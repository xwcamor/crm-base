<?php

return [
    'singular' => 'Conteo físico',
    'plural'   => 'Conteos físicos',
    'record'   => 'conteo',
    'records'  => 'conteos',
    'new'      => 'Nuevo conteo',
    'id'       => 'N°',

    'index_title'      => 'Conteos físicos',
    'index_subtitle'   => 'Inventarios y conteos físicos por almacén. Al completar se generan ajustes automáticos de stock.',
    'create_title'     => 'Nuevo conteo físico',
    'create_subtitle'  => 'Inventario por almacén — se generan líneas desde el stock actual.',
    'edit_title'       => 'Editar conteo físico',
    'delete_title'     => 'Eliminar conteo físico',
    'show_title'       => 'Conteo — Detalle',
    'trash_title'      => 'Papelera de conteos',
    'form_create_hint' => 'Completa los datos para iniciar un nuevo conteo físico.',
    'empty_hint'       => 'No hay conteos físicos aún. Crea el primero para inventariar un almacén.',

    'reference'        => 'Referencia',
    'reference_hint'   => 'Código interno del conteo. Si lo dejas vacío, el sistema sugiere el siguiente correlativo.',
    'filter_reference' => 'Referencia',
    'warehouse'        => 'Almacén',
    'warehouse_hint'   => 'Almacén sobre el que se realiza el conteo físico. No se puede cambiar después de crear.',
    'status'           => 'Estado',
    'status_hint'      => 'Etapa del conteo: borrador, en curso, completado o cancelado. Al completar se generan los ajustes.',
    'started_at'       => 'Iniciado',
    'completed_at'     => 'Completado',
    'completed_by'     => 'Completado por',
    'note'             => 'Nota / objetivo',
    'note_hint'        => 'Motivo del conteo (auditoría, cierre mensual, inventario anual, etc.).',

    'status_options' => [
        'draft'       => 'Borrador',
        'in_progress' => 'En curso',
        'completed'   => 'Completado',
        'cancelled'   => 'Cancelado',
    ],

    'lines_title'           => 'Líneas del conteo',
    'lines_empty'           => 'El conteo no tiene productos asociados.',
    'line_product'          => 'Producto',
    'line_sku'              => 'SKU',
    'line_qty_system'       => 'Sistema',
    'line_qty_counted'      => 'Contado',
    'line_variance'         => 'Variación',
    'line_note'             => 'Nota',

    'created'                  => 'Conteo iniciado con :count productos.',
    'saved'                    => 'Conteo actualizado.',
    'deleted'                  => 'Conteo eliminado.',
    'cannot_delete_completed'  => 'No se puede eliminar un conteo completado (ya generó ajustes de stock).',
    'adjustment_note'          => 'Ajuste por conteo físico (variación: :variance)',

    'edit_hint'    => 'Modificar este conteo',
    'delete_hint'  => 'Eliminar (queda en papelera)',
    'restore_hint' => 'Volverá a estar disponible en el listado principal.',

    'delete_about' => 'Vas a eliminar ":name". Quedará en papelera.',
    'deleted_description_required' => 'Indica el motivo del borrado.',
    'deleted_description_min'      => 'El motivo debe tener al menos 3 caracteres.',
    'deleted_description_max'      => 'El motivo no puede superar los 1000 caracteres.',

    // Validation
    'reference_unique'   => 'Ya existe un conteo con esta referencia en el workspace.',
    'status_required'    => 'El estado es obligatorio.',
    'warehouse_required' => 'El almacén es obligatorio.',

    // Bulk
    'bulk_set_status' => 'Cambiar estado',

    // Export
    'export_filename'           => 'exportacion_conteos_fisicos',
    'import_template_filename'  => 'plantilla-conteos-fisicos.xlsx',
    'export_title'              => 'Reporte de Conteos Físicos',
    'export_limit_exceeded'     => 'El export en :format excede el límite (:count filas vs :limit máximo). Usa CSV para datasets grandes.',
    'export_format_limit_hint'  => 'Máximo :limit filas para este formato. Usa CSV para datasets grandes.',
    'export_no_limit_hint'      => 'Sin límite — recomendado para datasets grandes.',

    // Import
    'import_warehouse_required'  => 'El código del almacén es obligatorio.',
    'import_warehouse_not_found' => 'No se encontró ningún almacén con ese código.',
    'import_invalid_status'      => 'Estado del conteo inválido.',

    // Edit All
    'edit_all_title'      => 'Conteos — Editar Todo',
    'edit_all_subtitle'   => 'Edita referencia y estado de muchos conteos a la vez. Haz clic en "Guardar todo" para confirmar.',
    'edit_all_changes'    => '{0} Sin cambios|{1} 1 cambio pendiente|[2,*] :count cambios pendientes',
    'edit_all_save_all'   => 'Guardar todo',
    'edit_all_discard'    => 'Descartar cambios',
    'edit_all_no_results' => 'No hay conteos que coincidan con el filtro.',

    'tour' => [
        'step1_title' => 'Bienvenido a Conteos Físicos',
        'step1_body'  => 'Inventarios por almacén con ajustes automáticos. Tour rápido en 1 minuto.',
        'step2_title' => 'Filtros',
        'step2_body'  => 'Busca por referencia, estado o almacén.',
        'step3_title' => 'Vistas guardadas',
        'step3_body'  => 'Guarda tu combinación favorita de filtros + columnas + orden y aplícala después con un clic.',
        'step4_title' => 'Columnas',
        'step4_body'  => 'Muestra/oculta columnas y se recuerda tu elección.',
        'step5_title' => 'Exportar & Importar',
        'step5_body'  => 'Exporta a Excel/PDF/Word/CSV en segundo plano. Importa conteos desde Excel/CSV con vista previa.',
        'step6_title' => 'Editar muchos a la vez',
        'step6_body'  => '"Editar todo" permite cambiar referencia y estado de varios conteos juntos.',
        'step7_title' => 'Favoritos *',
        'step7_body'  => 'La estrella * marca un conteo como favorito. Aparecen siempre arriba.',
        'step8_title' => 'Operaciones masivas',
        'step8_body'  => 'Selecciona filas con los checkboxes y cambia estado o elimina en lote.',
    ],
];
