<?php

namespace App\Imports\BusinessManagement\ProductVariants;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductVariantsImport implements ToCollection, WithHeadingRow
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
            ? ProductVariant::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->count()
            : ProductVariant::withoutGlobalScopes()->count();
    }

    public function collection(Collection $rows): void
    {
        DB::beginTransaction();

        try {
            $seenInFileBySku = [];
            $newRecordsCount  = 0;

            foreach ($rows as $i => $row) {
                $absoluteRow = $i + 2;

                $sku = $this->normalizeString($row['sku'] ?? null);
                if ($sku === null) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('product_variants.sku_required'),
                        'value'   => '—',
                    ];
                    continue;
                }
                if (mb_strlen($sku) > 60) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_too_long'),
                        'value'   => mb_substr($sku, 0, 60) . '…',
                    ];
                    continue;
                }

                $name = $this->normalizeString($row['name'] ?? null);
                if ($name === null) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_name_required'),
                        'value'   => $sku,
                    ];
                    continue;
                }
                if (mb_strlen($name) > 200) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_name_too_long'),
                        'value'   => mb_substr($name, 0, 60) . '…',
                    ];
                    continue;
                }

                $productSku = $this->normalizeString($row['product_sku'] ?? null);
                $productId  = null;
                if ($productSku !== null) {
                    $product = $this->findProductBySku($productSku);
                    if (!$product) {
                        $this->errors[] = [
                            'row'     => $absoluteRow,
                            'message' => __('product_variants.product_not_found'),
                            'value'   => $productSku,
                        ];
                        continue;
                    }
                    $productId = $product->id;
                } else {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('product_variants.product_required'),
                        'value'   => $sku,
                    ];
                    continue;
                }

                $barcode    = $this->normalizeString($row['barcode'] ?? null);
                $attributes = $this->normalizeJson($row['attributes'] ?? null);
                $cost       = $this->normalizeDecimal($row['cost']  ?? null);
                $price      = $this->normalizeDecimal($row['price'] ?? null);
                $sortOrder  = $this->normalizeInt($row['sort_order'] ?? null) ?? 0;
                $isActive   = $this->normalizeBool($row['is_active'] ?? null, default: true);

                $skuKey = mb_strtolower($sku);
                if (isset($seenInFileBySku[$skuKey])) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_duplicate_in_file', ['row' => $seenInFileBySku[$skuKey]]),
                        'value'   => $sku,
                    ];
                    continue;
                }
                $seenInFileBySku[$skuKey] = $absoluteRow;

                $existing = $this->findExistingBySku($sku);

                $payload = [
                    'name'       => $name,
                    'product_id' => $productId,
                    'barcode'    => $barcode,
                    'attributes' => $attributes,
                    'cost'       => $cost,
                    'price'      => $price,
                    'sort_order' => $sortOrder,
                    'is_active'  => $isActive,
                ];

                if ($existing) {
                    if ($this->mode === 'create_only') {
                        $this->skipped++;
                        $this->preview[] = ['row' => $absoluteRow, 'sku' => $sku, 'action' => 'skipped'];
                        continue;
                    }

                    $patch = [];
                    foreach ($payload as $k => $v) {
                        $current = $existing->{$k};
                        if (is_array($current) || is_array($v)) {
                            if (json_encode($current) !== json_encode($v)) {
                                $patch[$k] = $v;
                            }
                        } else {
                            if ((string) $current !== (string) $v) {
                                $patch[$k] = $v;
                            }
                        }
                    }
                    if (!empty($patch)) {
                        $existing->fill($patch)->save();
                    }

                    $this->updated++;
                    $this->preview[] = ['row' => $absoluteRow, 'sku' => $sku, 'action' => 'updated'];
                } else {
                    if ($this->maxRecords > 0 && $this->maxRecords !== PHP_INT_MAX) {
                        if (($this->currentCount + $newRecordsCount) >= $this->maxRecords) {
                            $this->errors[] = [
                                'row'     => $absoluteRow,
                                'message' => __('plans.limit_records_reached', ['max' => $this->maxRecords]),
                                'value'   => $sku,
                            ];
                            continue;
                        }
                    }

                    ProductVariant::create(array_merge($payload, [
                        'sku'        => $sku,
                        'created_by' => Auth::id(),
                    ]));

                    $newRecordsCount++;
                    $this->created++;
                    $this->preview[] = ['row' => $absoluteRow, 'sku' => $sku, 'action' => 'created'];
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

    protected function normalizeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        return is_numeric($value) ? (int) $value : null;
    }

    protected function normalizeDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        return is_numeric($value) ? (float) $value : null;
    }

    protected function normalizeJson(mixed $value): ?array
    {
        if ($value === null || $value === '') return null;
        if (is_array($value)) return $value;
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : null;
    }

    protected function findProductBySku(string $sku): ?Product
    {
        $query = Product::query()->withoutGlobalScopes();

        if ($this->tenantId !== null) {
            $query->where('products.tenant_id', $this->tenantId);
        }

        $query->whereRaw('LOWER(sku) = LOWER(?)', [$sku]);

        return $query->first();
    }

    protected function findExistingBySku(string $sku): ?ProductVariant
    {
        $query = ProductVariant::query()->withoutGlobalScopes();

        if ($this->tenantId !== null) {
            $query->where('product_variants.tenant_id', $this->tenantId);
        }

        $query->whereRaw('LOWER(sku) = LOWER(?)', [$sku]);

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
