<?php

namespace App\Imports\BusinessManagement\Discounts;

use App\Models\Discount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DiscountsImport implements ToCollection, WithHeadingRow
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
            ? Discount::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->count()
            : Discount::withoutGlobalScopes()->count();
    }

    public function collection(Collection $rows): void
    {
        DB::beginTransaction();

        try {
            // Layer 1: dedup in-file por code normalizado.
            $seenInFileByCode = [];
            $newRecordsCount  = 0;

            foreach ($rows as $i => $row) {
                $absoluteRow = $i + 2; // +2 = header (1) + indexacion desde 0.

                $code = $this->normalizeString($row['code'] ?? null);
                if ($code === null) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_code_required'),
                        'value'   => '—',
                    ];
                    continue;
                }
                if (mb_strlen($code) > 60) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_code_too_long'),
                        'value'   => mb_substr($code, 0, 60) . '…',
                    ];
                    continue;
                }

                $name = $this->normalizeString($row['name'] ?? null);
                if ($name === null) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_name_required'),
                        'value'   => $code,
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

                $type = $this->normalizeString($row['type'] ?? null) ?? 'percentage';
                if (!in_array($type, Discount::TYPES, true)) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_invalid_type'),
                        'value'   => $type,
                    ];
                    continue;
                }

                $codeKey = mb_strtolower($code);
                if (isset($seenInFileByCode[$codeKey])) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_duplicate_in_file', ['row' => $seenInFileByCode[$codeKey]]),
                        'value'   => $code,
                    ];
                    continue;
                }
                $seenInFileByCode[$codeKey] = $absoluteRow;

                $description       = $this->normalizeText($row['description'] ?? null, 500);
                $value             = (float) ($row['value'] ?? 0);
                $currencyCode      = $this->normalizeCurrency($row['currency_code'] ?? null);
                $minPurchase       = $this->normalizeDecimal($row['min_purchase_amount'] ?? null);
                $usageLimit        = $this->normalizeInt($row['usage_limit'] ?? null);
                $usagePerCustomer  = $this->normalizeInt($row['usage_per_customer'] ?? null);
                $validFrom         = $this->normalizeDate($row['valid_from'] ?? null);
                $validUntil        = $this->normalizeDate($row['valid_until'] ?? null);
                $isActive          = $this->normalizeBool($row['is_active'] ?? null, default: true);

                // Layer 2: DB lookup por code, scoped al tenant.
                $existing = $this->findExistingByCode($code);

                $payload = [
                    'name'                => $name,
                    'description'         => $description,
                    'type'                => $type,
                    'value'               => $value,
                    'currency_code'       => $currencyCode,
                    'min_purchase_amount' => $minPurchase,
                    'usage_limit'         => $usageLimit,
                    'usage_per_customer'  => $usagePerCustomer,
                    'valid_from'          => $validFrom,
                    'valid_until'         => $validUntil,
                    'is_active'           => $isActive,
                ];

                if ($existing) {
                    if ($this->mode === 'create_only') {
                        $this->skipped++;
                        $this->preview[] = ['row' => $absoluteRow, 'code' => $code, 'name' => $name, 'action' => 'skipped'];
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
                    $this->preview[] = ['row' => $absoluteRow, 'code' => $code, 'name' => $name, 'action' => 'updated'];
                } else {
                    if ($this->maxRecords > 0 && $this->maxRecords !== PHP_INT_MAX) {
                        if (($this->currentCount + $newRecordsCount) >= $this->maxRecords) {
                            $this->errors[] = [
                                'row'     => $absoluteRow,
                                'message' => __('plans.limit_records_reached', ['max' => $this->maxRecords]),
                                'value'   => $code,
                            ];
                            continue;
                        }
                    }

                    Discount::create(array_merge($payload, [
                        'code'       => $code,
                        'created_by' => Auth::id(),
                    ]));

                    $newRecordsCount++;
                    $this->created++;
                    $this->preview[] = ['row' => $absoluteRow, 'code' => $code, 'name' => $name, 'action' => 'created'];
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

    protected function findExistingByCode(string $code): ?Discount
    {
        $query = Discount::query()->withoutGlobalScopes();

        if ($this->tenantId !== null) {
            $query->where('discounts.tenant_id', $this->tenantId);
        }

        $query->where('discounts.code', $code);

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
