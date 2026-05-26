<?php

return [
    'singular'      => 'Fuente de lead',
    'plural'        => 'Fuentes de leads',
    'record'        => 'fuente',
    'records'       => 'fuentes',
    'new'           => 'Crear fuente',
    'id'            => 'N°',

    'index_title'    => 'Fuentes de leads',
    'index_subtitle' => 'De dónde vienen los prospectos: web, referido, evento, etc.',
    'create_title'   => 'Crear fuente',
    'create_subtitle'=> 'Completa los datos para crear una nueva fuente.',
    'edit_title'     => 'Editar fuente',
    'delete_title'   => 'Eliminar fuente',
    'show_title'     => 'Fuente — Información',
    'trash_title'    => 'Papelera de fuentes',
    'form_create_hint' => 'Completa los datos para crear una nueva fuente.',
    'empty_hint'     => 'No hay fuentes — crea la primera.',
    'name_placeholder' => 'Ej: Formulario web',

    'name'        => 'Nombre',
    'name_hint'   => 'Nombre descriptivo de la fuente. Aparece en reportes y en el detalle del deal.',
    'description' => 'Descripción',
    'description_hint' => 'Notas internas sobre la fuente.',
    'category'    => 'Categoría',
    'category_hint' => 'Clasificación libre. Ej: organic, paid, referral, event.',
    'category_placeholder' => 'Ej: organic',
    'sort_order'  => 'Orden',
    'sort_order_hint' => 'Orden numérico para listar las fuentes. Menor primero.',
    'is_active'   => 'Estado',
    'is_active_hint' => 'Si está inactiva, no podrá asignarse a nuevos deals.',
    'filter_name' => 'Nombre',

    'edit_hint'   => 'Modificar este registro',
    'delete_hint' => 'Eliminar (queda en papelera)',
    'restore_hint'=> 'Volverá a estar disponible en el listado principal.',

    'created' => 'Fuente creada.',
    'saved'   => 'Fuente actualizada.',
    'deleted' => 'Fuente eliminada.',

    'delete_about'                 => 'Vas a eliminar ":name". Quedará en papelera.',
    'deleted_description_required' => 'Indica el motivo del borrado.',
    'deleted_description_min'      => 'El motivo debe tener al menos 3 caracteres.',
    'deleted_description_max'      => 'El motivo no puede superar los 1000 caracteres.',

    // Export
    'export_filename'           => 'exportacion_fuentes',
    'import_template_filename'  => 'plantilla-fuentes.xlsx',
    'export_title'              => 'Reporte de Fuentes de Leads',
    'export_limit_exceeded'     => 'El export en :format excede el límite (:count filas vs :limit máximo). Usa CSV para datasets grandes (sin límite).',
    'export_format_limit_hint'  => 'Máximo :limit filas para este formato. Usa CSV para datasets grandes.',
    'export_no_limit_hint'      => 'Sin límite — recomendado para datasets grandes.',

    // Validation
    'name_required'            => 'El nombre es obligatorio.',
    'name_unique'              => 'Ya existe una fuente con ese nombre.',
    'name_duplicate_in_batch'  => 'Nombre duplicado dentro del mismo batch.',
    'is_active_required'       => 'El campo estado es obligatorio.',

    // Edit All
    'edit_all_title'    => 'Fuentes — Editar Todo',
    'edit_all_subtitle' => 'Edita nombre y estado de muchas fuentes a la vez. Haz clic en "Guardar todo" para confirmar.',
    'edit_all_changes'  => '{0} Sin cambios|{1} 1 cambio pendiente|[2,*] :count cambios pendientes',
    'edit_all_save_all' => 'Guardar todo',
    'edit_all_discard'  => 'Descartar cambios',
    'edit_all_no_results' => 'No hay fuentes que coincidan con el filtro.',

    'table_headers' => [
        'editable_name'   => 'Nombre (editable)',
        'editable_status' => 'Estado (editable)',
    ],

    // Onboarding tour
    'tour' => [
        'step1_title' => 'Bienvenido a Fuentes',
        'step1_body'  => 'Este es tu módulo de fuentes de leads. Te mostramos los puntos clave en menos de 1 minuto.',
        'step2_title' => 'Filtros',
        'step2_body'  => 'Busca y filtra por nombre, categoría, estado. Los filtros activos aparecen como chips arriba de la tabla.',
        'step3_title' => 'Vistas guardadas',
        'step3_body'  => 'Guarda tu combinación favorita de filtros + columnas + orden y aplícala después con un clic.',
        'step4_title' => 'Columnas',
        'step4_body'  => 'Muestra u oculta columnas; tu elección se recuerda. Las marcadas como obligatorias no se pueden ocultar.',
        'step5_title' => 'Exportar e importar',
        'step5_body'  => 'Exporta a Excel, PDF o Word en segundo plano. Importa desde Excel o CSV con vista previa antes de confirmar.',
        'step6_title' => 'Editar muchos a la vez',
        'step6_body'  => '"Editar todo" permite modificar nombre y estado de varias fuentes juntas.',
        'step7_title' => 'Favoritos',
        'step7_body'  => 'La estrella marca una fuente como favorita. Los favoritos aparecen siempre arriba.',
        'step8_title' => 'Operaciones masivas',
        'step8_body'  => 'Selecciona filas con los checkboxes — aparece una barra para activar, desactivar, eliminar o restaurar en lote.',
        'step9_title' => '¿Necesitas un repaso?',
        'step9_body'  => 'Reabre este tour cuando quieras con el botón ? aquí arriba.',
    ],
];
