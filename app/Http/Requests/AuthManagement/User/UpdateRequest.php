<?php

// Namespace
namespace App\Http\Requests\AuthManagement\User;

// Use Illuminates
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Main class
class UpdateRequest extends FormRequest
{
    // Authorize
    public function authorize(): bool
    {
        // Allow request
        return true;
    }

    // Solo super puede mover un usuario a otro tenant. Para el resto de actores
    // preservamos el tenant_id actual del usuario editado e ignoramos cualquier
    // valor que venga en el payload (defensa contra escalacion).
    protected function prepareForValidation(): void
    {
        $actor  = $this->user();
        $target = $this->route('user');

        if ($actor && $target && !$actor->hasRole('super')) {
            $this->merge(['tenant_id' => $target->tenant_id]);
        }
    }

    // Rules
    public function rules(): array
    {
        // Capture model for Route Model Binding
        $user = $this->route('user');

        // Limite de tamaño KB del setting `uploads.user_photo_max_mb`.
        $maxKb = \App\Models\Setting::getInt('uploads.user_photo_max_mb', 2) * 1024;

        // Validations
        return [
            'name'       => 'required|string|max:255',
            'email'      => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password'   => 'nullable|string|min:6',
            'photo'      => 'nullable|image|mimes:jpg,jpeg,png,gif|max:' . $maxKb,
            'country_id' => 'required|integer|exists:countries,id',
            'locale_id'  => 'required|integer|exists:locales,id',
            'tenant_id'  => 'nullable|integer|exists:tenants,id',
            'is_active'  => 'nullable|boolean',
            'role_id'    => 'nullable|integer|exists:roles,id',
        ];
    }

    // Messages
    public function messages(): array
    {
        // Validation Messages
        return [
            'name.max'           => 'El nombre debe tener como máximo 255 caracteres.',
            'email.unique'       => 'El correo electrónico ya existe.',
            'password.min'       => 'La contraseña debe tener al menos 6 caracteres.',
            'photo.image'        => 'El archivo debe ser una imagen válida.',
            'photo.mimes'        => 'La imagen debe ser de tipo: jpg, jpeg, png o gif.',
            'photo.max'          => 'La imagen no debe superar los 2 MB.',
            'country_id.required'=> 'El país es obligatorio.',
            'country_id.exists'  => 'El país seleccionado no es válido.',
            'locale_id.required' => 'El idioma es obligatorio.',
            'locale_id.exists'   => 'El idioma seleccionado no es válido.',
            'tenant_id.exists'   => 'El workspace seleccionado no es válido.',
            'role_id.exists'     => 'El perfil seleccionado no es válido.',
        ];
    }

}