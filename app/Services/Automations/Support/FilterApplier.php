<?php

namespace App\Services\Automations\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * FilterApplier — aplica los filtros de automation.data_filter a un query.
 *
 * Shape de data_filter:
 *   {
 *     "where": [
 *       { "field": "status",     "op": "=",        "value": "pending" },
 *       { "field": "created_at", "op": ">=",       "value": "2026-01-01" },
 *       { "field": "name",       "op": "contains", "value": "test" }
 *     ],
 *     "limit": 100
 *   }
 *
 * Operadores soportados: =, !=, >, <, >=, <=, in, contains.
 *
 * Allowlist de campos: cada DataSource declara sus fields con `type` (string,
 * boolean, enum, number, date, datetime). El FilterApplier:
 *   1) Ignora cualquier campo fuera de la allowlist (defensa contra payloads
 *      maliciosos que intenten filtrar por columnas sensibles).
 *   2) Coerce el value al tipo declarado antes de la query. Esto cubre 3
 *      escenarios donde un value invalido puede entrar: automatizaciones viejas
 *      en BD, imports CSV con strings, llamadas externas via API. Sin esta
 *      capa, "activo" en lugar de true mataba el filtro en silencio.
 */
class FilterApplier
{
    /**
     * @param Builder $query
     * @param array   $filter         data_filter completo
     * @param array   $allowedFields  lista de field meta arrays (con key + type)
     *                                o lista de strings (compatibilidad backwards)
     */
    public static function apply(Builder $query, array $filter, array $allowedFields): void
    {
        $fieldMetaByKey = self::normalizeAllowedFields($allowedFields);

        $where = $filter['where'] ?? [];
        foreach ($where as $clause) {
            $field = $clause['field'] ?? null;
            $op    = $clause['op']    ?? '=';
            $value = $clause['value'] ?? null;

            if (!$field || !isset($fieldMetaByKey[$field])) continue;

            $meta = $fieldMetaByKey[$field];
            $value = self::coerceValue($value, $meta, $op);
            if ($value === '__INVALID__') continue;

            self::applyClause($query, $field, $op, $value);
        }
    }

    /**
     * Compatibilidad: si el caller pasa solo strings (lista de keys), los
     * convertimos a estructura {key => meta} con type 'string' por defecto.
     * Si pasa arrays con 'key' y 'type', los respetamos.
     */
    protected static function normalizeAllowedFields(array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            if (is_string($f)) {
                $out[$f] = ['key' => $f, 'type' => 'string'];
            } elseif (is_array($f) && isset($f['key'])) {
                $out[$f['key']] = $f;
            }
        }
        return $out;
    }

    /**
     * Coerce el value al tipo declarado. Devuelve '__INVALID__' si el value
     * no se puede mapear (la clausula se descarta para no romper la query).
     */
    protected static function coerceValue(mixed $value, array $meta, string $op): mixed
    {
        // Operador 'in' fuerza array siempre, sin importar el type del field.
        if ($op === 'in') {
            if (is_string($value)) {
                $value = array_values(array_filter(
                    array_map('trim', explode(',', $value)),
                    fn ($v) => $v !== ''
                ));
            }
            if (!is_array($value) || empty($value)) return '__INVALID__';
            return $value;
        }

        if ($value === null || $value === '') {
            // Para contains/string vacios, dejar pasar (matchea todo). Para
            // resto, descartar — comparar contra null suele ser bug.
            return $op === 'contains' ? '' : '__INVALID__';
        }

        $type = $meta['type'] ?? 'string';

        switch ($type) {
            case 'boolean':
                return self::toBoolean($value);

            case 'number':
            case 'int':
                if (!is_numeric($value)) return '__INVALID__';
                return is_int($value + 0) ? (int) $value : (float) $value;

            case 'date':
                $ts = strtotime((string) $value);
                return $ts === false ? '__INVALID__' : date('Y-m-d', $ts);

            case 'datetime':
                $ts = strtotime((string) $value);
                return $ts === false ? '__INVALID__' : date('Y-m-d H:i:s', $ts);

            case 'enum':
                $allowed = array_column($meta['options'] ?? [], 'value');
                return in_array($value, $allowed, true) ? $value : '__INVALID__';

            case 'string':
            default:
                return (string) $value;
        }
    }

    /**
     * Acepta true/false reales, "true"/"false", "1"/"0", 1/0, "yes"/"no",
     * "si"/"no", "activo"/"inactivo", "active"/"inactive". Cualquier otra
     * cosa devuelve '__INVALID__' para descartar la clausula.
     */
    protected static function toBoolean(mixed $value): bool|string
    {
        if (is_bool($value)) return $value;
        if (is_int($value))  return $value === 1;

        $norm = mb_strtolower(trim((string) $value));
        $trueSet  = ['1', 'true', 't', 'yes', 'y', 'si', 'sí', 'activo', 'active', 'ativo'];
        $falseSet = ['0', 'false', 'f', 'no',  'n',                  'inactivo', 'inactive', 'inativo'];

        if (in_array($norm, $trueSet,  true)) return true;
        if (in_array($norm, $falseSet, true)) return false;
        return '__INVALID__';
    }

    protected static function applyClause(Builder $query, string $field, string $op, mixed $value): void
    {
        switch ($op) {
            case '=':
            case '!=':
            case '>':
            case '<':
            case '>=':
            case '<=':
                $query->where($field, $op, $value);
                break;
            case 'in':
                if (is_array($value) && !empty($value)) {
                    $query->whereIn($field, $value);
                }
                break;
            case 'contains':
                $query->where($field, 'ILIKE', '%' . str_replace('%', '\\%', (string) $value) . '%');
                break;
        }
    }
}
