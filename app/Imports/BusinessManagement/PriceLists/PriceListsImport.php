<?php

namespace App\Imports\BusinessManagement\PriceLists;

use App\Models\PriceList;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import de PriceLists con dedup 3-layer:
 *  - Layer 1: dedup in-file por name normalizado (case+accent insensitive).
 *  - Layer 2: DB lookup por name scoped al tenant.
 *  - Layer 3: PgSQL whereRaw con unaccent para match insensitive.
 *
 * El identificador unique per-tenant es `name` (no hay code separado).
 */
class PriceListsImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;

    /** @var array<int, array{row:int, message:string, value?:string}> */
    public array $errors = [];

    public array $preview = [];

    protected ?int $tenantId;

    protected int $maxRecords;

    protected int $currentCount;

    public function __construct(
        protected string $mode = 'update_or_create',
        protected bool $dryRun = false,
    ) {
        $user           = Auth::user();
        $this->tenantId = $user?->tenant_id;

        if ($user && $user->tenant) {
            $this->maxRecords = $user->tenant->maxRecordsPerModule();
        } else {
            $this->maxRecords = PHP_INT_MAX;
        }

        $this->currentCount = $this->tenantId !== null
            ? PriceList::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->count()
            : PriceList::withoutGlobalScopes()->count();
    }

    public function collection(Collection $rows): void
    {
        DB::beginTransaction();

        try {
            // Layer 1: dedup in-file por name normalizado.
            $seenInFileByName = [];
            $newRecordsCount  = 0;

            foreach ($rows as $i => $row) {
                $absoluteRow = $i + 2; // +2 = header (1) + indexacion desde 0.

                $name = $this->normalizeString($row['name'] ?? null);
                if ($name === null) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_name_required'),
                        'value'   => '—',
                    ];
                    continue;
                }
                if (mb_strlen($name) > 150) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_name_too_long'),
                        'value'   => mb_substr($name, 0, 60) . '…',
                    ];
                    continue;
                }

                $nameKey = mb_strtolower($name);
                if (isset($seenInFileByName[$nameKey])) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_duplicate_in_file', ['row' => $seenInFileByName[$nameKey]]),
                        'value'   => $name,
                    ];
                    continue;
                }
                $seenInFileByName[$nameKey] = $absoluteRow;

                $description       = $this->normalizeText($row['description'] ?? null, 500);
                $currencyCode      = $this->normalizeCurrency($row['currency_code'] ?? null);
                $globalDiscountPct = $this->normalizeDecimal($row['global_discount_pct'] ?? null) ?? 0;
                $priority          = $this->normalizeInt($row['priority'] ?? null) ?? 0;
                $validFrom         = $this->normalizeDate($row['valid_from'] ?? null);
                $validUntil        = $this->normalizeDate($row['valid_until'] ?? null);
                $isDefault         = $this->normalizeBool($row['is_default'] ?? null, default: false);
                $isActive          = $this->normalizeBool($row['is_active'] ?? null, default: true);

                // Layer 2: DB lookup por name, scoped al tenant.
                $existing = $this->findExistingByName($name);

                $payload = [
                    'name'                => $name,
                    'description'         => $description,
                    'currency_code'       => $currencyCode,
                    'global_discount_pct' => $globalDiscountPct,
                    'priority'            => $priority,
                    'valid_from'          => $validFrom,
                    'valid_until'         => $validUntil,
                    'is_default'          => $isDefault,
                    'is_active'           => $isActive,
                ];

                if ($existing) {
                    if ($this->mode === 'create_only') {
                        $this->skipped++;
                        $this->preview[] = ['row' => $absoluteRow, 'name' => $name, 'action' => 'skipped'];
                        continue;
                    }

                    $patch = [];
                    foreach ($payload as $k => $v) {
                        if ((string) $existing->{$k} !== (string) $v) {
                            $patch[$k] = $v;
                        }
                    }
                    if (!empty($patch)) {
                        $existing->fill($patch)->save();
                    }

                    $this->updated++;
                    $this->preview[] = ['row' => $absoluteRow, 'name' => $name, 'action' => 'updated'];
                } else {
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

                    PriceList::create(array_merge($payload, [
                        'created_by' => Auth::id(),
                    ]));

                    $newRecordsCount++;
                    $this->created++;
                    $this->preview[] = ['row' => $absoluteRow, 'name' => $name, 'action' => 'created'];
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

    protected function normalizeString(mixed $value): ?string
    {
        if ($value === null) return null;
        $s = trim((string) $value);
        return $s === '' ? null : $s;
    }

    protected function normalizeText(mixed $value, int $maxLen): ?string
    {
        if ($value === null) return null;
        $s = trim((string) $value);
        return $s === '' ? null : mb_substr($s, 0, $maxLen);
    }

    protected function normalizeCurrency(mixed $value): ?string
    {
        $s = $this->normalizeString($value);
        if ($s === null) return null;
        $s = strtoupper($s);
        return mb_strlen($s) === 3 ? $s : null;
    }

    protected function normalizeDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        return is_numeric($value) ? (float) $value : null;
    }

    protected function normalizeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        return is_numeric($value) ? (int) $value : null;
    }

    protected function normalizeDate(mixed $value): ?string
    {
        $s = $this->normalizeString($value);
        if ($s === null) return null;
        try {
            return \Carbon\Carbon::parse($s)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function findExistingByName(string $name): ?PriceList
    {
        $isPgsql = DB::getDriverName() === 'pgsql';
        $query   = PriceList::query()->withoutGlobalScopes();

        if ($this->tenantId !== null) {
            $query->where('price_lists.tenant_id', $this->tenantId);
        }

        if ($isPgsql) {
            $query->whereRaw('unaccent(LOWER(price_lists.name)) = unaccent(LOWER(?))', [$name]);
        } else {
            $query->whereRaw('LOWER(price_lists.name) = LOWER(?)', [$name]);
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
