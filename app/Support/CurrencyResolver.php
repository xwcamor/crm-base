<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;

/**
 * Resolver de moneda según cascada de inheritance:
 *
 *   1. Override explícito (parámetro)
 *   2. Company.preferred_currency_code (si el doc tiene cliente)
 *   3. User.preferred_currency_code (si el user logueado tiene preferencia)
 *   4. Tenant.default_currency_code (workspace del user)
 *   5. 'USD' (fallback duro — usado por super sin tenant)
 *
 * Usado por todos los formSelectOptions() de los controllers que devuelven
 * `defaultCurrencyCode` al frontend, y por servicios que setean currency
 * antes de persistir un Quote/Invoice/Order/etc.
 */
class CurrencyResolver
{
    public const FALLBACK = 'USD';

    /**
     * Resuelve la moneda default para el contexto actual.
     *
     * @param Company|null $company Si se conoce el cliente del documento.
     * @param User|null    $user    El usuario logueado (default: auth()->user()).
     */
    public static function resolve(?Company $company = null, ?User $user = null): string
    {
        $user ??= auth()->user();

        // 2. Cliente del documento
        if ($company && !empty($company->preferred_currency_code)) {
            return $company->preferred_currency_code;
        }

        // 3. Preferencia explícita del usuario
        if ($user && !empty($user->preferred_currency_code)) {
            return $user->preferred_currency_code;
        }

        // 4. Default del workspace
        if ($user && $user->tenant && !empty($user->tenant->default_currency_code)) {
            return $user->tenant->default_currency_code;
        }

        // 5. Fallback duro (super sin tenant)
        return self::FALLBACK;
    }

    /**
     * Helper conveniencia para formSelectOptions(). Equivale a resolve()
     * sin context de company — el form usa esto para pre-seleccionar moneda
     * antes de que el usuario elija cliente.
     */
    public static function forCurrentUser(): string
    {
        return self::resolve(null, auth()->user());
    }
}
