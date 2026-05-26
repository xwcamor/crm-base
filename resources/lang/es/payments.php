<?php

return [
    'singular' => 'Pago',
    'plural'   => 'Pagos',
    'record'   => 'pago',
    'records'  => 'pagos',
    'new'      => 'Registrar pago',

    'index_title'    => 'Pagos',
    'index_subtitle' => 'Cobros aplicados a facturas.',
    'show_title'     => 'Pago — Detalle',
    'create_title'   => 'Registrar pago',
    'edit_title'     => 'Editar pago',
    'delete_title'   => 'Eliminar pago',
    'trash_title'    => 'Papelera de pagos',

    // Legacy keys que otras pantallas (Delete/Trash/EditAll generados por scaffold)
    // todavía referencian. Apuntan al campo natural de identificación del pago.
    'name'          => 'Referencia',
    'is_active'     => 'Estado',
    'filter_name'   => 'Referencia',
    'name_unique'   => 'Esta referencia ya existe.',

    'reference'    => 'Referencia',
    'reference_hint' => 'Código corto interno para identificar el pago. Útil para conciliación contable.',
    'filter_reference' => 'Referencia',
    'company'      => 'Empresa',
    'invoice'      => 'Factura',
    'invoice_hint' => 'Factura a la que se aplica el pago. Déjala vacía si es un anticipo o saldo a favor del cliente.',
    'invoice_number' => 'N° Factura',
    'type'         => 'Tipo',
    'type_hint'    => 'Pago de factura = cancela un saldo; Anticipo = adelanto sin factura; Nota de crédito = devolución; Reembolso = reverso de cobro.',
    'payment_method' => 'Método',
    'payment_method_hint' => 'Forma de cobro: transferencia, tarjeta, efectivo, cheque. Define si requiere referencia bancaria.',
    'amount'       => 'Monto',
    'amount_hint'  => 'Importe efectivamente recibido. Si supera el saldo de la factura, el exceso queda como saldo a favor.',
    'currency'     => 'Moneda',
    'currency_hint' => 'Moneda en la que se recibió el pago. Debe coincidir con la moneda de la factura para aplicar el saldo.',
    'paid_at'      => 'Fecha pago',
    'paid_at_hint' => 'Fecha y hora en que se acreditó el pago en cuenta. Aparece en el recibo y en reportes de caja.',
    'reconciled_at'=> 'Conciliado',
    'status'       => 'Estado',
    'status_hint'  => 'Pendiente = sin confirmar; Completado = aplicado al saldo; Fallido = rechazado por el banco.',
    'bank_reference' => 'Ref. bancaria',
    'bank_reference_hint' => 'Número de operación bancaria, código de transferencia o número de cheque. Útil para conciliación.',
    'external_transaction_id' => 'ID transacción externa',
    'external_transaction_id_hint' => 'ID generado por la pasarela de pago (Stripe, MercadoPago, PayPal). Permite trazabilidad y disputas.',
    'notes'        => 'Notas',
    'notes_hint'   => 'Comentarios internos sobre el pago: condiciones especiales, observaciones del cliente, instrucciones para tesorería.',
    'required_suffix' => 'requerida',

    'edit_hint'    => 'Modificar este pago',
    'delete_hint'  => 'Eliminar (queda en papelera)',
    'restore_hint' => 'Volverá a estar disponible en el listado principal.',

    'empty_hint' => 'No hay pagos aún.',

    'type_options' => [
        'invoice_payment' => 'Pago de factura',
        'deposit'         => 'Depósito / Anticipo',
        'credit_memo'     => 'Nota de crédito',
        'refund'          => 'Reembolso',
    ],

    'status_options' => [
        'pending'   => 'Pendiente',
        'completed' => 'Completado',
        'failed'    => 'Fallido',
        'refunded'  => 'Reembolsado',
        'disputed'  => 'Disputado',
    ],

    // Flash messages
    'created' => 'Pago registrado.',
    'saved'   => 'Pago actualizado.',
    'deleted' => 'Pago eliminado.',

    // Validation
    'reference_unique'    => 'Ya existe un pago con esta referencia en el workspace.',
    'items_required'      => 'Agrega al menos una línea al pago.',
    'is_active_required'  => 'El estado activo es obligatorio.',

    // Delete confirmation
    'delete_about' => 'Vas a eliminar el pago ":name". Quedará en papelera.',
    'deleted_description_required' => 'Indica el motivo del borrado.',
    'deleted_description_min'      => 'El motivo debe tener al menos 3 caracteres.',
    'deleted_description_max'      => 'El motivo no puede superar los 1000 caracteres.',

    // Bulk
    'bulk_set_active' => 'Cambiar estado activo',

    // Export
    'export_filename'           => 'exportacion_pagos',
    'import_template_filename'  => 'plantilla-pagos.xlsx',
    'export_title'              => 'Reporte de Pagos',
    'export_limit_exceeded'     => 'El export en :format excede el límite (:count filas vs :limit máximo). Usa CSV para datasets grandes.',
    'export_format_limit_hint'  => 'Máximo :limit filas para este formato. Usa CSV para datasets grandes.',
    'export_no_limit_hint'      => 'Sin límite — recomendado para datasets grandes.',

    // Edit All
    'edit_all_title'      => 'Pagos — Editar Todo',
    'edit_all_subtitle'   => 'Edita referencia y estado de muchos pagos a la vez. Haz clic en "Guardar todo" para confirmar.',
    'edit_all_changes'    => '{0} Sin cambios|{1} 1 cambio pendiente|[2,*] :count cambios pendientes',
    'edit_all_save_all'   => 'Guardar todo',
    'edit_all_discard'    => 'Descartar cambios',
    'edit_all_no_results' => 'No hay pagos que coincidan con el filtro.',

    'table_headers' => [
        'editable_name'   => 'Referencia (editable)',
        'editable_status' => 'Estado (editable)',
    ],

    // Onboarding tour
    'tour' => [
        'step1_title' => 'Bienvenido a Pagos',
        'step1_body'  => 'Este es tu módulo de pagos. Te mostramos los puntos clave en menos de 1 minuto.',
        'step2_title' => 'Filtros',
        'step2_body'  => 'Busca y filtra por referencia, estado, tipo, método, fechas y monto. Los filtros activos aparecen como chips arriba de la tabla.',
        'step3_title' => 'Vistas guardadas',
        'step3_body'  => 'Guarda tu combinación favorita de filtros, columnas y orden, y aplícala después con un clic. Cada usuario tiene las suyas propias.',
        'step4_title' => 'Columnas',
        'step4_body'  => 'Muestra u oculta columnas y se recuerda tu elección. Las marcadas como obligatorias no se pueden ocultar.',
        'step5_title' => 'Exportar e importar',
        'step5_body'  => 'Exporta a Excel, PDF o Word en segundo plano — se te notificará cuando esté listo. Importa desde Excel o CSV con vista previa antes de confirmar.',
        'step6_title' => 'Editar muchos a la vez',
        'step6_body'  => '"Editar todo" permite modificar varios pagos juntos. Después se confirman todos los cambios en un solo guardado.',
        'step7_title' => 'Favoritos',
        'step7_body'  => 'La estrella marca un pago como favorito. Los favoritos aparecen siempre arriba del listado y cada usuario tiene los suyos.',
        'step8_title' => 'Operaciones masivas',
        'step8_body'  => 'Selecciona filas con los checkboxes — aparece una barra para eliminar o restaurar. Funciona con cientos de filas; los lotes grandes se procesan en segundo plano.',
    ],
];
