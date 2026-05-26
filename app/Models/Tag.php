<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Tag — etiqueta reusable per-tenant.
 *
 * Se asocia a Company/Contact/Deal/etc. via tabla polimorfica `taggables`.
 * Cada tenant tiene su propio set de tags (unique por tenant_id + name).
 *
 * Uso desde un modelo entityable:
 *   $company->tags()->attach($tagId);     // agregar
 *   $company->tags()->detach($tagId);     // quitar
 *   $company->tags()->sync([1, 5, 7]);    // setear exactamente esos
 *
 * Componente Vue TagPicker.vue maneja toda la UX en Show pages.
 */
class Tag extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'slug', 'name', 'color', 'description',
        'tenant_id', 'created_by', 'deleted_by', 'deleted_description',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                do {
                    $slug = Str::random(22);
                } while (static::withTrashed()->where('slug', $slug)->exists());
                $model->slug = $slug;
            }
        });
    }
}
