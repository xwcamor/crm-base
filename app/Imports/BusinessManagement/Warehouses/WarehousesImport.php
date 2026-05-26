<?php

namespace App\Imports\BusinessManagement\Warehouses;

use App\Models\Warehouse;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class WarehousesImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;

    /** @var array<int, array{row:int, message:string, value?:string}> */
    public array $errors = [];

    public array $preview = [];

/** tenant_id del usuario autenticado, capturado al construir. */
    protected ?int $tenantId;

    /** Limite de records del plan (>0 = aplica; 0 o PHP_INT_MAX = ilimitado). */
    protected int $maxRecords;

    /** Count actual de warehouses del tenant (pre-import). */
    protected int $currentCount;

    public function __construct(
        protected string $mode = 'update_or_create',
        protected bool $dryRun = false,
    ) {
        $user           = Auth::user();
        $this->tenantId = $user?->tenant_id;

        // Plan limit del tenant. super sin tenant_id → sin limite.
        if ($user && $user->tenant) {
            $this->maxRecords = $user->tenant->maxRecordsPerModule();
        } else {
            $this->maxRecords = PHP_INT_MAX;
        }

        // Snapshot del count actual. Para super contamos sin scope.
        $this->currentCount = $this->tenantId !== null
            ? Warehouse::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->count()
            : Warehouse::withoutGlobalScopes()->count();

}

    public function collection(Collection $rows): void
    {
        DB::beginTransaction();

        try {
            // Layer 1: dedup in-file por nombre normalizado.
            $seenInFileByName = [];
$newRecordsCount = 0; // contador de filas que crearian un nuevo warehouse

            foreach ($rows as $i => $row) {
                $absoluteRow = $i + 2; // +2 = header (1) + indexacion desde 0.

                $name = $this->normalizeName($row['name'] ?? null);
                if ($name === null) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_name_required'),
                        'value'   => '—',
                    ];
                    continue;
                }
                if (mb_strlen($name) > 255) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_name_too_long'),
                        'value'   => mb_substr($name, 0, 60) . '…',
                    ];
                    continue;
                }

                $normNameKey = $this->normalizeKey($name);
                if (isset($seenInFileByName[$normNameKey])) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_duplicate_in_file', ['row' => $seenInFileByName[$normNameKey]]),
                        'value'   => $name,
                    ];
                    continue;
                }
                $seenInFileByName[$normNameKey] = $absoluteRow;

                $description = $this->normalizeDescription($row['description'] ?? null);

$isActive = $this->normalizeBool($row['is_active'] ?? null, default: true);

                // Layer 2: DB lookup case + accent insensitive (scoped al tenant).
                $existing = $this->findExistingByNameInsensitive($name);

                if ($existing) {
                    if ($this->mode === 'create_only') {
                        $this->skipped++;
                        $this->preview[] = [
                            'row'         => $absoluteRow,
                            'name'        => $name,
                            'description' => $description,
                            'is_active'   => $isActive,
                            'action'      => 'skipped',
                        ];
                        continue;
                    }

                    // Solo tocar campos que cambian (evita audit logs vacios).
                    $patch = [];
                    if ((bool) $existing->is_active !== $isActive)      $patch['is_active']  = $isActive;
if (!empty($patch)) {
                        $existing->fill($patch)->save();
                    }

                    $this->updated++;
                    $this->preview[] = [
                        'row'         => $absoluteRow,
                        'name'        => $name,
                            'description' => $description,
                        'is_active'   => $isActive,
                        'action'      => 'updated',
                    ];
                } else {
                    // Antes de crear, validar limite del plan.
                    if ($this->maxRecords > 0 && $this->maxRecords !== PHP_INT_MAX) {
                        if (($this->currentCount + $newRecordsCount) >= $this->maxRecords) {
                            $this->errors[] = [
                                'row'     => $absoluteRow,
                                'message' => __('plans.limit_records_reached', ['max' => $this->maxRecords]),
                                'value'   => $name,
                            ];
                            continue;
                        }
                    }

                    Warehouse::create([
                        'name'       => $name,
                        'description' => $description,
                        'is_active'  => $isActive,
                        'created_by' => Auth::id(),
                        // tenant_id lo auto-fillea el trait BelongsToTenant.
                    ]);

                    $newRecordsCount++;
                    $this->created++;
                    $this->preview[] = [
                        'row'         => $absoluteRow,
                        'name'        => $name,
                            'description' => $description,
                        'is_active'   => $isActive,
                        'action'      => 'created',
                    ];
                }
            }

            if ($this->dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function summary(): array
    {
        return [
            'created'      => $this->created,
            'updated'      => $this->updated,
            'skipped'      => $this->skipped,
            'error_count'  => count($this->errors),
            'total_rows'   => $this->created + $this->updated + $this->skipped + count($this->errors),
            'errors'       => array_slice($this->errors, 0, 50),
            'preview'      => array_slice($this->preview, 0, 100),
            'dry_run'      => $this->dryRun,
        ];
    }

    protected function normalizeName(mixed $value): ?string
    {
        if ($value === null) return null;
        $name = trim((string) $value);
        return $name === '' ? null : $name;
    }

    protected function normalizeDescription(mixed $value): ?string
    {
        if ($value === null) return null;
        $desc = trim((string) $value);
        return $desc === '' ? null : mb_substr($desc, 0, 1000);
    }

/** Lowercase + strip accents (iconv) — mismo pattern que el DB-level layer 2. */
    protected function normalizeKey(string $name): string
    {
        $lower    = mb_strtolower(trim($name));
        $stripped = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lower);
        return $stripped !== false ? $stripped : $lower;
    }

    /**
     * Lookup case + accent insensitive (Postgres unaccent / fallback LOWER),
     * scoped al tenant del usuario.
     */
    protected function findExistingByNameInsensitive(string $name): ?Warehouse
    {
        $isPgsql = DB::getDriverName() === 'pgsql';
        $query   = Warehouse::query()->withoutGlobalScopes();

        if ($this->tenantId !== null) {
            $query->where('warehouses.tenant_id', $this->tenantId);
        }

        if ($isPgsql) {
            $query->whereRaw('unaccent(LOWER(warehouses.name)) = unaccent(LOWER(?))', [$name]);
        } else {
            $query->whereRaw('LOWER(warehouses.name) = LOWER(?)', [$name]);
        }

        return $query->first();
    }

    /** Acepta 1/0, true/false, si/no, activo/inactivo, yes/no, active/inactive, x. */
    protected function normalizeBool(mixed $value, bool $default = true): bool
    {
        if ($value === null || $value === '') return $default;
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return ((int) $value) === 1;

        $normalized = mb_strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 't', 'yes', 'y', 'sí', 'si', 's', 'activo', 'active', 'x'], true);
    }
}
