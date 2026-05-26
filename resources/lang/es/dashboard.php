<?php

return [
    'title'  => 'Dashboard',
    'hello'  => 'Hola, :name',
    'user'   => 'usuario',
    'role_super' => 'Panel de plataforma',
    'role_admin'       => 'Panel de administrador',
    'role_user'        => 'Panel de trabajo',

    // Widget labels (claves usadas como $t('dashboard.widget_' + key))
    'widget_tenants_active'  => 'Workspaces activos',
    'widget_subs_active'     => 'Suscripciones activas',
    'widget_subs_expiring'   => 'Por vencer',
    'widget_autos_runs_24h'  => 'Automatizaciones (24h)',
    'widget_users_count'     => 'Usuarios',
    'widget_automations'     => 'Automatizaciones',
    'widget_auto_failures'   => 'Fallas recientes',
    'widget_plan_days_left'  => 'Días en tu plan',

    // Bloques
    'expiring_soon'        => 'Suscripciones por vencer',
    'recent_automations'   => 'Automatizaciones recientes',
    'no_automations_yet'   => 'Todavía no hay ejecuciones de automatizaciones.',
    'days_left'            => ':n días',
    'records_processed'    => 'Registros procesados',

    // Vista simple para usuarios no-super: bienvenida + actividad reciente.
    'welcome_body'         => 'Bienvenido. Desde el menú lateral accedes a los módulos disponibles en tu plan. Abajo verás las últimas acciones que realizaste en el sistema.',
    'your_recent_activity' => 'Tu actividad reciente',
    'no_recent_activity'   => 'Aún no registras actividad. Cuando crees, edites o elimines un registro aparecerá aquí.',
];
