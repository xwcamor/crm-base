<?php

// Namespace
namespace App\Http\Requests\AuthManagement\User;

// Use Illuminates
use Illuminate\Foundation\Http\FormRequest;

// Main class
class StoreRequest extends FormRequest
{
    // Authorize
    public function authorize(): bool
    {
      // Allow request
      return true;
    }

    // Solo super puede definir el tenant del nuevo usuario. Para cualquier
    // otro actor forzamos el tenant del propio actor; esto bloquea que un
    // admin de tenant cree usuarios en otros tenants pasando tenant_id por POST.
    protected function prepareForValidation(): void
    {
        $actor = $this->user();
        if ($actor && !$actor->hasRole('super')) {
            $this->merge(['tenant_id' => $actor->tenant_id]);
        }
    }

    // Rules
    public function rules(): array
    {
      // Limite de tamaño en KB para la foto — viene del setting
      // `uploads.user_photo_max_mb` (editable desde la UI). Default 2 MB.
      $maxKb = \App\Models\Setting::getInt('uploads.user_photo_max_mb', 2) * 1024;

      // Validations
      return [
        'name'       => 'required|string|max:255',
        'email'      => 'required|email|unique:users,email',
        'password'   => 'required|string|min:6',
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
        'email.unique'       => 'El email ya existe.',
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