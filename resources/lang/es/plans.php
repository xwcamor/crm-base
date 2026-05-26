<?php

return [
    'modal_title'      => 'Comparación de planes',
    'modal_intro'      => 'Cada workspace tiene un plan que define cuántos usuarios puede tener, cuántos registros puede crear y qué funcionalidades están disponibles.',
    'modal_footnote'   => 'Para cambiar el plan de un workspace, entra a su pantalla de detalle → tab Suscripción.',

    'view_plans'       => 'Ver planes',
    'view_plans_hint'  => 'Qué incluye cada plan',

    // Feature column
    'feature'            => 'Funcionalidad',
    'no_public_plans'    => 'No hay planes públicos disponibles.',
    'days_remaining_short' => ':n días restantes',

    // Feature rows
    'feature_users'         => 'Usuarios',
    'feature_records'       => 'Registros por módulo',
    'feature_csv'           => 'Exportar CSV',
    'feature_excel'         => 'Exportar Excel',
    'feature_pdf'           => 'Exportar PDF',
    'feature_word'          => 'Exportar Word',
    'feature_api'           => 'API REST + tokens',
    'feature_audit'         => 'Historial de auditoría',
    'feature_bulk'          => 'Operaciones masivas',
    'feature_saved_views'   => 'Vistas guardadas',
    'feature_branding'      => 'Branding personalizado',
    'feature_scheduled'     => 'Exports programados',
    'feature_webhooks'      => 'Webhooks',
    'feature_retention'     => 'Retención de exports',
    'feature_export_rate'   => 'Límite de exports',
    'feature_support'       => 'Soporte',

    'support_community' => 'Comunidad',
    'support_email'     => 'Email',
    'support_priority'  => 'Prioritario',

    // Limit warnings (cuando se acerca al límite)
    'limit_users_reached'   => 'Alcanzaste el máximo de usuarios para tu plan (:max). Sube a pro para agregar más.',
    'limit_records_reached' => 'Alcanzaste el máximo de :max registros para este módulo en tu plan. Sube de plan para crear más.',
    'feature_locked'        => 'Esta funcionalidad requiere un plan superior.',

    // ─── Admin UI (super gestiona planes) ──────────────────────
    'index_title'    => 'Planes',
    'index_subtitle' => 'Pricing tiers del sistema. Edita límites, features y precios sin redeploy.',
    'index_footnote' => 'Cambios surten efecto inmediato para todos los workspaces. No puedes borrar un plan con workspaces asignados.',
    'edit_title'     => 'Editar plan: :name',
    'edit_subtitle'  => 'Cambios aplican inmediatamente a todos los workspaces de este plan.',
    'edit_info'      => 'Tip: usa -1 en los límites para "ilimitado". Cualquier cambio se refleja en tiempo real.',
    'edit_hint'      => 'Modificar límites, features y precio de este plan',
    'saved'          => 'Plan actualizado.',

    'create'              => 'Nuevo plan',
    'create_title'        => 'Crear plan',
    'create_subtitle'     => 'Define un nuevo tier de pricing. El slug es único e inmutable después.',
    'create_info'         => 'Tip: el slug se usa internamente (tenants.plan). No lo podrás cambiar después.',
    'create_hint'         => 'Crear un nuevo tier de pricing',
    'created'             => 'Plan creado.',
    'slug_placeholder'    => 'Ej: pro_plus',
    'slug_create_hint'    => 'Minúsculas, números y guion bajo. Identificador único.',
    'slug_help' => 'El slug es el identificador interno del plan, referenciado en codigo (tenants.plan, subscriptions.plan). Por eso es inmutable despues de crearlo: cambiarlo dejaria tenants apuntando a un plan inexistente. Si necesitas renombrar, se hace por migracion.',

    'deleted'                 => 'Plan eliminado.',
    'delete_confirm_title'    => '¿Eliminar este plan?',
    'delete_confirm_desc'     => 'El plan ":name" se borrará. Quedará en Papelera y solo super puede restaurarlo o eliminarlo permanentemente.',
    'delete_blocked_title'    => 'No se puede eliminar',
    'delete_blocked'          => 'Hay :count workspace(s) usando este plan. Migra esos workspaces a otro plan primero.',
    'delete_blocked_hint'     => 'No puedes borrar este plan mientras tenga workspaces asignados.',
    'delete_hint'             => 'Eliminar este plan (queda en Papelera).',
    'delete_about'            => 'Vas a eliminar el plan ":name". Quedará en Papelera por si necesitas recuperarlo.',
    'restore_hint'            => 'Restaurar este plan a la lista activa.',

    // Singular/plural para títulos
    'singular' => 'Plan',
    'plural'   => 'Planes',
    'record'   => 'plan',
    'records'  => 'planes',

    // Validación delete
    'deleted_description_required' => 'Especifica el motivo del borrado.',
    'deleted_description_min'      => 'El motivo debe tener al menos 3 caracteres.',
    'deleted_description_max'      => 'El motivo no puede superar los 1000 caracteres.',

    // Columnas del Index
    'col_plan'          => 'Plan',
    'col_users'         => 'Usuarios',
    'col_records'       => 'Registros',
    'col_api'           => 'API',
    'col_price_monthly' => 'Mensual',
    'col_price_yearly'  => 'Anual',
    'col_status'        => 'Estado',

    // Form sections
    'section_identity'   => 'Identidad',
    'section_limits'     => 'Límites numéricos',
    'section_pricing'    => 'Precios',
    'section_features'   => 'Funcionalidades',
    'section_visibility' => 'Visibilidad',

    // Form fields
    'slug'                   => 'Slug (no editable)',
    'slug_locked_hint'       => 'Referenciado en código (tenants.plan, subscriptions.plan).',
    'name'                   => 'Nombre',
    'name_hint'              => 'Nombre visible del plan; aparece en la página de pricing y selectores.',
    'tagline'                => 'Subtítulo',
    'tagline_hint'           => 'Frase corta que describe el plan en una línea.',
    'icon'                   => 'Icono',
    'icon_hint'              => 'Icono que se muestra junto al nombre del plan.',
    'icon_placeholder'       => 'Sin icono',
    'color'                  => 'Color',
    'color_hint'             => 'Color del tag con el que se renderiza este plan en la UI.',
    'preview'                => 'Vista previa',
    'preview_placeholder'    => 'Plan',
    'max_users'              => 'Máx. usuarios',
    'max_records_per_module' => 'Máx. registros por módulo',
    'export_rate_limit'      => 'Rate limit export (/min)',
    'unlimited_hint'         => 'Usa -1 para ilimitado.',
    'export_rate_hint'       => 'Cuántos exports por minuto puede ejecutar.',
    'limits_hint'            => 'Define los topes numéricos del plan. -1 desactiva el límite.',
    'support_level'          => 'Nivel de soporte',
    'support_level_hint'     => 'Nivel de soporte incluido en este plan.',
    'price_monthly'          => 'Precio mensual',
    'price_monthly_hint'     => 'Precio facturado cada mes en la moneda elegida.',
    'price_yearly'           => 'Precio anual',
    'price_yearly_hint'      => 'Precio facturado una vez al año; suele ofrecer descuento sobre el mensual.',
    'currency'               => 'Moneda (ISO 3)',
    'currency_hint'          => 'Código ISO 4217 de 3 letras (USD, EUR, PEN).',
    'is_active'              => 'Activo',
    'is_active_hint'         => 'Si está inactivo, no se puede asignar a nuevas suscripciones.',
    'is_public'              => 'Público',
    'is_public_hint'         => 'Si está oculto, no aparece en el modal "Ver planes".',
    'features_hint'          => 'Toggle cada feature. Los cambios afectan a todos los tenants en este plan.',

    // Feature groups
    'group_exports'    => 'Exports',
    'group_visibility' => 'Visibilidad de datos',
    'group_team'       => 'Operaciones de equipo',
    'group_advanced'   => 'Features avanzadas',
    'group_quality'    => 'Calidad de servicio',
    'group_other'      => 'Otras funcionalidades',

    // Show page + tabs
    'tab_tenants'             => 'Workspaces',
    'tenant_name'             => 'Nombre',
    'tenants_count_hint'      => 'Cuántos workspaces están en este plan hoy',
    'no_tenants_in_plan'      => 'Ningún workspace usa este plan todavía.',
    'no_activity'             => 'Sin cambios registrados aún.',
    'export_excel_hint'       => 'Descargar listado de planes en Excel',
    'export_pdf_hint'         => 'Descargar listado de planes en PDF',
    'deactivate_warning_title'=> 'Vas a desactivar un plan con workspaces activos',
    'deactivate_warning_desc' => 'Hay :count workspace(s) usando este plan. ¿Continuar?',

    // Feature labels individuales (en el form admin)
    'feature_exportCsv'               => 'Exportar CSV',
    'feature_exportExcel'             => 'Exportar Excel',
    'feature_exportPdf'               => 'Exportar PDF',
    'feature_exportWord'              => 'Exportar Word',
    'feature_auditLogView'            => 'Ver historial de auditoría',
    'feature_savedViews'              => 'Vistas guardadas',
    'feature_bulkOperations'          => 'Operaciones masivas (bulk)',
    'feature_imports'                 => 'Importar registros desde archivo',
    'feature_editAll'                 => 'Editar todo (edición masiva inline)',
    'feature_teamManagement'          => 'Equipos de trabajo (crear usuarios y perfiles)',
    'feature_apiAccess'               => 'Acceso a REST API + tokens',
    'feature_brandedExports'          => 'Exports con logo del workspace',
    'feature_scheduledExports'        => 'Exports programados (cron)',
    'feature_exportWebhookDelivery'   => 'Webhook delivery al terminar export',
    'feature_exportEmailDelivery'     => 'Email delivery al terminar export',
    'feature_extendedRetention'       => 'Retención extendida (30d vs 7d)',
    'feature_higherExportRateLimit'   => 'Rate limit alto en exports',
    'feature_automations'             => 'Automatizaciones',

    // ─── Tier 1 parity (Index Tier 1, exports, imports, bulk) ──────────────
    'id'              => 'N°',
    'sort_order'      => 'Orden',
    'filter_name'     => 'Nombre o slug',
    'empty_hint'      => 'Aún no hay planes — crea el primero.',

    // Tabla — headers editables (edit-all)
    'table_headers' => [
        'editable_name'   => 'Nombre (editable)',
        'editable_status' => 'Estado (editable)',
    ],

    // Validation mensajes
    'is_active_required'       => 'El campo estado es obligatorio.',

    // Exports / Imports
    'export_filename'          => 'exportacion_planes',
    'export_title'             => 'Reporte de Planes',
    'export_limit_exceeded'    => 'El export en :format excede el límite (:count filas vs :limit máximo). Usa CSV para datasets grandes (sin límite).',
    'import_template_filename' => 'plantilla-planes.xlsx',

    // Import errors específicos (los genéricos viven en imports.php)
    'err_slug_required'  => 'El slug es obligatorio.',
    'err_slug_invalid'   => 'El slug debe contener minúsculas, números y guion bajo (empezando por letra).',
    'err_slug_too_long'  => 'El slug supera los 60 caracteres.',
    'err_name_required'  => 'El nombre es obligatorio.',
    'err_name_too_long'  => 'El nombre supera los 100 caracteres.',
    'err_invalid_support'=> 'El nivel de soporte no es válido.',

    // Edit All
    'edit_all_title'      => 'Planes — Editar Todo',
    'edit_all_subtitle'   => 'Edita nombre y estado de muchos planes a la vez. Haz click en "Guardar todo" para confirmar, "Cancelar" para descartar.',
    'edit_all_changes'    => '{0} Sin cambios|{1} 1 cambio pendiente|[2,*] :count cambios pendientes',
    'edit_all_save_all'   => 'Guardar todo',
    'edit_all_discard'    => 'Descartar cambios',
    'edit_all_no_results' => 'No hay planes que coincidan con el filtro.',
    'edit_all_duplicates' => 'Hay nombres duplicados en el batch — resuélvelos antes de guardar.',

    // Onboarding tour
    'tour' => [
        'step1_title' => 'Bienvenido a Planes',
        'step1_body'  => 'Este es el módulo de tiers de pricing. Te mostramos los puntos clave en menos de 1 minuto.',
        'step2_title' => 'Filtros',
        'step2_body'  => 'Busca y filtra por nombre, slug, nivel de soporte, estado. Los filtros activos aparecen como chips arriba de la tabla.',
        'step3_title' => 'Vistas guardadas',
        'step3_body'  => 'Guarda tu combinación favorita de filtros + columnas + orden y aplícala después con un clic.',
        'step4_title' => 'Columnas',
        'step4_body'  => 'Muestra u oculta columnas y se recuerda tu elección. Las marcadas como "obligatorias" no se pueden ocultar.',
        'step5_title' => 'Exportar e Importar',
        'step5_body'  => 'Exporta a Excel/PDF/Word en segundo plano. Importa desde Excel/CSV con vista previa antes de confirmar.',
        'step6_title' => 'Editar muchos a la vez',
        'step6_body'  => '"Editar todo" permite modificar nombre y estado de varios planes juntos.',
        'step7_title' => 'Favoritos',
        'step7_body'  => 'La estrella marca un plan como favorito. Los favoritos aparecen siempre arriba.',
        'step8_title' => 'Operaciones masivas',
        'step8_body'  => 'Selecciona filas con los checkboxes — aparece una barra para activar, desactivar, eliminar o restaurar en lote.',
        'step9_title' => '¿Necesitas un repaso?',
        'step9_body'  => 'Reabre este tour cuando quieras con el botón ? aquí arriba.',
    ],
];
