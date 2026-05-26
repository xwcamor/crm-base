<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Industry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Catálogos con CRUD per-tenant (excepto industries: super-only global).
 */
class CatalogController extends Controller
{
    // ─── Lead Sources: MIGRADO a Tier 1 ────────────────────────────────────
    // Vive en LeadSourceController (clon del patron Discount/ProductCategory).
    // Las rutas estan en routes/business_management.php bajo el bloque
    // "Lead Sources".

    // ─── Payment Methods: MIGRADO a Tier 1 ─────────────────────────────────
    // Vive en PaymentMethodController (clon del patron Discount/ProductCategory).
    // Las rutas estan en routes/business_management.php bajo el bloque
    // "Payment Methods".

    // ─── Product Categories: MIGRADO a Tier 1 ──────────────────────────────
    // Vive en ProductCategoryController (clon del patron Discount). Las rutas
    // estan en routes/business_management.php bajo el bloque "Product Categories".

    // ─── Product Variants: MIGRADO a Tier 1 ────────────────────────────────
    // Vive en ProductVariantController (clon del patron Discount/ProductCategory).
    // Las rutas estan en routes/business_management.php bajo el bloque
    // "Product Variants".

    // ─── Industries (global — super only) ─────────────────────────────────
    public function industries(Request $request)
    {
        $items = Industry::query()->with('parent:id,name')
            ->when($request->filled('name'), fn ($q) => $q->where('name', 'like', '%' . $request->name . '%'))
            ->orderBy('name')->paginate(100)->withQueryString();
        $parentOptions = Industry::orderBy('name')->get(['id','name'])
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all();
        return inertia('Catalogs/Industries', [
            'items' => $items,
            'filters' => ['name' => $request->get('name', '')],
            'parentOptions' => $parentOptions,
        ]);
    }
    public function industryStore(Request $request)
    {
        $data = $this->industryRules($request);
        Industry::create(array_merge($data, ['slug' => Str::random(22), 'created_by' => $request->user()?->id]));
        return back()->with('success', 'Industria creada.');
    }
    public function industryUpdate(Request $request, Industry $industry)
    {
        $industry->update($this->industryRules($request));
        return back()->with('success', 'Industria actualizada.');
    }
    public function industryDestroy(Industry $industry)
    {
        $industry->delete();
        return back()->with('success', 'Industria eliminada.');
    }
    protected function industryRules(Request $r): array
    {
        return $r->validate([
            'name' => ['required','string','max:150'],
            'parent_id' => ['nullable','integer','exists:industries,id'],
            'is_active' => ['sometimes','boolean'],
        ]);
    }
}
