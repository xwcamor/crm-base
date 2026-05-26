<?php

return [
    // Page
    'title'              => 'Mi perfil',
    'subtitle'           => 'Administra tu información personal y configuración de cuenta.',

    // Tabs
    'tab_info'           => 'Información',
    'tab_password'       => 'Contraseña',
    'tab_preferences'    => 'Preferencias',

    // Info section
    'name'               => 'Nombre',
    'email'              => 'Correo electrónico',
    'email_readonly_hint' => 'El correo identifica tu cuenta y no se puede modificar desde aquí. Contacta al administrador si necesitas cambiarlo.',
    'tenant'             => 'Organización',
    'country'            => 'País',
    'timezone'           => 'Zona horaria',
    'timezone_hint'      => 'Deja la opción "Usar la del workspace" para heredar la zona del workspace o tu país.',
    'timezone_inherit'   => 'Usar la del workspace',
    'preferred_currency_code'        => 'Moneda preferida',
    'preferred_currency_placeholder' => 'Heredar del workspace',
    'preferred_currency_hint'        => 'Sobrescribe la moneda default del workspace para tu visualización personal. Dejá vacío para heredar del tenant.',
    'roles'              => 'Roles',
    'member_since'       => 'Miembro desde',
    'save_info'          => 'Guardar información',

    // Password section
    'password_title'     => 'Cambiar contraseña',
    'password_subtitle'  => 'Una contraseña fuerte tiene al menos 8 caracteres, letras y números.',
    'current_password'   => 'Contraseña actual',
    'new_password'       => 'Nueva contraseña',
    'confirm_password'   => 'Confirmar nueva contraseña',
    'change_password'    => 'Cambiar contraseña',
    'no_password_hint'   => 'Aún no tienes una contraseña local (iniciaste con Google). Define una para poder iniciar sesión también con email.',
    'password_updated'   => 'Contraseña actualizada correctamente.',
    'current_password_incorrect' => 'La contraseña actual no es correcta.',

    // Preferences section
    'preferences_title'  => 'Preferencias de la app',
    'preferences_hint'   => 'Estas preferencias también se pueden cambiar desde la barra superior.',
    'preferred_language' => 'Idioma preferido',
    'preferred_theme'    => 'Tema',
    'tour_status'        => 'Tours de onboarding',
    'tours_completed'    => ':count tour completado|{1} 1 tour completado|[2,*] :count tours completados',
    'reset_tours'        => 'Reiniciar tours',
    'reset_tours_done'   => 'Tours reiniciados. Vas a verlos otra vez la próxima vez que entres a cada módulo.',
];
