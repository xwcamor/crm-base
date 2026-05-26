<?php

namespace App\Imports\SystemManagement\Plans;

use App\Models\Plan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import de Plans con dedup 3-layer. Plans es super-only, sin tenant scope:
 * el slug es unique global.
 *
 *   - Layer 1: dedup in-file por slug normalizado (case-insensitive).
 *   - Layer 2: DB lookup por slug global (no per-tenant).
 *   - Layer 3: el unique constraint de DB es la red final.
 */
class PlansImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;

    /** @var array<int, array{row:int, message:string, value?:string}> */
    public array $errors = [];

    public array $preview = [];

    public function __construct(
        protected string $mode = 'update_or_create',
        protected bool $dryRun = false,
    ) {}

    public function collection(Collection $rows): void
    {
        DB::beginTransaction();

        try {
            $seenInFileBySlug = [];

            foreach ($rows as $i => $row) {
                $absoluteRow = $i + 2;

                $slug = $this->normalizeString($row['slug'] ?? null);
                if ($slug === null) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('plans.err_slug_required'),
                        'value'   => '—',
                    ];
                    continue;
                }
                $slug = strtolower($slug);
                if (!preg_match('/^[a-z][a-z0-9_]*$/', $slug)) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('plans.err_slug_invalid'),
                        'value'   => $slug,
                    ];
                    continue;
                }
                if (mb_strlen($slug) > 60) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('plans.err_slug_too_long'),
                        'value'   => mb_substr($slug, 0, 60) . '…',
                    ];
                    continue;
                }

                $name = $this->normalizeString($row['name'] ?? null);
                if ($name === null) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('plans.err_name_required'),
                        'value'   => $slug,
                    ];
                    continue;
                }
                if (mb_strlen($name) > 100) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('plans.err_name_too_long'),
                        'value'   => mb_substr($name, 0, 60) . '…',
                    ];
                    continue;
                }

                if (isset($seenInFileBySlug[$slug])) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_duplicate_in_file', ['row' => $seenInFileBySlug[$slug]]),
                        'value'   => $slug,
                    ];
                    continue;
                }
                $seenInFileBySlug[$slug] = $absoluteRow;

                $support = $this->normalizeString($row['support_level'] ?? null) ?? 'community';
                if (!in_array($support, Plan::SUPPORT_LEVELS, true)) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('plans.err_invalid_support'),
                        'value'   => $support,
                    ];
                    continue;
                }

                $payload = [
                    'name'                   => $name,
                    'tagline'                => $this->normalizeText($row['tagline'] ?? null, 200),
                    'support_level'          => $support,
                    'max_users'              => $this->normalizeInt($row['max_users'] ?? null, default: 1),
                    'max_records_per_module' => $this->normalizeInt($row['max_records_per_module'] ?? null, default: 100),
                    'export_rate_limit'      => max(1, min(10000, $this->normalizeInt($row['export_rate_limit'] ?? null, default: 1))),
                    'price_monthly'          => $this->normalizeDecimal($row['price_monthly'] ?? null) ?? 0,
                    'price_yearly'           => $this->normalizeDecimal($row['price_yearly']  ?? null) ?? 0,
                    'currency'               => $this->normalizeCurrency($row['currency']     ?? null) ?? 'USD',
                    'is_active'              => $this->normalizeBool($row['is_active']        ?? null, default: true),
                    'is_public'              => $this->normalizeBool($row['is_public']        ?? null, default: true),
                ];

                $existing = Plan::withTrashed()->where('slug', $slug)->first();

                if ($existing) {
                    if ($this->mode === 'create_only') {
                        $this->skipped++;
                        $this->preview[] = ['row' => $absoluteRow, 'slug' => $slug, 'name' => $name, 'action' => 'skipped'];
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
                    $this->preview[] = ['row' => $absoluteRow, 'slug' => $slug, 'name' => $name, 'action' => 'updated'];
                } else {
                    Plan::create(array_merge($payload, [
                        'slug'       => $slug,
                        'sort_order' => (int) Plan::max('sort_order') + 1,
                        'created_by' => Auth::id(),
                    ]));

                    $this->created++;
                    $this->preview[] = ['row' => $absoluteRow, 'slug' => $slug, 'name' => $name, 'action' => 'created'];
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

    protected function normalizeInt(mixed $value, int $default = 0): int
    {
        if ($value === null || $value === '') return $default;
        return is_numeric($value) ? (int) $value : $default;
    }

    protected function normalizeBool(mixed $value, bool $default = true): bool
    {
        if ($value === null || $value === '') return $default;
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return ((int) $value) === 1;

        $normalized = mb_strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 't', 'yes', 'y', 'sí', 'si', 's', 'activo', 'active', 'x'], true);
    }
}
