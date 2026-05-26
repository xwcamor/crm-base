<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * TagController — CRUD minimo de Tags + attach/detach polimorfico.
 *
 * Endpoints:
 *   GET    /tags                          → JSON: lista tags del tenant (autocomplete)
 *   POST   /tags                          → crear nuevo tag
 *   POST   /tags/attach                   → attach tag a Company/Contact/Deal
 *   POST   /tags/detach                   → detach
 *
 * Allowlist polimorfico: solo Company, Contact, Deal. Si en el futuro se
 * quieren tags en Quote/Invoice/etc., agregar aqui sin tocar mas codigo.
 */
class TagController extends Controller
{
    protected const ALLOWED_TYPES = [
        'App\\Models\\Company' => Company::class,
        'App\\Models\\Contact' => Contact::class,
        'App\\Models\\Deal'    => Deal::class,
    ];

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        $query = Tag::query()->orderBy('name');
        if ($q !== '') {
            $isPg = \DB::connection()->getDriverName() === 'pgsql';
            $op   = $isPg ? 'ILIKE' : 'LIKE';
            $query->where('name', $op, '%' . $q . '%');
        }
        return response()->json([
            'tags' => $query->limit(50)->get(['id', 'name', 'color']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:16'],
        ]);

        // Idempotente por (tenant_id, name): si ya existe el tag, lo retorna.
        $tag = Tag::firstOrCreate(
            ['name' => $data['name']],   // tenant_id se auto-fill via BelongsToTenant
            [
                'slug'       => Str::random(22),
                'color'      => $data['color'] ?? '#888888',
                'created_by' => $request->user()?->id,
            ]
        );

        return response()->json(['tag' => $tag->only(['id', 'name', 'color'])]);
    }

    public function attach(Request $request): RedirectResponse
    {
        [$model, $tag] = $this->resolveTarget($request);
        $model->tags()->syncWithoutDetaching([$tag->id]);
        return back()->with('success', __('tags.attached'));
    }

    public function detach(Request $request): RedirectResponse
    {
        [$model, $tag] = $this->resolveTarget($request);
        $model->tags()->detach($tag->id);
        return back()->with('success', __('tags.detached'));
    }

    protected function resolveTarget(Request $request): array
    {
        $data = $request->validate([
            'taggable_type' => ['required', 'string'],
            'taggable_id'   => ['required', 'integer', 'min:1'],
            'tag_id'        => ['required', 'integer', 'exists:tags,id'],
        ]);

        $modelClass = self::ALLOWED_TYPES[$data['taggable_type']] ?? null;
        if (!$modelClass) {
            abort(422, 'Tipo de entidad no soportado para tags.');
        }
        $model = $modelClass::findOrFail($data['taggable_id']);
        $tag   = Tag::findOrFail($data['tag_id']);
        return [$model, $tag];
    }
}
