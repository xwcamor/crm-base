<?php

return [
    'index_title'    => 'Stock',
    'index_subtitle' => 'Current levels (on hand, reserved, available) per product × warehouse.',
    'movements_title' => 'Kardex — Stock movements',
    'movements_subtitle' => 'Full history of inflows/outflows/adjustments/transfers.',
    'empty_hint'     => 'No stock recorded.',
    'movements_empty' => 'No movements recorded.',

    'product'        => 'Product',
    'sku'            => 'SKU',
    'warehouse'      => 'Warehouse',
    'qty_on_hand'    => 'On hand',
    'qty_reserved'   => 'Reserved',
    'qty_incoming'   => 'Incoming',
    'qty_available'  => 'Available',
    'low_stock'      => 'Min',
    'average_cost'   => 'Avg cost',
    'last_movement_at' => 'Last move',

    'moved_at'        => 'Date',
    'type'            => 'Type',
    'quantity'        => 'Quantity',
    'unit_cost'       => 'Unit cost',
    'total_cost'      => 'Total',
    'source_reference'=> 'Source doc',
    'note'            => 'Note',

    'type_options' => [
        'receipt'      => 'Inflow (receipt)',
        'issue'        => 'Outflow (issue)',
        'transfer_in'  => 'Transfer in',
        'transfer_out' => 'Transfer out',
        'adjustment'   => 'Manual adjustment',
        'return_in'    => 'Customer return',
        'return_out'   => 'Supplier return',
    ],

    'view_movements' => 'View movements (kardex)',
];
