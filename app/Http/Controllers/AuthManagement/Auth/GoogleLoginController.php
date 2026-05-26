<?php

namespace App\Http\Controllers\AuthManagement\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;

class GoogleLoginController extends Controller
{
    public function redirectToGoogle()
    {
        session(['locale' => app()->getLocale()]);
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $locale = $request->segment(1); // ←  'en', 'es', etc.
            app()->setLocale($locale);
            \LaravelLocalization::setLocale($locale);

            $googleUser = Socialite::driver('google')->user();

            // 1. Buscar por google_id
            $user = User::where('google_id', $googleUser->id)->first();

            if ($user) {
                if (!$user->is_active) {
                    return redirect('/login')->with('error', __('auth.account_inactive'));
                }

                Auth::login($user);
                return redirect(\LaravelLocalization::getLocalizedURL($locale, route('user_management.users.index', [], false)));
            }

            // 2. Buscar por email
            $user = User::where('email', $googleUser->email)->first();

            if ($user) {
                $user->update(['google_id' => $googleUser->id]);

                if (!$user->is_active) {
                    return redirect('/login')->with('error', __('auth.account_inactive'));
                }

                Auth::login($user);
                return redirect(\LaravelLocalization::getLocalizedURL($locale, route('user_management.users.index', [], false)));
            }

            // 3. Cuenta no registrada: NO auto-creamos para evitar que
            // cualquier cuenta Google entre a un tenant arbitrario. El admin
            // del tenant debe crear el usuario primero; en el proximo login
            // por Google el flujo (paso 2) vincula el google_id.
            return redirect('/login')->with('error', __('auth.account_not_registered'));

        } catch (\Exception $e) {
            return redirect('/login')->with('error', __('auth.login_error'));
        }
        

    }
}