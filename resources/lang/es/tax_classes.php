<?php

return [
    'singular'      => 'Clase de impuesto',
    'plural'        => 'Clases de impuesto',
    'record'        => 'clase de impuesto',
    'records'       => 'clases de impuesto',
    'new'           => 'Crear clase',
    'id'            => 'N°',

    'index_title'    => 'Clases de impuesto',
    'index_subtitle' => 'IVA / IGV configurables por workspace.',
    'create_title'   => 'Crear clase de impuesto',
    'create_subtitle'=> 'Define una nueva clase impositiva.',
    'edit_title'     => 'Editar clase de impuesto',
    'delete_title'   => 'Eliminar clase de impuesto',
    'show_title'     => 'Clase de impuesto — Información',
    'trash_title'    => 'Papelera de clases de impuesto',
    'form_create_hint' => 'Completa los datos para crear una nueva clase.',
    'empty_hint'     => 'Crea la primera clase de impuesto.',
    'name_placeholder' => 'Ej: IVA 21% Standard',
    'code_placeholder' => 'Ej: IVA-21',

    'name'        => 'Nombre',
    'name_hint'   => 'Nombre descriptivo de la clase de impuesto. Aparece en selects de productos, cotizaciones y facturas.',
    'code'        => 'Código',
    'code_hint'   => 'Código corto único (ej: IVA-21, IGV-18). Útil para exports a contabilidad y reportes fiscales.',
    'description' => 'Descripción',
    'description_hint' => 'Notas internas: marco legal aplicable, vigencia, excepciones.',
    'is_default'  => 'Predeterminado',
    'is_default_hint' => 'Si está activo, esta clase se aplica por defecto a los productos nuevos sin tasa específica.',
    'is_active'   => 'Estado',
    'is_active_hint' => 'Si está inactivo, la clase no aparece en selects de productos ni de líneas de factura.',
    'filter_name' => 'Nombre',

    'edit_hint'   => 'Modificar este registro',
    'delete_hint' => 'Eliminar (queda en papelera)',
    'restore_hint'=> 'Volverá a estar disponible en el listado principal.',

    'created' => 'Clase creada.',
    'saved'   => 'Clase actualizada.',
    'deleted' => 'Clase eliminada.',

    'delete_about'                 => 'Vas a eliminar ":name". Quedará en papelera.',
    'deleted_description_required' => 'Indica el motivo del borrado.',
    'deleted_description_min'      => 'El motivo debe tener al menos 3 caracteres.',
    'deleted_description_max'      => 'El motivo no puede superar los 1000 caracteres.',

    // Export
    'export_filename'           => 'exportacion_clases_impuesto',
    'import_template_filename'  => 'plantilla-clases-impuesto.xlsx',
    'export_title'              => 'Reporte de Clases de Impuesto',
    'export_limit_exceeded'     => 'El export en :format excede el límite (:count filas vs :limit máximo). Usa CSV para datasets grandes (sin límite).',
    'export_format_limit_hint'  => 'Máximo :limit filas para este formato. Usa CSV para datasets grandes.',
    'export_no_limit_hint'      => 'Sin límite — recomendado para datasets grandes.',

    // Validation
    'name_required'            => 'El nombre es obligatorio.',
    'name_unique'              => 'Ya existe una clase con ese nombre.',
    'name_duplicate_in_batch'  => 'Nombre duplicado dentro del mismo batch.',
    'is_active_required'       => 'El campo estado es obligatorio.',

    // Edit All
    'edit_all_title'    => 'Clases de impuesto — Editar Todo',
    'edit_all_subtitle' => 'Edita nombre y estado de muchas clases a la vez. Click "Guardar todo" para confirmar.',
    'edit_all_changes'  => '{0} Sin cambios|{1} 1 cambio pendiente|[2,*] :count cambios pendientes',
    'edit_all_save_all' => 'Guardar todo',
    'edit_all_discard'  => 'Descartar cambios',
    'edit_all_no_results' => 'No hay clases que coincidan con el filtro.',

    'table_headers' => [
        'editable_name'   => 'Nombre (editable)',
        'editable_status' => 'Estado (editable)',
    ],

    // Onboarding tour
    'tour' => [
        'step1_title' => 'Bienvenido a Clases de Impuesto',
        'step1_body'  => 'Configura las clases impositivas (IVA, IGV, exentos) que usarán cotizaciones, facturas y órdenes. Tour rápido en 1 minuto.',
        'step2_title' => 'Filtros',
        'step2_body'  => 'Busca y filtra por nombre, código, estado. Los filtros activos aparecen como chips arriba de la tabla.',
        'step3_title' => 'Vistas guardadas',
        'step3_body'  => 'Guarda tu combinación favorita de filtros + columnas + orden y reúsala con un clic.',
        'step4_title' => 'Columnas',
        'step4_body'  => 'Muestra/oculta columnas y se recuerda tu elección.',
        'step5_title' => 'Exportar & Importar',
        'step5_body'  => 'Exporta a Excel/PDF/Word en segundo plano. Importa desde Excel/CSV con vista previa.',
        'step6_title' => 'Editar muchos a la vez',
        'step6_body'  => '"Editar todo" permite modificar nombre y estado en lote.',
        'step7_title' => 'Favoritos',
        'step7_body'  => 'La estrella marca un registro como favorito. Los favoritos aparecen siempre arriba.',
        'step8_title' => 'Operaciones masivas',
        'step8_body'  => 'Selecciona filas con los checkboxes para activar, desactivar, eliminar o restaurar en lote.',
        'step9_title' => '¿Necesitas un repaso?',
        'step9_body'  => 'Reabre este tour cuando quieras con el botón ? aquí arriba.',
    ],
];
