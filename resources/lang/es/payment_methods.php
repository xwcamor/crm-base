<?php

return [
    'singular'      => 'Método de pago',
    'plural'        => 'Métodos de pago',
    'record'        => 'método',
    'records'       => 'métodos',
    'new'           => 'Crear método',
    'id'            => 'N°',

    'index_title'    => 'Métodos de pago',
    'index_subtitle' => 'Catálogo de formas de cobro aceptadas por el workspace.',
    'create_title'   => 'Crear método de pago',
    'create_subtitle'=> 'Completa los datos para crear un nuevo método de pago.',
    'edit_title'     => 'Editar método de pago',
    'delete_title'   => 'Eliminar método de pago',
    'show_title'     => 'Método de pago — Información',
    'trash_title'    => 'Papelera de métodos de pago',
    'form_create_hint' => 'Completa los datos para crear un nuevo método de pago.',
    'empty_hint'     => 'No hay métodos de pago — crea el primero.',
    'name_placeholder' => 'Ej: Transferencia bancaria',

    'name'                 => 'Nombre',
    'name_hint'            => 'Nombre descriptivo del método de pago. Aparece en facturas y reportes.',
    'code'                 => 'Código',
    'code_hint'            => 'Código corto opcional para identificar el método (ej: transfer, card, cash).',
    'description'          => 'Descripción',
    'description_hint'     => 'Notas internas sobre el método de pago.',
    'integration_provider' => 'Provider de integración',
    'integration_provider_hint' => 'Provider externo opcional (stripe, mercadopago, paypal, etc.).',
    'requires_reference'   => 'Requiere referencia',
    'requires_reference_hint' => 'Si está activo, se solicitará un número de referencia (ej: nro de transferencia) al registrar un pago.',
    'sort_order'           => 'Orden',
    'sort_order_hint'      => 'Orden numérico para listar los métodos. Menor primero.',
    'is_active'            => 'Estado',
    'is_active_hint'       => 'Si está inactivo, no podrá usarse para nuevos pagos.',
    'filter_name'          => 'Nombre',

    'edit_hint'   => 'Modificar este registro',
    'delete_hint' => 'Eliminar (queda en papelera)',
    'restore_hint'=> 'Volverá a estar disponible en el listado principal.',

    'created' => 'Método de pago creado.',
    'saved'   => 'Método de pago actualizado.',
    'deleted' => 'Método de pago eliminado.',

    'delete_about'                 => 'Vas a eliminar ":name". Quedará en papelera.',
    'deleted_description_required' => 'Indica el motivo del borrado.',
    'deleted_description_min'      => 'El motivo debe tener al menos 3 caracteres.',
    'deleted_description_max'      => 'El motivo no puede superar los 1000 caracteres.',

    // Export
    'export_filename'           => 'exportacion_metodos_pago',
    'import_template_filename'  => 'plantilla-metodos-pago.xlsx',
    'export_title'              => 'Reporte de Métodos de pago',
    'export_limit_exceeded'     => 'El export en :format excede el límite (:count filas vs :limit máximo). Usa CSV para datasets grandes (sin límite).',
    'export_format_limit_hint'  => 'Máximo :limit filas para este formato. Usa CSV para datasets grandes.',
    'export_no_limit_hint'      => 'Sin límite — recomendado para datasets grandes.',

    // Validation
    'name_required'            => 'El nombre es obligatorio.',
    'name_unique'              => 'Ya existe un método de pago con ese nombre.',
    'name_duplicate_in_batch'  => 'Nombre duplicado dentro del mismo batch.',
    'is_active_required'       => 'El campo estado es obligatorio.',

    // Edit All
    'edit_all_title'    => 'Métodos de pago — Editar Todo',
    'edit_all_subtitle' => 'Edita nombre y estado de muchos métodos a la vez. Haz clic en "Guardar todo" para confirmar.',
    'edit_all_changes'  => '{0} Sin cambios|{1} 1 cambio pendiente|[2,*] :count cambios pendientes',
    'edit_all_save_all' => 'Guardar todo',
    'edit_all_discard'  => 'Descartar cambios',
    'edit_all_no_results' => 'No hay métodos que coincidan con el filtro.',

    'table_headers' => [
        'editable_name'   => 'Nombre (editable)',
        'editable_status' => 'Estado (editable)',
    ],

    // Onboarding tour
    'tour' => [
        'step1_title' => 'Bienvenido a Métodos de pago',
        'step1_body'  => 'Este es tu catálogo de métodos de pago. Te mostramos los puntos clave en menos de 1 minuto.',
        'step2_title' => 'Filtros',
        'step2_body'  => 'Busca y filtra por nombre, código, provider, estado. Los filtros activos aparecen como chips arriba de la tabla.',
        'step3_title' => 'Vistas guardadas',
        'step3_body'  => 'Guarda tu combinación favorita de filtros + columnas + orden y aplícala después con un clic.',
        'step4_title' => 'Columnas',
        'step4_body'  => 'Muestra u oculta columnas; tu elección se recuerda. Las marcadas como obligatorias no se pueden ocultar.',
        'step5_title' => 'Exportar e importar',
        'step5_body'  => 'Exporta a Excel, PDF o Word en segundo plano. Importa desde Excel o CSV con vista previa antes de confirmar.',
        'step6_title' => 'Editar muchos a la vez',
        'step6_body'  => '"Editar todo" permite modificar nombre y estado de varios métodos juntos.',
        'step7_title' => 'Favoritos',
        'step7_body'  => 'La estrella marca un método como favorito. Los favoritos aparecen siempre arriba.',
        'step8_title' => 'Operaciones masivas',
        'step8_body'  => 'Selecciona filas con los checkboxes — aparece una barra para activar, desactivar, eliminar o restaurar en lote.',
        'step9_title' => '¿Necesitas un repaso?',
        'step9_body'  => 'Reabre este tour cuando quieras con el botón ? aquí arriba.',
    ],
];
