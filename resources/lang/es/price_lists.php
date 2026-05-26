<?php

return [
    'singular'      => 'Lista de precios',
    'plural'        => 'Listas de precios',
    'record'        => 'lista de precios',
    'records'       => 'listas de precios',
    'new'           => 'Crear lista',
    'id'            => 'N°',

    'index_title'    => 'Listas de precios',
    'index_subtitle' => 'Precios diferenciados por segmento: retail, wholesale, enterprise, partners.',
    'create_title'   => 'Crear lista de precios',
    'create_subtitle'=> 'Completa los datos para crear una nueva lista de precios.',
    'edit_title'     => 'Editar lista de precios',
    'delete_title'   => 'Eliminar lista de precios',
    'show_title'     => 'Lista de precios — Informacion',
    'trash_title'    => 'Papelera de listas de precios',
    'form_create_hint' => 'Completa los datos para crear una nueva lista de precios.',
    'empty_hint'     => 'No hay listas de precios — crea la primera.',
    'name_placeholder' => 'Ej: Wholesale 25% off',

    'name'        => 'Nombre',
    'name_hint'   => 'Nombre descriptivo de la lista (ej: Wholesale 25% off, Lista Corporativa). Aparece al asignarla a clientes y en reportes.',
    'description' => 'Descripcion',
    'description_hint' => 'Notas internas sobre el segmento de clientes objetivo y las condiciones de la lista.',
    'currency'    => 'Moneda',
    'currency_hint' => 'Moneda en la que se expresan los precios de esta lista. Define con que clientes se puede usar.',
    'global_discount_pct' => 'Descuento global %',
    'global_discount_pct_hint' => 'Porcentaje aplicado a todos los productos de la lista. Se combina con los precios especificos por producto.',
    'priority'    => 'Prioridad',
    'priority_hint' => 'Define que lista gana cuando un cliente califica para varias. Mayor numero = mayor prioridad.',
    'valid_from'  => 'Valida desde',
    'valid_from_hint' => 'Fecha y hora desde la que la lista se activa. Vacio = activa de inmediato.',
    'valid_until' => 'Valida hasta',
    'valid_until_hint' => 'Fecha y hora de expiracion. Vacio = sin fecha de fin.',
    'is_default'  => 'Default del tenant',
    'is_default_hint' => 'Si esta activo, esta lista se aplica a los clientes sin lista asignada. Solo una puede ser default por workspace.',
    'is_active'   => 'Estado',
    'is_active_hint' => 'Si esta inactiva, la lista no se aplica aunque el cliente la tenga asignada.',
    'filter_name' => 'Nombre',

    'edit_hint'   => 'Modificar este registro',
    'delete_hint' => 'Eliminar (queda en papelera)',
    'restore_hint'=> 'Volvera a estar disponible en el listado principal.',

    'created' => 'Lista de precios creada.',
    'saved'   => 'Lista de precios actualizada.',
    'deleted' => 'Lista de precios eliminada.',

    'delete_about'                 => 'Vas a eliminar ":name". Quedara en papelera.',
    'deleted_description_required' => 'Indica el motivo del borrado.',
    'deleted_description_min'      => 'El motivo debe tener al menos 3 caracteres.',
    'deleted_description_max'      => 'El motivo no puede superar los 1000 caracteres.',

    // Export
    'export_filename'           => 'exportacion_listas_precios',
    'import_template_filename'  => 'plantilla-listas-precios.xlsx',
    'export_title'              => 'Reporte de Listas de Precios',
    'export_limit_exceeded'     => 'El export en :format excede el limite (:count filas vs :limit maximo). Usa CSV para datasets grandes (sin limite).',
    'export_format_limit_hint'  => 'Maximo :limit filas para este formato. Usa CSV para datasets grandes.',
    'export_no_limit_hint'      => 'Sin limite — recomendado para datasets grandes.',

    // Validation
    'name_required'            => 'El nombre es obligatorio.',
    'name_unique'              => 'Ya existe una lista de precios con ese nombre en este workspace.',
    'name_duplicate_in_batch'  => 'Nombre duplicado dentro del mismo batch.',
    'is_active_required'       => 'El campo estado es obligatorio.',

    // Edit All
    'edit_all_title'    => 'Listas de precios — Editar Todo',
    'edit_all_subtitle' => 'Edita nombre y estado de muchas listas a la vez. Haz click en "Guardar todo" para confirmar, "Cancelar" para descartar.',
    'edit_all_changes'  => '{0} Sin cambios|{1} 1 cambio pendiente|[2,*] :count cambios pendientes',
    'edit_all_save_all' => 'Guardar todo',
    'edit_all_discard'  => 'Descartar cambios',
    'edit_all_no_results' => 'No hay listas de precios que coincidan con el filtro.',

    'table_headers' => [
        'editable_name'   => 'Nombre (editable)',
        'editable_status' => 'Estado (editable)',
    ],

    // Onboarding tour
    'tour' => [
        'step1_title' => 'Bienvenido a Listas de precios',
        'step1_body'  => 'Este es tu modulo de listas de precios diferenciadas por segmento. Te mostramos los puntos clave en menos de 1 minuto.',
        'step2_title' => 'Filtros',
        'step2_body'  => 'Busca y filtra por nombre, moneda, estado. Los filtros activos aparecen como chips arriba de la tabla.',
        'step3_title' => 'Vistas guardadas',
        'step3_body'  => 'Guarda tu combinacion favorita de filtros + columnas + orden y aplicala despues con un clic.',
        'step4_title' => 'Columnas',
        'step4_body'  => 'Muestra/oculta columnas y se recuerda tu eleccion. Las marcadas como "obligatorias" no se pueden ocultar.',
        'step5_title' => 'Exportar e Importar',
        'step5_body'  => 'Exporta a Excel/PDF/Word en segundo plano. Importa desde Excel/CSV con vista previa antes de confirmar.',
        'step6_title' => 'Editar muchas a la vez',
        'step6_body'  => '"Editar todo" permite modificar nombre y estado de varias listas juntas.',
        'step7_title' => 'Favoritos',
        'step7_body'  => 'La estrella marca una lista como favorita. Los favoritos aparecen siempre arriba.',
        'step8_title' => 'Operaciones masivas',
        'step8_body'  => 'Selecciona filas con los checkboxes — aparece una barra para activar, desactivar, eliminar o restaurar en lote.',
        'step9_title' => '¿Necesitas un repaso?',
        'step9_body'  => 'Reabre este tour cuando quieras con el boton ? aqui arriba.',
    ],
];
