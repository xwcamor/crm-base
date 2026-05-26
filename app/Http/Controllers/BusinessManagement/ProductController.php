<?php

namespace App\Http\Controllers\BusinessManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessManagement\Product\BulkDeleteProductRequest;
use App\Http\Requests\BusinessManagement\Product\BulkRestoreProductRequest;
use App\Http\Requests\BusinessManagement\Product\BulkSetActiveProductRequest;
use App\Http\Requests\BusinessManagement\Product\DeleteProductRequest;
use App\Http\Requests\BusinessManagement\Product\EditAllUpdateProductRequest;
use App\Http\Requests\BusinessManagement\Product\ForceDeleteProductRequest;
use App\Http\Requests\BusinessManagement\Product\ImportProductRequest;
use App\Http\Requests\BusinessManagement\Product\StoreProductRequest;
use App\Http\Requests\BusinessManagement\Product\UpdateProductRequest;
use App\Http\Resources\AuditLogResource;
use App\Jobs\BusinessManagement\Products\GenerateProductsCsvJob;
use App\Jobs\BusinessManagement\Products\GenerateProductsExcelJob;
use App\Jobs\BusinessManagement\Products\GenerateProductsPdfJob;
use App\Jobs\BusinessManagement\Products\GenerateProductsWordJob;
use App\Models\AuditLog;
use App\Models\Product;
use App\Services\BusinessManagement\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100, 200]) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'desc']);
        }

        $userId  = $request->user()?->id;
        $isSuper = $request->user()?->hasRole('super') ?? false;

        // Solo super necesita el tenant eager-loaded — admins ven solo los suyos
        // y la columna workspace queda oculta en el frontend.
        $with = ['creator:id,name,email', 'category:id,name'];
        if ($isSuper) {
            $with[] = 'tenant:id,name';
        }

        $products = Product::query()
            ->select('products.*')
            ->with($with)
            ->orderByFavoriteFirst($userId)
            ->filter($request)
            ->paginate($perPage)
            ->withQueryString();

        $totalUnfiltered = Product::count();

        $names = $request->get('name', []);
        if (is_string($names)) $names = $names === '' ? [] : [$names];

        return inertia('Products/Index', [
            'products' => array_merge($products->toArray(), [
                'total_unfiltered' => $totalUnfiltered,
            ]),
            // Limites de export por formato — el frontend deshabilita formatos
            // que exceden su limite. CSV con 0 = sin limite (streaming).
            'exportLimits' => \App\Models\Setting::getExportLimits('products'),
            'filters' => [
                'name'           => array_values($names),
                'sku'            => $request->get('sku', ''),
                'type'           => $request->get('type', ''),
                'category_id'    => $request->get('category_id', null),
                'brand'          => $request->get('brand', ''),
                'currency_code'  => $request->get('currency_code', null),
                'price_from'     => $request->get('price_from', null),
                'price_to'       => $request->get('price_to', null),
                'is_active'      => $request->has('is_active') && $request->is_active !== ''
                    ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'created_from'   => $request->get('created_from', ''),
                'created_to'     => $request->get('created_to', ''),
                'only_favorites' => $request->boolean('only_favorites'),
                'sort'           => $request->get('sort', 'id'),
                'direction'      => $request->get('direction', 'desc'),
                'per_page'       => $perPage,
                'advanced_where' => $this->parseAdvancedWhere($request),
            ],
            'isSuper'        => $isSuper,
            'filterSchema'   => Product::filterSchema(),
            // Options para los filtros simples (drawer/sidebar de filtros).
            'filterOptions'  => [
                'categoryOptions' => \App\Models\ProductCategory::where('is_active', true)->orderBy('name')->get(['id','name'])
                    ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
                'typeOptions'     => collect(Product::TYPES)
                    ->map(fn ($t) => ['value' => $t, 'label' => __('products.type_options.' . $t)])->all(),
                'currencyOptions' => \App\Models\Currency::where('is_active', true)->orderBy('code')->get(['code'])
                    ->map(fn ($c) => ['value' => $c->code, 'label' => $c->code])->all(),
            ],
        ]);
    }

    /**
     * Normaliza `advanced_where` del request: viene como JSON string o
     * array directo segun como Inertia lo serialice. Filtra clausulas
     * vacias o incompletas antes de pasarlo al frontend.
     */
    protected function parseAdvancedWhere(\Illuminate\Http\Request $request): array
    {
        $raw = $request->input('advanced_where', []);
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }
        if (!is_array($raw)) return [];

        return array_values(array_filter($raw, fn ($c) =>
            is_array($c) && !empty($c['field']) && !empty($c['op'])
        ));
    }

    public function show(Request $request, Product $product)
    {
        $product->load(['creator:id,name,email', 'deleter:id,name,email', 'category:id,name']);

        $canSeeAudit = $request->user()?->hasAnyRole(['super', 'admin']) ?? false;
        $activity = $canSeeAudit
            ? AuditLogResource::collection(
                AuditLog::query()
                    ->where('auditable_type', Product::class)
                    ->where('auditable_id', $product->id)
                    ->with('user:id,name,email')
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get(['id', 'user_id', 'event', 'old_values', 'new_values', 'created_at'])
            )->resolve()
            : [];

        return inertia('Products/Show', [
            'product' => $this->payload($product, withAudit: true),
            'activity'     => $activity,
        ]);
    }

    public function create()
    {
        return inertia('Products/Form', array_merge(
            ['product' => null],
            $this->formSelectOptions()
        ));
    }

    protected function formSelectOptions(): array
    {
        return [
            'categoryOptions' => \App\Models\ProductCategory::query()
                ->where('is_active', true)->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])
                ->all(),

            'typeOptions' => collect(\App\Models\Product::TYPES)
                ->map(fn ($t) => ['value' => $t, 'label' => __('products.type_options.' . $t)])
                ->all(),

            'billingCycleOptions' => collect(\App\Models\Product::BILLING_CYCLES)
                ->map(fn ($t) => ['value' => $t, 'label' => __('products.billing_cycle_options.' . $t)])
                ->all(),

            'currencyOptions' => \App\Models\Currency::query()
                ->where('is_active', true)->orderBy('code')
                ->get(['code', 'name', 'symbol'])
                ->map(fn ($c) => ['value' => $c->code, 'label' => $c->code . ' — ' . $c->symbol . ' ' . $c->name])
                ->all(),

            'defaultCurrencyCode' => \App\Support\CurrencyResolver::forCurrentUser(),
        ];
    }

    public function store(StoreProductRequest $request, ProductService $service): RedirectResponse
    {
        // Limite de registros por modulo segun el plan del tenant.
        // super no tiene tenant → no aplica. -1 = ilimitado.
        $tenant = $request->user()?->tenant;
        if ($tenant) {
            $max = $tenant->maxRecordsPerModule();
            if ($max > 0 && Product::count() >= $max) {
                return back()->with('error', __('plans.limit_records_reached', ['max' => $max]));
            }
        }

        $service->create($request->validated());

        return redirect()
            ->route('business_management.products.index')
            ->with('success', __('products.created'));
    }

    public function edit(Product $product)
    {
        return inertia('Products/Form', array_merge(
            ['product' => $this->payload($product)],
            $this->formSelectOptions()
        ));
    }

public function update(UpdateProductRequest $request, Product $product, ProductService $service): RedirectResponse
    {
        $service->update($product, $request->validated());

        return redirect()
            ->route('business_management.products.index')
            ->with('success', __('products.saved'));
    }

    public function delete(Product $product)
    {
        return inertia('Products/Delete', [
            'product' => $this->payload($product),
        ]);
    }

    public function deleteSave(DeleteProductRequest $request, Product $product, ProductService $service): RedirectResponse
    {
        $service->delete($product, $request->validated()['deleted_description']);

        $this->storeUndoableDelete([$product->id]);

        return redirect()
            ->route('business_management.products.index')
            ->with('success', __('global.deleted_success'))
            ->with('recentDelete', $this->buildRecentDeletePayload([$product->id]));
    }

    /** Persiste el claim en sesion por el window de undo (60s). */
    protected function storeUndoableDelete(array $ids): void
    {
        session(['products.recent_delete' => [
            'ids'        => array_values($ids),
            'expires_at' => now()->addSeconds(60)->toIso8601String(),
        ]]);
    }

    /** Payload que va al frontend via flash para disparar el toast. */
    protected function buildRecentDeletePayload(array $ids): array
    {
        return [
            'count'   => count($ids),
            'seconds' => 60,
        ];
    }

    public function trash(Request $request)
    {
        abort_unless($request->user()?->hasRole('super'), 403);

        $name    = $request->get('name', '');
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        $products = Product::onlyTrashed()
            ->with('deleter:id,name,email')
            ->when($name !== '', fn ($q) => $q->where('name', 'like', "%{$name}%"))
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Products/Trash', [
            'products' => $products,
            'filters'   => [
                'name'     => $name,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function restore(Request $request, $slug, ProductService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super'), 403);
        $model = Product::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $service->restore($model);

        return redirect()
            ->route('business_management.products.trash')
            ->with('success', __('global.restored_success'));
    }

    /**
     * Edit All — pagina con tabla editable in-line de name + is_active.
     * El submit hace batch update en transaccion (editAllUpdate).
     */
    public function editAll(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'asc']);
        }

        $products = Product::query()
            ->filter($request)
            ->select('products.id', 'products.slug', 'products.name',
                'products.is_active')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Products/EditAll', [
            'products' => $products,
            'filters'   => [
                'name'      => $request->get('name', ''),
                'is_active' => $request->has('is_active') && $request->is_active !== ''
                    ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'sort'      => $request->get('sort', 'id'),
                'direction' => $request->get('direction', 'asc'),
                'per_page'  => $perPage,
            ],
        ]);
    }

    public function editAllUpdate(EditAllUpdateProductRequest $request, ProductService $service): RedirectResponse
    {
        $touched = $service->editAllUpdate($request->validated()['changes']);

        return redirect()
            ->route('business_management.products.edit_all')
            ->with('success', __('global.updated_success') . " ({$touched})");
    }

    /**
     * Clona el product. Sufijo "(copia)" con sanity guard de 100 intentos.
     */
    public function duplicate(Request $request, Product $product, ProductService $service): RedirectResponse
    {
        $clone = $service->duplicate($product);

        if (!$clone) {
            return back()->with('error', __('global.duplicate_failed'));
        }

        return redirect()
            ->route('business_management.products.index')
            ->with('success', __('global.duplicated_success'));
    }

    public function bulkRestore(BulkRestoreProductRequest $request, ProductService $service): RedirectResponse
    {
        $result = $service->bulkRestore($request->validated()['ids']);

        if (!empty($result['queued'])) {
            return redirect()
                ->route('business_management.products.trash')
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return redirect()
            ->route('business_management.products.trash')
            ->with('success', __('global.restored_success') . " ({$result['restored']})");
    }

    public function forceDelete(ForceDeleteProductRequest $request, $slug, ProductService $service): RedirectResponse
    {
        $model = Product::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $data  = $request->validated();

        if (trim($data['name_confirmation']) !== $model->name) {
            return back()->withErrors(['name_confirmation' => __('global.force_delete_name_mismatch')]);
        }

        $service->forceDelete($model, $data['reason']);

        return redirect()
            ->route('business_management.products.trash')
            ->with('success', __('global.force_deleted_success'));
    }

    protected function payload(Product $m, bool $withAudit = false): array
    {
        $base = [
            'id'               => $m->id,
            'slug'             => $m->slug,
            'sku'              => $m->sku,
            'barcode'          => $m->barcode,
            'name'             => $m->name,
            'description'      => $m->description,
            'long_description' => $m->long_description,
            'category_id'      => $m->category_id,
            'category'         => $m->relationLoaded('category') && $m->category
                ? ['id' => $m->category->id, 'name' => $m->category->name]
                : null,
            'type'             => $m->type,
            'brand'            => $m->brand,
            'cost'             => $m->cost !== null ? (float) $m->cost : null,
            'final_cost'       => $m->final_cost !== null ? (float) $m->final_cost : null,
            'list_price'       => $m->list_price !== null ? (float) $m->list_price : null,
            'currency_code'    => $m->currency_code,
            'tax_class_id'     => $m->tax_class_id,
            'track_inventory'      => (bool) $m->track_inventory,
            'low_stock_threshold'  => $m->low_stock_threshold,
            'billing_cycle'    => $m->billing_cycle,
            'billing_period'   => $m->billing_period,
            'weight_kg'        => $m->weight_kg !== null ? (float) $m->weight_kg : null,
            'length_cm'        => $m->length_cm !== null ? (float) $m->length_cm : null,
            'width_cm'         => $m->width_cm  !== null ? (float) $m->width_cm  : null,
            'height_cm'        => $m->height_cm !== null ? (float) $m->height_cm : null,
            'image_url'        => $m->image_url,
            'external_id'      => $m->external_id,
            'is_active'        => $m->is_active,
            'is_favorite'      => (bool) ($m->is_favorite ?? false),
            'created_at'       => $m->created_at,
            'updated_at'       => $m->updated_at,
            'deleted_at'       => $m->deleted_at,
        ];
        if ($withAudit) {
            $base['deleted_description'] = $m->deleted_description;
            $base['creator'] = $m->creator ? ['id' => $m->creator->id, 'name' => $m->creator->name, 'email' => $m->creator->email] : null;
            $base['deleter'] = $m->deleter ? ['id' => $m->deleter->id, 'name' => $m->deleter->name, 'email' => $m->deleter->email] : null;
        }
        return $base;
    }

    // ── EXPORTS ─────────────────────────────────────────────────────────
    // Los 4 formatos van a queue como jobs async (mismo patron que Regions).
    // El job se encarga de la query con scope + render + Download record.

    public function exportCsv(Request $request)
    {
        return $this->dispatchExport($request, 'csv', GenerateProductsCsvJob::class);
    }

    public function exportExcel(Request $request)
    {
        return $this->dispatchExport($request, 'excel', GenerateProductsExcelJob::class);
    }

    public function exportPdf(Request $request)
    {
        return $this->dispatchExport($request, 'pdf', GenerateProductsPdfJob::class);
    }

    public function exportWord(Request $request)
    {
        return $this->dispatchExport($request, 'word', GenerateProductsWordJob::class);
    }

    /**
     * Helper comun de los 4 export endpoints: parse options → limit check →
     * audit → dispatch. Mismo patron que Region.
     */
    protected function dispatchExport(Request $request, string $format, string $jobClass): RedirectResponse
    {
        $options = $this->buildExportOptions($request, $format);
        $this->assertExportLimit($format, $options);
        $this->recordExportAudit($format, $options);
        $jobClass::dispatch(auth()->id(), $options);

        return back()->with('success', __('global.download_in_queue'));
    }

    /**
     * Valida que el dataset no exceda el limite del formato. Usuarios con
     * plan premium (feature flag `export_unlimited_rows`) saltean el limite.
     */
    protected function assertExportLimit(string $format, array $options): void
    {
        if (\App\Support\FeatureGate::allows('export_unlimited_rows', auth()->user())
            && config('features.features.export_unlimited_rows') !== null) {
            return;
        }

        $limit = \App\Models\Setting::getExportLimit('products', $format);
        if ($limit === 0) return; // CSV streaming, sin limite

        $count = $this->countForExport($options);
        if ($count > $limit) {
            abort(422, __('products.export_limit_exceeded', [
                'count'  => number_format($count),
                'limit'  => number_format($limit),
                'format' => strtoupper($format),
            ]));
        }
    }

    /** Cuenta filas a exportar segun scope+filters. */
    protected function countForExport(array $options): int
    {
        $scope = $options['scope'] ?? 'filtered';

        if ($scope === 'selected') {
            return count($options['selected_ids'] ?? []);
        }
        if ($scope === 'all') {
            return Product::query()->count();
        }
        // Filters como Request para reusar scopeFilter.
        $fakeReq = new Request($options['filters'] ?? []);
        return Product::query()->filter($fakeReq)->count();
    }

    // ── IMPORTS (two-phase: dry_run preview + commit) ────────────────────
    // El frontend sube 2 veces: primero dry_run=true (preview con summary),
    // despues dry_run=false (commit).

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BusinessManagement\Products\ProductsImportTemplate(),
            __('products.import_template_filename')
        );
    }

    public function import(ImportProductRequest $request)
    {
        $data    = $request->validated();
        $mode    = $data['mode'] ?? 'update_or_create';
        $dryRun  = filter_var($data['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $importer = new \App\Imports\BusinessManagement\Products\ProductsImport(
            mode:   $mode,
            dryRun: $dryRun,
        );

        try {
            \Maatwebsite\Excel\Facades\Excel::import($importer, $data['file']);
        } catch (\Throwable $e) {
            \Log::error('ProductsImport failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok'      => false,
                'dry_run' => $dryRun,
                'message' => $this->humanizeImportError($e),
            ], 422);
        }

        return response()->json([
            'ok'      => true,
            'dry_run' => $dryRun,
            'summary' => $importer->summary(),
        ], 200);
    }

    /**
     * Convierte una excepcion de import en mensaje legible para el usuario.
     * El detalle tecnico queda en el log, no llega al cliente.
     */
    protected function humanizeImportError(\Throwable $e): string
    {
        $msg = $e->getMessage();

        if ($e instanceof \Illuminate\Database\QueryException) {
            if (str_contains($msg, 'unique') || str_contains($msg, 'duplicate')) {
                return __('imports.err_unique_violation');
            }
            if (str_contains($msg, 'NOT NULL') || str_contains($msg, 'null value')) {
                return __('imports.err_not_null_violation');
            }
            if (str_contains($msg, 'foreign key') || str_contains($msg, 'violates foreign')) {
                return __('imports.err_foreign_key_violation');
            }
        }

        return __('imports.process_failed');
    }

    // ── BULK OPERATIONS ─────────────────────────────────────────────────
    public function bulkDelete(BulkDeleteProductRequest $request, ProductService $service): RedirectResponse
    {
        $data   = $request->validated();
        $result = $service->bulkDelete($data['ids'], $data['deleted_description']);

        if (!empty($result['queued'])) {
            // Async: el delete real ocurre despues del redirect; el undo
            // window de 60s no calza con un job que tarda minutos.
            return back()
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        $deletedIds = $result['deleted'];
        $this->storeUndoableDelete($deletedIds);

        return back()
            ->with('success', __('global.deleted_success') . ' (' . count($deletedIds) . ')')
            ->with('recentDelete', $this->buildRecentDeletePayload($deletedIds));
    }

    /**
     * Undo dentro del window de 60s. Validamos contra session claim:
     * quien borro puede deshacer su propio error sin permisos extra.
     * Defense in depth: el service solo restaura las filas con
     * deleted_by = current user.
     */
    public function undoLastDelete(Request $request, ProductService $service): RedirectResponse
    {
        $claim = session('products.recent_delete');
        if (!$claim || !is_array($claim) || empty($claim['ids']) || empty($claim['expires_at'])) {
            return back()->with('error', __('global.undo_failed'));
        }
        if (now()->isAfter($claim['expires_at'])) {
            session()->forget('products.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        $restored = $service->undoLastDelete($claim['ids'], (int) auth()->id());

        if (empty($restored)) {
            session()->forget('products.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        session()->forget('products.recent_delete');

        return back()->with('success', __('global.undo_done'));
    }

    public function bulkSetActive(BulkSetActiveProductRequest $request, ProductService $service): RedirectResponse
    {
        $data   = $request->validated();
        $result = $service->bulkSetActive($data['ids'], (bool) $data['is_active']);

        if (!empty($result['queued'])) {
            return back()->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return back()->with('success', __('global.updated_success') . " ({$result['changed']})");
    }

    // ── Export helpers ──────────────────────────────────────────────────

    /**
     * Opciones normalizadas que reciben todos los jobs de export. Allowlist
     * de columnas previene inyeccion de campos sensibles.
     */
    protected function buildExportOptions(Request $request, string $format): array
    {
        $allowedColumns = ['id', 'name',
            'is_active', 'slug', 'created_at', 'updated_at', 'creator'];

        $rules = [
            'scope'                   => 'nullable|in:filtered,selected,all',
            'selected_ids'            => 'array',
            'selected_ids.*'          => 'integer',
            'columns'                 => 'array|min:1',
            'columns.*'               => 'in:' . implode(',', $allowedColumns),
            'title'                   => 'nullable|string|max:120',
            'include_filters_summary' => 'boolean',
            'filters'                 => 'array',
        ];
        if ($format === 'pdf') {
            $rules['orientation'] = 'nullable|in:portrait,landscape';
            $rules['paper_size']  = 'nullable|in:a4,letter';
        }
        if ($format === 'excel') {
            $rules['autofilter']    = 'boolean';
            $rules['freeze_header'] = 'boolean';
        }

        $data = $request->validate($rules);

        return [
            'scope'                   => $data['scope']                   ?? 'filtered',
            'selected_ids'            => $data['selected_ids']            ?? [],
            'columns'                 => $data['columns']                 ?? $allowedColumns,
            'title'                   => $data['title']                   ?? __('products.export_title'),
            'include_filters_summary' => $data['include_filters_summary'] ?? true,
            'filters'                 => $data['filters']                 ?? [],
            'orientation'             => $data['orientation']             ?? 'portrait',
            'paper_size'              => $data['paper_size']              ?? 'a4',
            'autofilter'              => $data['autofilter']              ?? true,
            'freeze_header'           => $data['freeze_header']           ?? true,
        ];
    }

    /**
     * Escribe audit log manual del export. Event = 'export_queued' registra
     * la INTENCION del usuario; el estado final (ready/failed) vive en `downloads`.
     */
    protected function recordExportAudit(string $format, array $options): void
    {
        AuditLog::create([
            'user_id'        => auth()->id(),
            'event'          => 'export_queued',
            'auditable_type' => Product::class,
            'auditable_id'   => null,
            'module'         => 'products',
            'old_values'     => null,
            'new_values'     => [
                'format'                  => $format,
                'scope'                   => $options['scope']        ?? 'filtered',
                'columns'                 => $options['columns']      ?? [],
                'title'                   => $options['title']        ?? null,
                'orientation'             => $format === 'pdf'   ? ($options['orientation']    ?? null) : null,
                'paper_size'              => $format === 'pdf'   ? ($options['paper_size']     ?? null) : null,
                'autofilter'              => $format === 'excel' ? ($options['autofilter']     ?? null) : null,
                'freeze_header'           => $format === 'excel' ? ($options['freeze_header']  ?? null) : null,
                'include_filters_summary' => $options['include_filters_summary'] ?? false,
                'filters'                 => $options['filters']      ?? [],
                'selected_ids_count'      => count($options['selected_ids'] ?? []),
            ],
            'url'        => route('business_management.products.index'),
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
