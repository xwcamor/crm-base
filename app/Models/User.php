<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

// App
use App\Notifications\ResetPasswordNotification;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use App\Traits\HasFavorites;
use App\Scopes\HideSuperScope;

class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens, SoftDeletes, Auditable, BelongsToTenant, HasFavorites;

    // photo_url se appendea cuando el modelo se serializa (toArray/toJson).
    // Asi cualquier vista que recibe un User como prop tiene la URL completa
    // con cache-busting, sin que cada controller la deba pedir manualmente.
    protected $appends = ['photo_url'];

    // Friendly module label for audit logs (defaults would be `users` anyway).
    protected string $auditModule = 'users';

    // Campos excluidos del audit log. `module_tours` es estado UX privado
    // (qué tours vio el user) — no es relevante para compliance/security review.
    protected array $auditExclude = [
        'password', 'remember_token', 'updated_at',
        'module_tours',
    ];


    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'country_id',
        'locale_id',
        'timezone',
        'preferred_currency_code',
        'email',
        'google_id',
        'password',
        'name',
        'photo',
        'is_active',
        'module_tours',
        'created_by',
        'deleted_by',
        'deleted_description',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'module_tours'      => 'array',
        ];
    }

    // Boot — register global scopes + slug auto-generation
    protected static function booted()
    {
        // Ghost super: tenant admins/users never see the system creator.
        static::addGlobalScope(new HideSuperScope);

        // Auto-generate a unique slug on create (was buggy — used Country::).
        static::creating(function ($user) {
            if (empty($user->slug)) {
                do {
                    $slug = Str::random(22);
                } while (User::withoutGlobalScopes()->where('slug', $slug)->exists());
                $user->slug = $slug;
            }
        });
    }

    // Use slug for route model binding
    public function getRouteKeyName()
    {
        return 'slug';
    }

    // tenant() relationship is provided by BelongsToTenant trait — see trait file.

    public function country()
    {
        return $this->belongsTo(Country::class);
    }    

    /**
     * Relationships for audit.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
    
    // Accessor: return HTML state (active/inactive).
    public function getStateHtmlAttribute()
    {
        return $this->is_active
            ? '<span class="text-success">' . __('global.active') . '</span>'
            : '<span class="text-danger">' . __('global.inactive') . '</span>';
    }

    // Accessor: return plain text state (active/inactive).
    public function getStateTextAttribute()
    {
        return $this->is_active
            ? __('global.active')
            : __('global.inactive');
    }     
   
    // Devuelve URL absoluta de la foto con cache-busting via timestamp, o
    // null si el usuario no tiene foto. El componente UserAvatar del frontend
    // dibuja la inicial del nombre cuando recibe null.
    //
    // El `?v={updated_at}` invalida el cache del browser cuando se sube una
    // foto nueva (mismo path con timestamp distinto = nuevo fetch).
    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo) return null;
        if (Str::startsWith($this->photo, ['http://', 'https://'])) return $this->photo;

        $ts = $this->updated_at?->timestamp ?? time();
        return asset('storage/' . $this->photo) . '?v=' . $ts;
    }

    /**
     * Locale preferido del usuario para notifications/emails. Laravel lo
     * consulta automaticamente porque User implementa HasLocalePreference.
     *
     * Se deriva del `locale_id` (FK a locales) → tomamos el iso_code del
     * language asociado (es / en / pt). Si el user no tiene locale_id,
     * fallback al locale default del app.
     */
    public function preferredLocale(): string
    {
        if ($this->locale_id) {
            $iso = \App\Models\Locale::query()
                ->join('languages', 'languages.id', '=', 'locales.language_id')
                ->where('locales.id', $this->locale_id)
                ->value('languages.iso_code');
            if ($iso) return $iso;
        }
        return config('app.locale', 'es');
    }

    /**
     * Override del default de Laravel para usar nuestra ResetPasswordNotification
     * con las traducciones del proyecto.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}