<?php

return [
    'singular'      => 'Cliente',
    'plural'        => 'Clientes',
    'record'        => 'cliente',
    'records'       => 'clientes',
    'new'           => 'Crear cliente',
    'id'            => 'N°',

    'index_title'    => 'Clientes',
    'index_subtitle' => 'Gestiona los clientes del workspace.',
    'create_title'   => 'Crear cliente',
    'create_subtitle'=> 'Completa los datos para crear un nuevo registro.',
    'edit_title'     => 'Editar cliente',
    'delete_title'   => 'Eliminar cliente',
    'show_title'     => 'Cliente — Información',
    'trash_title'    => 'Papelera de clientes',
    'form_create_hint' => 'Completa los datos para crear un nuevo cliente.',
    'empty_hint'      => 'Crea el primer cliente o importa un lote desde Excel para empezar.',
    'name_placeholder' => 'Ej: Empresa S.A.',

    'name'      => 'Nombre',
    'name_hint' => 'Nombre del cliente como aparece en facturas y documentos. Único por workspace.',
    // @scaffold:anchor description-lang
    // @scaffold:remove-begin commercial-labels
    'cod'       => 'Código',
    'cod_hint'  => 'RUC, RFC, CUIT, NIT o identificador interno. Único por workspace.',
    'cod_placeholder' => 'Ej: 20123456789',
    'country'           => 'País',
    'country_hint'      => 'País de residencia fiscal del cliente. Se usa para impuestos y envíos.',
    'country_placeholder' => 'Selecciona un país',
    // @scaffold:remove-end
    'is_active' => 'Estado',
    'is_active_hint' => 'Si está inactivo, no aparece en selectores al crear nuevos registros (pero los existentes siguen funcionando).',
    'filter_name' => 'Nombre',

    'edit_hint'   => 'Modificar este registro',
    'delete_hint' => 'Eliminar (queda en papelera)',
    'restore_hint'=> 'Volverá a estar disponible en el listado principal.',

    'created' => 'Registro creado.',
    'saved'   => 'Registro actualizado.',
    'deleted' => 'Registro eliminado.',

    'delete_about'                 => 'Vas a eliminar ":name". Quedará en papelera.',
    'deleted_description_required' => 'Indica el motivo del borrado.',
    'deleted_description_min'      => 'El motivo debe tener al menos 3 caracteres.',
    'deleted_description_max'      => 'El motivo no puede superar los 1000 caracteres.',

    // Export
    'export_filename'           => 'exportacion_clientes',
    'import_template_filename'  => 'plantilla-clientes.xlsx',
    'export_title'              => 'Reporte de Clientes',
    'export_limit_exceeded'     => 'El export en :format excede el límite (:count filas vs :limit máximo). Usa CSV para datasets grandes (sin límite).',
    'export_format_limit_hint'  => 'Máximo :limit filas para este formato. Usa CSV para datasets grandes.',
    'export_no_limit_hint'      => 'Sin límite — recomendado para datasets grandes.',

    // Validation
    'name_required'            => 'El campo nombre es obligatorio.',
    'name_unique'              => 'Este cliente ya existe.',
    'name_duplicate_in_batch'  => 'Nombre duplicado dentro del mismo batch.',
    'is_active_required'       => 'El campo estado es obligatorio.',

    // Edit All
    'edit_all_title'    => 'Cliente — Editar Todo',
    'edit_all_subtitle' => 'Edita nombre y estado de muchos clientes a la vez. Click "Guardar todo" para confirmar, "Cancelar" para descartar.',
    'edit_all_changes'  => '{0} Sin cambios|{1} 1 cambio pendiente|[2,*] :count cambios pendientes',
    'edit_all_save_all' => 'Guardar todo',
    'edit_all_discard'  => 'Descartar cambios',
    'edit_all_no_results' => 'No hay clientes que coincidan con el filtro.',

    'table_headers' => [
        'editable_name'   => 'Nombre (editable)',
        'editable_status' => 'Estado (editable)',
    ],

    // Onboarding tour
    'tour' => [
        'step1_title' => 'Bienvenido a Clientes',
        'step1_body'  => 'Este es tu módulo de clientes. Te mostramos los puntos clave en menos de 1 minuto.',
        'step2_title' => 'Filtros',
        'step2_body'  => 'Busca y filtra por nombre, estado, fechas e ID. Los filtros activos aparecen como chips arriba de la tabla.',
        'step3_title' => 'Vistas guardadas',
        'step3_body'  => 'Guarda tu combinación favorita de filtros + columnas + orden y aplícala después con un clic. Cada usuario tiene las suyas propias.',
        'step4_title' => 'Columnas',
        'step4_body'  => 'Muestra/oculta columnas y se recuerda tu elección. Las marcadas como "obligatorias" no se pueden ocultar.',
        'step5_title' => 'Exportar & Importar',
        'step5_body'  => 'Exporta a Excel/PDF/Word en segundo plano — se te notificará cuando esté listo. Importa desde Excel/CSV con vista previa antes de confirmar.',
        'step6_title' => 'Editar muchos a la vez',
        'step6_body'  => '"Editar todo" permite modificar nombre y estado de varios registros juntos. Después se confirman todos los cambios en un solo guardado.',
        'step7_title' => 'Favoritos ★',
        'step7_body'  => 'La estrella ★ marca un registro como favorito. Los favoritos aparecen siempre arriba del listado y cada usuario tiene los suyos.',
        'step8_title' => 'Operaciones masivas',
        'step8_body'  => 'Selecciona filas con los checkboxes — aparece una barra para activar, desactivar, eliminar o restaurar. Funciona con cientos de filas; los lotes grandes se procesan en segundo plano.',
        'step9_title' => '¿Necesitas un repaso?',
        'step9_body'  => 'Reabre este tour cuando quieras con el botón ? aquí arriba. También tienes "Recientes" en el menú del avatar — los últimos registros que viste en cualquier módulo.',
    ],
];
