<?php

namespace App\Imports\BusinessManagement\ProductCategories;

use App\Models\ProductCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductCategoriesImport implements ToCollection, WithHeadingRow
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
            ? ProductCategory::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->count()
            : ProductCategory::withoutGlobalScopes()->count();
    }

    public function collection(Collection $rows): void
    {
        DB::beginTransaction();

        try {
            $seenInFileByName = [];
            $newRecordsCount  = 0;

            foreach ($rows as $i => $row) {
                $absoluteRow = $i + 2;

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

                $parentName = $this->normalizeString($row['parent_name'] ?? null);
                $parentId   = null;
                if ($parentName !== null) {
                    $parent = $this->findCategoryByName($parentName);
                    if (!$parent) {
                        $this->errors[] = [
                            'row'     => $absoluteRow,
                            'message' => __('product_categories.parent_not_found'),
                            'value'   => $parentName,
                        ];
                        continue;
                    }
                    $parentId = $parent->id;
                }

                $description = $this->normalizeText($row['description'] ?? null, 500);
                $sortOrder   = $this->normalizeInt($row['sort_order'] ?? null) ?? 0;
                $isActive    = $this->normalizeBool($row['is_active'] ?? null, default: true);

                $nameKey = mb_strtolower($name) . '|' . ($parentId ?? '');
                if (isset($seenInFileByName[$nameKey])) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_duplicate_in_file', ['row' => $seenInFileByName[$nameKey]]),
                        'value'   => $name,
                    ];
                    continue;
                }
                $seenInFileByName[$nameKey] = $absoluteRow;

                $existing = $this->findExistingByName($name, $parentId);

                $payload = [
                    'description' => $description,
                    'parent_id'   => $parentId,
                    'sort_order'  => $sortOrder,
                    'is_active'   => $isActive,
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

                    ProductCategory::create(array_merge($payload, [
                        'name'       => $name,
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

    protected function normalizeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        return is_numeric($value) ? (int) $value : null;
    }

    protected function findCategoryByName(string $name): ?ProductCategory
    {
        $query = ProductCategory::query()->withoutGlobalScopes();

        if ($this->tenantId !== null) {
            $query->where('product_categories.tenant_id', $this->tenantId);
        }

        $isPgsql = DB::getDriverName() === 'pgsql';
        if ($isPgsql) {
            $query->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$name]);
        } else {
            $query->whereRaw('LOWER(name) = LOWER(?)', [$name]);
        }

        return $query->first();
    }

    protected function findExistingByName(string $name, ?int $parentId): ?ProductCategory
    {
        $query = ProductCategory::query()->withoutGlobalScopes();

        if ($this->tenantId !== null) {
            $query->where('product_categories.tenant_id', $this->tenantId);
        }

        $isPgsql = DB::getDriverName() === 'pgsql';
        if ($isPgsql) {
            $query->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$name]);
        } else {
            $query->whereRaw('LOWER(name) = LOWER(?)', [$name]);
        }

        if ($parentId) {
            $query->where('parent_id', $parentId);
        } else {
            $query->whereNull('parent_id');
        }

        return $query->first();
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
