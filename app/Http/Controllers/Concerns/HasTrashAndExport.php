<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Trait reusable para agregar a cada Controller las features faltantes vs Customer:
 *
 *  - trash()         → listado de soft-deleted (super only)
 *  - restore()       → restaurar uno
 *  - bulkRestore()   → restaurar varios
 *  - forceDelete()   → eliminar permanentemente (super only)
 *  - exportCsv()     → streaming CSV de los filtrados
 *
 * Uso:
 *
 *   class FooController extends Controller {
 *       use HasTrashAndExport;
 *
 *       protected string $trashModel = \App\Models\Foo::class;
 *       protected string $trashRoute = 'business_management.foos';
 *       protected string $trashView  = 'Foos/Trash';
 *       protected array  $exportColumns = ['id','name','code','is_active'];
 *       protected string $exportFilename = 'foos_export';
 *   }
 */
trait HasTrashAndExport
{
    public function trash(Request $request)
    {
        abort_unless($request->user()?->hasRole('super'), 403);

        $name    = $request->get('name', '');
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        $items = $this->trashModel::onlyTrashed()
            ->with('deleter:id,name,email')
            ->when($name !== '' && $this->modelHasColumn('name'),
                fn ($q) => $q->where('name', 'like', "%{$name}%"))
            ->when($name !== '' && !$this->modelHasColumn('name') && $this->modelHasColumn('reference'),
                fn ($q) => $q->where('reference', 'like', "%{$name}%"))
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia($this->trashView, [
            'items'   => $items,
            'filters' => ['name' => $name, 'per_page' => $perPage],
            'meta'    => $this->trashMeta(),
        ]);
    }

    public function restore(Request $request, $slug)
    {
        abort_unless($request->user()?->hasRole('super'), 403);
        $model = $this->trashModel::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $model->restore();
        $clear = [];
        if ($this->modelHasColumn('deleted_by'))          $clear['deleted_by'] = null;
        if ($this->modelHasColumn('deleted_description')) $clear['deleted_description'] = null;
        if (!empty($clear)) $model->update($clear);
        return redirect()->route($this->trashRoute . '.trash')->with('success', 'Registro restaurado.');
    }

    public function bulkRestore(Request $request)
    {
        abort_unless($request->user()?->hasRole('super'), 403);
        $ids = $request->validate(['ids' => ['required','array','min:1'], 'ids.*' => ['integer']])['ids'];
        $n = $this->trashModel::onlyTrashed()->whereIn('id', $ids)->get()
            ->each(fn ($m) => $m->restore())->count();
        return redirect()->route($this->trashRoute . '.trash')->with('success', "{$n} registros restaurados.");
    }

    public function forceDelete(Request $request, $slug)
    {
        abort_unless($request->user()?->hasRole('super'), 403);
        $request->validate(['name_confirmation' => ['required', 'string']]);
        $model = $this->trashModel::onlyTrashed()->where('slug', $slug)->firstOrFail();

        // Acepta name OR reference OR number
        $expected = $model->name ?? $model->reference ?? $model->number ?? '';
        if (trim($request->name_confirmation) !== $expected) {
            return back()->withErrors(['name_confirmation' => 'El texto de confirmación no coincide con "' . $expected . '".']);
        }
        $model->forceDelete();
        return redirect()->route($this->trashRoute . '.trash')->with('success', 'Eliminado permanentemente.');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $cols     = $this->exportColumns ?? ['id', 'created_at'];
        $filename = ($this->exportFilename ?? 'export') . '_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $query = $this->trashModel::query();
        // Aplicar mismo scope que index (sin pagination)
        if (method_exists($this->trashModel, 'scopeFilter')) {
            $query->filter($request);
        }

        return response()->stream(function () use ($query, $cols) {
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF"); // BOM UTF-8 (Excel)
            fputcsv($out, $cols);
            $query->chunk(500, function ($rows) use ($out, $cols) {
                foreach ($rows as $row) {
                    $line = [];
                    foreach ($cols as $c) {
                        $val = data_get($row, $c);
                        if (is_bool($val)) $val = $val ? '1' : '0';
                        elseif (is_array($val) || is_object($val)) $val = json_encode($val, JSON_UNESCAPED_UNICODE);
                        $line[] = $val;
                    }
                    fputcsv($out, $line);
                }
            });
            fclose($out);
        }, 200, $headers);
    }

    protected function modelHasColumn(string $column): bool
    {
        $instance = new $this->trashModel;
        return \Schema::hasColumn($instance->getTable(), $column);
    }

    protected function trashMeta(): array
    {
        $instance = new $this->trashModel;
        return [
            'route_prefix' => $this->trashRoute,
            'singular'     => class_basename($this->trashModel),
            'display_col'  => $this->modelHasColumn('name') ? 'name'
                            : ($this->modelHasColumn('reference') ? 'reference'
                            : ($this->modelHasColumn('number') ? 'number' : 'id')),
        ];
    }
}
