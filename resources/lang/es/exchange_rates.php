<?php

return [
    'singular'      => 'Tipo de cambio',
    'plural'        => 'Tipos de cambio',
    'record'        => 'tipo de cambio',
    'records'       => 'tipos de cambio',
    'new'           => 'Nueva tasa',
    'id'            => 'N°',

    'index_title'    => 'Tipos de cambio',
    'index_subtitle' => 'Historial FX entre pares de monedas. Append-only en uso: cada actualización agrega una fila.',
    'create_title'   => 'Nueva tasa de cambio',
    'create_subtitle'=> 'Registra una tasa en el historial.',
    'edit_title'     => 'Editar tasa de cambio',
    'delete_title'   => 'Eliminar tasa de cambio',
    'show_title'     => 'Tasa de cambio — Información',
    'trash_title'    => 'Papelera de tasas',
    'form_create_hint' => 'Completa los datos para registrar una nueva tasa.',
    'empty_hint'     => 'No hay tasas de cambio — crea la primera.',

    'base_code'      => 'Moneda base',
    'base_code_hint' => 'Moneda de origen de la conversión (la que tienes).',
    'quote_code'     => 'Moneda quote',
    'quote_code_hint'=> 'Moneda de destino de la conversión (a la que conviertes).',
    'rate'           => 'Tasa',
    'rate_hint'      => 'Cuántas unidades de la moneda quote equivalen a 1 unidad de la base. Admite hasta 6 decimales.',
    'valid_at'       => 'Válida desde',
    'valid_at_hint'  => 'Fecha y hora desde la que se aplica esta tasa. La más reciente vigente es la que se usa.',
    'source'         => 'Fuente',
    'source_hint'    => 'Origen del dato: manual, fixer.io, BCRA, openexchangerates u otro proveedor.',
    'is_active'      => 'Estado',
    'is_active_hint' => 'Si está inactiva, la tasa no se aplica aunque sea la más reciente.',
    'filter_base'    => 'Moneda base',
    'filter_quote'   => 'Moneda quote',

    'pair'           => 'Par',
    'pair_label'     => ':base / :quote',
    'pair_display'   => ':base/:quote @ :date',

    'edit_hint'   => 'Modificar esta tasa',
    'delete_hint' => 'Eliminar (queda en papelera)',
    'restore_hint'=> 'Volverá a estar disponible en el listado principal.',

    'created' => 'Tasa registrada.',
    'saved'   => 'Tasa actualizada.',
    'deleted' => 'Tasa eliminada.',

    'delete_about'                 => 'Vas a eliminar ":display". Quedará en papelera.',
    'deleted_description_required' => 'Indica el motivo del borrado.',
    'deleted_description_min'      => 'El motivo debe tener al menos 3 caracteres.',
    'deleted_description_max'      => 'El motivo no puede superar los 1000 caracteres.',

    // Export
    'export_filename'           => 'exportacion_tasas',
    'import_template_filename'  => 'plantilla-tasas.xlsx',
    'export_title'              => 'Reporte de tasas de cambio',
    'export_limit_exceeded'     => 'El export en :format excede el límite (:count filas vs :limit máximo). Usa CSV para datasets grandes (sin límite).',
    'export_format_limit_hint'  => 'Máximo :limit filas para este formato. Usa CSV para datasets grandes.',
    'export_no_limit_hint'      => 'Sin límite — recomendado para datasets grandes.',

    // Validation
    'base_code_required'       => 'La moneda base es obligatoria.',
    'quote_code_required'      => 'La moneda quote es obligatoria.',
    'rate_required'            => 'La tasa es obligatoria.',
    'valid_at_required'        => 'La fecha desde es obligatoria.',
    'pair_valid_unique'        => 'Ya existe una tasa para ese par en esa fecha y hora.',
    'is_active_required'       => 'El campo estado es obligatorio.',

    // Force delete
    'force_delete_display_mismatch' => 'La confirmación no coincide. Copia exacta: ":expected".',
    'force_delete_display_prompt'   => 'Para confirmar, escribe exactamente: :display',

    // Edit All
    'edit_all_title'    => 'Tasas — Editar Todo',
    'edit_all_subtitle' => 'Edita tasa y estado de muchas filas a la vez. Click "Guardar todo" para confirmar, "Cancelar" para descartar.',
    'edit_all_changes'  => '{0} Sin cambios|{1} 1 cambio pendiente|[2,*] :count cambios pendientes',
    'edit_all_save_all' => 'Guardar todo',
    'edit_all_discard'  => 'Descartar cambios',
    'edit_all_no_results' => 'No hay tasas que coincidan con el filtro.',

    'table_headers' => [
        'editable_rate'   => 'Tasa (editable)',
        'editable_status' => 'Estado (editable)',
    ],

    // Onboarding tour
    'tour' => [
        'step1_title' => 'Bienvenido a Tipos de cambio',
        'step1_body'  => 'Este es tu módulo de tasas FX. Te mostramos los puntos clave en menos de 1 minuto.',
        'step2_title' => 'Filtros',
        'step2_body'  => 'Busca y filtra por moneda base, quote, fuente y estado. Los filtros activos aparecen como chips arriba de la tabla.',
        'step3_title' => 'Vistas guardadas',
        'step3_body'  => 'Guarda tu combinación favorita de filtros + columnas + orden y aplícala después con un clic.',
        'step4_title' => 'Columnas',
        'step4_body'  => 'Muestra/oculta columnas y se recuerda tu elección. Las marcadas como obligatorias no se pueden ocultar.',
        'step5_title' => 'Exportar e Importar',
        'step5_body'  => 'Exporta a Excel/PDF/Word en segundo plano. Importa desde Excel/CSV con vista previa antes de confirmar.',
        'step7_title' => 'Favoritos',
        'step7_body'  => 'La estrella marca una tasa como favorita. Los favoritos aparecen siempre arriba.',
        'step8_title' => 'Operaciones masivas',
        'step8_body'  => 'Selecciona filas con los checkboxes — aparece una barra para activar, desactivar, eliminar o restaurar en lote.',
    ],
];
