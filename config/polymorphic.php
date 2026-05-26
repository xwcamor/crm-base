<?php

/*
|--------------------------------------------------------------------------
| Polymorphic allowlist — single source of truth
|--------------------------------------------------------------------------
|
| Mapea slug-de-módulo → FQCN del modelo. Lo usan:
|   - FavoriteController (toggle de favoritos polimórfico)
|   - RecentViewController (track de "últimos vistos")
|   - HandleInertiaRequests (resolver el route show del módulo)
|
| Centralizado aqui para evitar que el slug "regions" se hardcodee en 3
| lugares. Cuando agregues un modulo nuevo (patients, doctors, etc.),
| sumalo SOLO aqui y queda habilitado en todos los lugares de una.
|
| ESQUEMA por modulo:
|   - model:     FQCN del Eloquent model
|   - show_route: nombre del route name del show (para Recientes)
|
| El allowlist nunca debe aceptar entradas dinamicas del cliente — todo
| modulo soportado tiene que estar declarado aqui explicitamente.
*/
return [
    'modules' => [
        'regions' => [
            'model'      => \App\Models\Region::class,
            'show_route' => 'system_management.regions.show',
        ],
        'languages' => [
            'model'      => \App\Models\Language::class,
            'show_route' => 'system_management.languages.show',
        ],
        'countries' => [
            'model'      => \App\Models\Country::class,
            'show_route' => 'system_management.countries.show',
        ],
        'locales' => [
            'model'      => \App\Models\Locale::class,
            'show_route' => 'system_management.locales.show',
        ],
        'tenants' => [
            'model'      => \App\Models\Tenant::class,
            'show_route' => 'system_management.tenants.show',
        ],
        'system_modules' => [
            'model'      => \App\Models\SystemModule::class,
            'show_route' => 'system_management.system_modules.show',
        ],
        'users' => [
            'model'      => \App\Models\User::class,
            'show_route' => 'auth_management.users.show',
        ],
        'roles' => [
            'model'      => \App\Models\Role::class,
            'show_route' => 'user_management.roles.show',
        ],
        'customers' => [
            'model'      => \App\Models\Customer::class,
            'show_route' => 'business_management.customers.show',
        ],
        'automations' => [
            'model'      => \App\Models\Automation::class,
            'show_route' => 'automation_management.automations.show',
        ],
        'companies' => [
            'model'      => \App\Models\Company::class,
            'show_route' => 'crm.companies.show',
        ],
        'contacts' => [
            'model'      => \App\Models\Contact::class,
            'show_route' => 'crm.contacts.show',
        ],
        'pipelines' => [
            'model'      => \App\Models\Pipeline::class,
            'show_route' => 'crm.pipelines.show',
        ],
        'deals' => [
            'model'      => \App\Models\Deal::class,
            'show_route' => 'crm.deals.show',
        ],
        'activities' => [
            'model'      => \App\Models\Activity::class,
            'show_route' => null,
        ],
        'products' => [
            'model'      => \App\Models\Product::class,
            'show_route' => 'business_management.products.show',
        ],
        'quotes' => [
            'model'      => \App\Models\Quote::class,
            'show_route' => 'business_management.quotes.show',
        ],
        'invoices' => [
            'model'      => \App\Models\Invoice::class,
            'show_route' => 'business_management.invoices.show',
        ],
        'payments' => [
            'model'      => \App\Models\Payment::class,
            'show_route' => 'business_management.payments.show',
        ],
        'warehouses' => [
            'model'      => \App\Models\Warehouse::class,
            'show_route' => 'business_management.warehouses.show',
        ],
        'tax_classes' => [
            'model'      => \App\Models\TaxClass::class,
            'show_route' => 'business_management.tax_classes.show',
        ],
        'discounts' => [
            'model'      => \App\Models\Discount::class,
            'show_route' => 'business_management.discounts.show',
        ],
        'price_lists' => [
            'model'      => \App\Models\PriceList::class,
            'show_route' => 'business_management.price_lists.show',
        ],
        'purchase_orders' => [
            'model'      => \App\Models\PurchaseOrder::class,
            'show_route' => 'business_management.purchase_orders.show',
        ],
        'stock_takes' => [
            'model'      => \App\Models\StockTake::class,
            'show_route' => 'business_management.stock_takes.show',
        ],
        'product_categories' => [
            'model'      => \App\Models\ProductCategory::class,
            'show_route' => 'business_management.product_categories.show',
        ],
        'product_variants' => [
            'model'      => \App\Models\ProductVariant::class,
            'show_route' => 'business_management.product_variants.show',
        ],
        'lead_sources' => [
            'model'      => \App\Models\LeadSource::class,
            'show_route' => 'business_management.lead_sources.show',
        ],
        'deliveries' => [
            'model'      => \App\Models\Delivery::class,
            'show_route' => 'business_management.deliveries.show',
        ],
        'payment_methods' => [
            'model'      => \App\Models\PaymentMethod::class,
            'show_route' => 'business_management.payment_methods.show',
        ],
        'messages' => [
            'model'      => \App\Models\Message::class,
            'show_route' => 'communication.messages.show',
        ],
        'exchange_rates' => [
            'model'      => \App\Models\ExchangeRate::class,
            'show_route' => 'business_management.exchange_rates.show',
        ],
        'plans' => [
            'model'      => \App\Models\Plan::class,
            'show_route' => 'system_management.plans.show',
        ],
        // Agrega modulos nuevos aqui cuando crees patients, doctors, etc.
    ],
];
