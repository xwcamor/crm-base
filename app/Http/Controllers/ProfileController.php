<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * ProfileController — "Mi perfil" del usuario logueado.
 *
 * Distinto del UserController de admin: acá el usuario solo edita SU propia
 * info, no la de otros. No requiere permiso especial — auth middleware basta.
 *
 * Campos editables por el usuario:
 *   - name (display name)
 *
 * Inmutables desde acá:
 *   - email (es la identidad — cambiarlo requiere flujo de verificación)
 *   - tenant_id, locale_id, country_id (admin-managed)
 *   - is_active, roles, permissions (admin-managed)
 */
class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $user->loadMissing(['country', 'tenant']);

        return inertia('Profile/Show', [
            'profile' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'photo'      => $user->photo,
                'photo_url'  => $user->photo_url,
                'is_active'  => $user->is_active,
                'tenant'     => $user->tenant ? ['id' => $user->tenant->id, 'name' => $user->tenant->name, 'timezone' => $user->tenant->timezone, 'default_currency_code' => $user->tenant->default_currency_code] : null,
                'country'    => $user->country ? ['id' => $user->country->id, 'name' => $user->country->name] : null,
                'roles'      => $user->getRoleNames(),
                'created_at' => $user->created_at?->toIso8601String(),
                'has_password' => !empty($user->password),
                // TZ propio (puede ser null si el user hereda del workspace o
                // país). El frontend lo distingue del TZ "efectivo" (siempre
                // un string) para mostrar la opción "heredar".
                'timezone'                => $user->timezone,
                'preferred_currency_code' => $user->preferred_currency_code,
            ],
            'currencyOptions' => \App\Models\Currency::query()
                ->where('is_active', true)->orderBy('code')
                ->get(['code', 'name', 'symbol'])
                ->map(fn ($c) => ['value' => $c->code, 'label' => $c->code . ' — ' . $c->symbol . ' ' . $c->name])
                ->all(),
        ]);
    }

    /**
     * Update name (and photo si se sumó). Email no se toca acá — mantiene la
     * identidad estable y el audit_log limpio.
     */
    public function update(Request $request)
    {
        // El TZ se valida contra la lista que Tz::availableTimezones()
        // construye (la misma que ofrece el dropdown). null = heredar del
        // workspace; cualquier string fuera de la lista = rechazo.
        $allowedTimezones = \App\Support\Tz::availableTimezones();

        $data = $request->validate([
            'name'                    => 'required|string|max:255',
            'timezone'                => ['nullable', 'string', 'in:' . implode(',', $allowedTimezones)],
            'preferred_currency_code' => ['nullable', 'string', 'size:3', 'exists:currencies,code'],
        ]);

        $user = $request->user();
        $user->name     = $data['name'];
        // `timezone` puede llegar como '' (campo vacio) o null — ambos => null
        // para que el user vuelva a heredar del workspace.
        $user->timezone                = $data['timezone'] ?: null;
        $user->preferred_currency_code = $data['preferred_currency_code'] ?: null;
        $user->save();

        // Limpiamos la cache de Tz para que la próxima request resuelva el
        // valor nuevo. Sin esto, la página siguiente seguiría mostrando el
        // TZ viejo hasta que el proceso PHP termine.
        \App\Support\Tz::forget($user->id);

        return back()->with('success', __('global.updated_success'));
    }

    /**
     * Cambio de password. Pide current + new + confirm. Si el usuario nunca
     * tuvo password (Google login), `current_password` no se requiere.
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $hasPassword = !empty($user->password);

        $data = $request->validate([
            'current_password' => $hasPassword ? 'required|string' : 'nullable',
            'password'         => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if ($hasPassword && !Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => __('profile.current_password_incorrect')]);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        $user->notify(new \App\Notifications\PasswordChangedNotification('self'));

        return back()->with('success', __('profile.password_updated'));
    }
}
