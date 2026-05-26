<?php

return [
    'index_title'    => 'Stock',
    'index_subtitle' => 'Niveles actuales (qty on hand, reservado, disponible) por producto × almacén.',
    'movements_title' => 'Kardex — Movimientos de stock',
    'movements_subtitle' => 'Historial completo de entradas/salidas/ajustes/transferencias.',
    'empty_hint'     => 'Sin stock registrado.',
    'movements_empty' => 'Sin movimientos registrados.',

    'product'        => 'Producto',
    'sku'            => 'SKU',
    'warehouse'      => 'Almacén',
    'qty_on_hand'    => 'On hand',
    'qty_reserved'   => 'Reservado',
    'qty_incoming'   => 'Incoming',
    'qty_available'  => 'Disponible',
    'low_stock'      => 'Mín',
    'average_cost'   => 'Costo prom.',
    'last_movement_at' => 'Último mov.',

    // Movements
    'moved_at'        => 'Fecha',
    'type'            => 'Tipo',
    'quantity'        => 'Cantidad',
    'unit_cost'       => 'Costo unit.',
    'total_cost'      => 'Total',
    'source_reference'=> 'Ref. doc',
    'note'            => 'Nota',

    'type_options' => [
        'receipt'      => 'Entrada (recepción)',
        'issue'        => 'Salida (despacho)',
        'transfer_in'  => 'Transferencia entrante',
        'transfer_out' => 'Transferencia saliente',
        'adjustment'   => 'Ajuste manual',
        'return_in'    => 'Devolución de cliente',
        'return_out'   => 'Devolución a proveedor',
    ],

    'view_movements' => 'Ver movimientos (kardex)',
];
