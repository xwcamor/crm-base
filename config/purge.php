<?php

/*
|--------------------------------------------------------------------------
| Soft-delete purge policy per module
|--------------------------------------------------------------------------
|
| Define after how many days each module's soft-deleted records become
| eligible for hard-delete. Records younger than `days` are kept; older
| are physically removed when `app:purge-soft-deleted` runs.
|
| Per-module options:
|   - model:    FQCN of the Eloquent model (must use SoftDeletes)
|   - days:     grace window in days (counted since deleted_at)
|   - anonymize: if true, replaces PII fields with random data BEFORE
|                deleting (relevant for users/patients with sensitive cols).
|                List the columns to anonymize.
|   - chunk:    batch size for the delete (default 500). Tune for tables
|               with very large rows.
|
| Adjust these by use-case:
|   - Catalog data (regions, languages, countries): days = 365
|   - Transactional data (tenants, settings):       days = 90
|   - PII data (users, patients):                   days = 30 + anonymize
|   - Time-series append-only:                      days = configurable per business
|
| Set days = 0 in env-specific overrides to disable purging for a module.
*/

return [
    'modules' => [
        'regions' => [
            'model' => \App\Models\Region::class,
            'days'  => 365,
        ],
        'languages' => [
            'model' => \App\Models\Language::class,
            'days'  => 365,
        ],
        'countries' => [
            'model' => \App\Models\Country::class,
            'days'  => 365,
        ],
        'locales' => [
            'model' => \App\Models\Locale::class,
            'days'  => 365,
        ],
        'tenants' => [
            'model' => \App\Models\Tenant::class,
            'days'  => 90,
        ],
        'system_modules' => [
            'model' => \App\Models\SystemModule::class,
            'days'  => 365,
        ],
        'users' => [
            'model'     => \App\Models\User::class,
            'days'      => 30,
            // PII completa: nombre, email, foto, google_id + password (hash).
            // El campo `module_tours` es jsonb sin PII per-se, no se anonimiza.
            'anonymize' => ['name', 'email', 'photo', 'google_id', 'password'],
        ],
        // ─── Negocio per-tenant ─────────────────────────────────────────
        'customers' => [
            'model'     => \App\Models\Customer::class,
            'days'      => 90,
            // Customer puede ser una persona física — anonimizamos identificadores.
            // `cod` es código interno del tenant pero puede contener DNI/CUIT/etc.
            'anonymize' => ['name', 'cod'],
        ],
        'roles' => [
            'model' => \App\Models\Role::class,
            'days'  => 180,
            // No PII — roles son metadata. Solo retention para mantener trazabilidad.
        ],
        'automations' => [
            'model' => \App\Models\Automation::class,
            'days'  => 90,
            // trigger_config / action_config pueden contener emails de destinatarios.
            // Anonimizamos action_config (json) reemplazando con marker; description
            // queda como string anodino. No usamos lista plana porque son jsonb.
            'anonymize' => ['description'],
        ],
        // ─── Catálogo de billing ────────────────────────────────────────
        'plans' => [
            'model' => \App\Models\Plan::class,
            'days'  => 365,
        ],
        'subscriptions' => [
            'model' => \App\Models\Subscription::class,
            'days'  => 365,
            // History de pagos — retención larga (audit + reportes históricos).
        ],
        'settings' => [
            'model' => \App\Models\Setting::class,
            'days'  => 365,
        ],
        'companies' => [
            'model' => \App\Models\Company::class,
            'days'  => 90,
        ],
        'contacts' => [
            'model' => \App\Models\Contact::class,
            'days'  => 90,
        ],
        'pipelines' => [
            'model' => \App\Models\Pipeline::class,
            'days'  => 90,
        ],
        'deals' => [
            'model' => \App\Models\Deal::class,
            'days'  => 90,
        ],
        'products' => [
            'model' => \App\Models\Product::class,
            'days'  => 90,
        ],
        'quotes' => [
            'model' => \App\Models\Quote::class,
            'days'  => 90,
        ],
        'invoices' => [
            'model' => \App\Models\Invoice::class,
            'days'  => 90,
        ],
        'payments' => [
            'model' => \App\Models\Payment::class,
            'days'  => 90,
        ],
        'warehouses' => [
            'model' => \App\Models\Warehouse::class,
            'days'  => 90,
        ],
        'tax_classes' => [
            'model' => \App\Models\TaxClass::class,
            'days'  => 90,
        ],
        'discounts' => [
            'model' => \App\Models\Discount::class,
            'days'  => 90,
        ],
        'price_lists' => [
            'model' => \App\Models\PriceList::class,
            'days'  => 90,
        ],
        'purchase_orders' => [
            'model' => \App\Models\PurchaseOrder::class,
            'days'  => 90,
        ],
        'stock_takes' => [
            'model' => \App\Models\StockTake::class,
            'days'  => 90,
        ],
        'product_categories' => [
            'model' => \App\Models\ProductCategory::class,
            'days'  => 90,
        ],
        'product_variants' => [
            'model' => \App\Models\ProductVariant::class,
            'days'  => 90,
        ],
        'lead_sources' => [
            'model' => \App\Models\LeadSource::class,
            'days'  => 90,
        ],
        'deliveries' => [
            'model' => \App\Models\Delivery::class,
            'days'  => 90,
        ],
        'payment_methods' => [
            'model' => \App\Models\PaymentMethod::class,
            'days'  => 90,
        ],
        'messages' => [
            'model' => \App\Models\Message::class,
            'days'  => 180,
            // Anuncios/avisos super-only — retencion media. Sin PII en body
            // (es comunicacion publica del admin), no se anonimiza.
        ],
        'exchange_rates' => [
            'model' => \App\Models\ExchangeRate::class,
            'days'  => 365,
            // Tasas FX historicas — retencion larga por valor de reporting
            // y reproducibilidad de conversiones pasadas. Sin PII.
        ],
        // Suma modulos nuevos aqui:
        // 'patients' => [...],
        // 'doctors'  => [...],
        // 'transformers' => [...],
    ],
];
