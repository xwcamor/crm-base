<?php

namespace App\Imports\BusinessManagement\ExchangeRates;

use App\Models\ExchangeRate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Importer 3-layer dedup (in-file + DB lookup + DB unique constraint).
 * Mode: create_only | update_or_create. dryRun rollback automatico.
 */
class ExchangeRatesImport implements ToCollection, WithHeadingRow
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
            ? ExchangeRate::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->count()
            : ExchangeRate::withoutGlobalScopes()->count();
    }

    public function collection(Collection $rows): void
    {
        DB::beginTransaction();

        try {
            // Layer 1: dedup in-file por triple (base, quote, valid_at).
            $seenInFile      = [];
            $newRecordsCount = 0;

            foreach ($rows as $i => $row) {
                $absoluteRow = $i + 2;

                $base = $this->normalizeCode($row['base_code'] ?? null);
                if ($base === null) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_base_code_required'),
                        'value'   => '—',
                    ];
                    continue;
                }
                $quote = $this->normalizeCode($row['quote_code'] ?? null);
                if ($quote === null) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_quote_code_required'),
                        'value'   => $base,
                    ];
                    continue;
                }
                if ($base === $quote) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_codes_must_differ'),
                        'value'   => "{$base}/{$quote}",
                    ];
                    continue;
                }

                $rateValue = $this->normalizeDecimal($row['rate'] ?? null);
                if ($rateValue === null || $rateValue <= 0) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_rate_required'),
                        'value'   => "{$base}/{$quote}",
                    ];
                    continue;
                }

                $validAt = $this->normalizeDate($row['valid_at'] ?? null);
                if ($validAt === null) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_valid_at_required'),
                        'value'   => "{$base}/{$quote}",
                    ];
                    continue;
                }

                $key = "{$base}|{$quote}|{$validAt}";
                if (isset($seenInFile[$key])) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_duplicate_in_file', ['row' => $seenInFile[$key]]),
                        'value'   => "{$base}/{$quote} @ {$validAt}",
                    ];
                    continue;
                }
                $seenInFile[$key] = $absoluteRow;

                $source   = $this->normalizeString($row['source'] ?? null) ?? 'manual';
                $isActive = $this->normalizeBool($row['is_active'] ?? null, default: true);

                $payload = [
                    'rate'      => $rateValue,
                    'source'    => mb_substr($source, 0, 60),
                    'is_active' => $isActive,
                ];

                // Layer 2: DB lookup por triple (base, quote, valid_at), scoped al tenant.
                $existing = $this->findExisting($base, $quote, $validAt);

                if ($existing) {
                    if ($this->mode === 'create_only') {
                        $this->skipped++;
                        $this->preview[] = ['row' => $absoluteRow, 'display' => "{$base}/{$quote} @ {$validAt}", 'action' => 'skipped'];
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
                    $this->preview[] = ['row' => $absoluteRow, 'display' => "{$base}/{$quote} @ {$validAt}", 'action' => 'updated'];
                } else {
                    if ($this->maxRecords > 0 && $this->maxRecords !== PHP_INT_MAX) {
                        if (($this->currentCount + $newRecordsCount) >= $this->maxRecords) {
                            $this->errors[] = [
                                'row'     => $absoluteRow,
                                'message' => __('plans.limit_records_reached', ['max' => $this->maxRecords]),
                                'value'   => "{$base}/{$quote}",
                            ];
                            continue;
                        }
                    }

                    ExchangeRate::create(array_merge($payload, [
                        'base_code'  => $base,
                        'quote_code' => $quote,
                        'valid_at'   => $validAt,
                        'created_by' => Auth::id(),
                    ]));

                    $newRecordsCount++;
                    $this->created++;
                    $this->preview[] = ['row' => $absoluteRow, 'display' => "{$base}/{$quote} @ {$validAt}", 'action' => 'created'];
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

    protected function normalizeCode(mixed $value): ?string
    {
        $s = $this->normalizeString($value);
        if ($s === null) return null;
        $s = strtoupper($s);
        return preg_match('/^[A-Z]{3}$/', $s) ? $s : null;
    }

    protected function normalizeDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        return is_numeric($value) ? (float) $value : null;
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

    protected function findExisting(string $base, string $quote, string $validAt): ?ExchangeRate
    {
        $query = ExchangeRate::query()->withoutGlobalScopes();

        if ($this->tenantId !== null) {
            $query->where('exchange_rates.tenant_id', $this->tenantId);
        } else {
            $query->whereNull('exchange_rates.tenant_id');
        }

        $query->where('exchange_rates.base_code',  $base)
              ->where('exchange_rates.quote_code', $quote)
              ->where('exchange_rates.valid_at',   $validAt);

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
