<?php

return [
    'singular'      => 'Producto',
    'plural'        => 'Productos',
    'record'        => 'producto',
    'records'       => 'productos',
    'new'           => 'Crear producto',
    'id'            => 'N°',

    'index_title'    => 'Productos',
    'index_subtitle' => 'Catálogo de bienes, servicios, suscripciones y bundles.',
    'create_title'   => 'Crear producto',
    'create_subtitle'=> 'Nuevo producto/servicio del catálogo.',
    'edit_title'     => 'Editar producto',
    'delete_title'   => 'Eliminar producto',
    'show_title'     => 'Producto — Información',
    'trash_title'    => 'Papelera de productos',
    'form_create_hint' => 'Define un nuevo producto del catálogo.',
    'empty_hint'      => 'Crea el primer producto o importa desde Excel para empezar.',
    'name_placeholder' => 'Ej: Licencia Pro Anual',

    // Identidad
    'name'             => 'Nombre',
    'name_placeholder_form' => 'Ej: Licencia Pro — 50 usuarios anual',
    'sku'              => 'SKU',
    'sku_placeholder'  => 'Ej: LIC-PRO-50',
    'sku_hint'         => 'Código único interno del producto. Identifica el producto en órdenes, facturas, stock y reportes. Usa un patrón consistente (ej: CAT-PROD-VAR).',
    'barcode'          => 'Código de barras',
    'barcode_placeholder' => 'EAN/UPC',
    'barcode_hint'     => 'Código de barras estándar (EAN-13, UPC-A, GTIN). Útil si vendes en retail y tienes lector de barcodes.',
    'name_hint'        => 'Nombre comercial del producto, como aparecerá en cotizaciones y facturas al cliente.',
    'description_hint' => 'Descripción corta que aparece en listados y selects de productos.',
    'long_description_hint' => 'Descripción extensa para fichas técnicas, propuestas, marketplace. No aparece en listas rápidas.',
    'description'      => 'Descripción corta',
    'description_placeholder' => 'Breve descripción para listados',
    'long_description' => 'Descripción larga',
    'long_description_placeholder' => 'Detalles, especificaciones técnicas, condiciones...',

    // Clasificación
    'category'         => 'Categoría',
    'category_placeholder' => 'Selecciona categoría',
    'category_hint'    => 'Agrupa productos para reportes, filtros y permisos. Las categorías se administran en Catálogos → Categorías de productos.',
    'type'             => 'Tipo',
    'type_hint'        => 'Bien físico = con stock; Servicio = horas/instalación sin stock; Suscripción = cobro recurrente (mensual/anual); Bundle = combo de varios productos vendido como uno solo.',
    'type_options' => [
        'good'         => 'Bien físico',
        'service'      => 'Servicio',
        'subscription' => 'Suscripción',
        'bundle'       => 'Bundle (combo)',
    ],
    'brand'            => 'Marca',
    'brand_placeholder'=> 'Ej: Acme, Genérico',
    'brand_hint'       => 'Marca del fabricante. Útil para filtros, reportes y SEO en marketplace.',

    // Precios
    'cost'             => 'Costo de adquisición',
    'cost_placeholder' => '0.00',
    'cost_hint'        => 'Cuánto estimás que te cuesta adquirir o producir el producto. Para compras locales suele coincidir con lo pagado. Para importaciones es una estimación — el costo real se registra después en "Costo final" cuando el producto llega al almacén.',
    'final_cost'       => 'Costo final',
    'final_cost_placeholder' => '0.00',
    'final_cost_hint'  => 'Costo real "landed" del producto: incluye flete, aduanas, agentes, seguros. Para imports se conoce solo cuando el producto llega al almacén. Para compras locales suele coincidir con el costo de adquisición. Sirve para reportes de rentabilidad reales.',
    'list_price'       => 'Precio de venta',
    'list_price_placeholder' => '0.00',
    'list_price_hint'  => 'Precio público al que se vende el producto. Puede ser overrideado por listas de precios (wholesale, enterprise, etc.).',
    'currency'         => 'Moneda',
    'currency_placeholder' => 'Hereda del tenant',
    'currency_hint'    => 'Moneda en la que se expresan precio y costo. Si se deja vacío, hereda la moneda default del workspace.',
    'margin'           => 'Margen',
    'margin_pct'       => 'Margen %',
    'margin_pct_hint'  => 'Margen porcentual calculado automáticamente a partir de costo de adquisición y precio de venta. Solo lectura.',
    'final_margin_pct' => 'Margen % final',
    'final_margin_pct_hint' => 'Margen porcentual REAL calculado a partir de costo final (landed) y precio de venta. Refleja rentabilidad efectiva post-importación. Solo lectura.',

    // Tax
    'tax_class'        => 'Clase de impuesto',

    // Inventario
    'track_inventory'     => 'Trackear stock',
    'track_inventory_hint'=> 'Si está activo, se descuenta stock al vender. Solo aplica a bienes físicos.',
    'low_stock_threshold' => 'Stock mínimo (alerta)',
    'low_stock_threshold_placeholder' => '0',
    'low_stock_threshold_hint' => 'Cantidad mínima. Si stock disponible baja de este valor, aparece alerta en el dashboard y notificaciones.',

    // Subscription
    'billing_cycle_hint' => 'Frecuencia con la que se factura automáticamente la suscripción al cliente.',
    'billing_cycle'    => 'Ciclo de facturación',
    'billing_cycle_options' => [
        'monthly'   => 'Mensual',
        'quarterly' => 'Trimestral',
        'yearly'    => 'Anual',
    ],
    'billing_period'   => 'Cada N ciclos',
    'billing_period_hint' => 'Ej: 1 = todos los meses, 3 = cada 3 meses',

    // Dimensiones
    'weight_kg' => 'Peso (kg)',
    'weight_kg_hint' => 'Peso del producto empacado en kilogramos. Se usa para calcular costos de envío y guías de despacho.',
    'length_cm' => 'Largo (cm)',
    'length_cm_hint' => 'Largo del producto empacado en centímetros. Útil para calcular tarifas de envío por volumen.',
    'width_cm'  => 'Ancho (cm)',
    'width_cm_hint' => 'Ancho del producto empacado en centímetros. Útil para calcular tarifas de envío por volumen.',
    'height_cm' => 'Alto (cm)',
    'height_cm_hint' => 'Alto del producto empacado en centímetros. Útil para calcular tarifas de envío por volumen.',

    // Otros
    'image_url'   => 'URL de imagen',
    'image_url_hint' => 'URL pública de la imagen del producto. Se muestra en listados, fichas y en cotizaciones/facturas. Recomendado: 600×600 px mínimo.',
    'external_id' => 'ID externo',
    'external_id_hint' => 'Identificador del producto en sistemas externos (ERP, e-commerce, marketplace). Útil para sincronización vía imports/exports o APIs.',

    // Form sections
    'section_general'    => 'Datos generales',
    'section_classification' => 'Clasificación',
    'section_pricing'    => 'Precios + moneda',
    'section_inventory'  => 'Inventario',
    'section_subscription' => 'Suscripción',
    'section_shipping'   => 'Dimensiones / envío',
    'section_media'      => 'Imagen + IDs',

    'is_active' => 'Estado',
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
    'export_filename'           => 'exportacion_productos',
    'import_template_filename'  => 'plantilla-productos.xlsx',
    'export_title'              => 'Reporte de Productos',
    'export_limit_exceeded'     => 'El export en :format excede el límite (:count filas vs :limit máximo). Usa CSV para datasets grandes (sin límite).',
    'export_format_limit_hint'  => 'Máximo :limit filas para este formato. Usa CSV para datasets grandes.',
    'export_no_limit_hint'      => 'Sin límite — recomendado para datasets grandes.',

    // Validation
    'name_required'            => 'El nombre del producto es obligatorio.',
    'name_unique'              => 'Ya existe un producto con ese nombre.',
    'name_duplicate_in_batch'  => 'Nombre duplicado dentro del mismo batch.',
    'list_price_required'      => 'El precio de venta es obligatorio.',
    'type_required'            => 'Selecciona el tipo de producto.',
    'billing_cycle_required'   => 'Selecciona el ciclo de facturación para suscripciones.',
    'is_active_required'       => 'El campo estado es obligatorio.',

    // Edit All
    'edit_all_title'    => 'Producto — Editar Todo',
    'edit_all_subtitle' => 'Edita nombre y estado de muchos productos a la vez.',
    'edit_all_changes'  => '{0} Sin cambios|{1} 1 cambio pendiente|[2,*] :count cambios pendientes',
    'edit_all_save_all' => 'Guardar todo',
    'edit_all_discard'  => 'Descartar cambios',
    'edit_all_no_results' => 'No hay productos que coincidan con el filtro.',

    'table_headers' => [
        'editable_name'   => 'Nombre (editable)',
        'editable_status' => 'Estado (editable)',
    ],

    // Onboarding tour
    'tour' => [
        'step1_title' => 'Bienvenido a Productos',
        'step1_body'  => 'Tu catálogo: bienes, servicios, suscripciones y bundles. Te mostramos lo clave en 1 minuto.',
        'step2_title' => 'Filtros',
        'step2_body'  => 'Busca y filtra por nombre, SKU, tipo, categoría y estado.',
        'step3_title' => 'Vistas guardadas',
        'step3_body'  => 'Guarda combinaciones de filtros + columnas + orden y reúsalas.',
        'step4_title' => 'Columnas',
        'step4_body'  => 'Muestra/oculta columnas y se recuerda tu elección.',
        'step5_title' => 'Exportar & Importar',
        'step5_body'  => 'Exporta a Excel/PDF/Word en segundo plano. Importa desde Excel/CSV con vista previa.',
        'step6_title' => 'Editar muchos a la vez',
        'step6_body'  => '"Editar todo" permite modificar nombre y estado de varios productos juntos.',
        'step7_title' => 'Favoritos ★',
        'step7_body'  => 'Marca productos clave como favoritos para tenerlos siempre arriba.',
        'step8_title' => 'Operaciones masivas',
        'step8_body'  => 'Selecciona filas con los checkboxes para activar, desactivar, eliminar o restaurar en lote.',
        'step9_title' => '¿Necesitas un repaso?',
        'step9_body'  => 'Reabre este tour cuando quieras con el botón ? aquí arriba.',
    ],
];
