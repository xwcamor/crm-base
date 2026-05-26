<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CurrenciesSeeder — 40 monedas ISO 4217 más usadas en CRM B2B.
 *
 * Idempotente: usa updateOrInsert por code, se puede re-correr sin duplicar.
 * Cubre LATAM (ARS/BRL/CLP/COP/MXN/PEN/UYU), USD/EUR/GBP majors, y monedas
 * comunes en transacciones internacionales B2B.
 *
 * decimal_places: la mayoría usa 2; JPY/KRW usan 0 (sin centavos); BHD/KWD
 * usan 3 (sub-centavo). Si necesitas más, agregalas con `php artisan
 * db:seed --class=CurrenciesSeeder`.
 */
class CurrenciesSeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            // North America
            ['code' => 'USD', 'name' => 'US Dollar',           'symbol' => '$',   'decimals' => 2],
            ['code' => 'CAD', 'name' => 'Canadian Dollar',     'symbol' => 'CA$', 'decimals' => 2],
            ['code' => 'MXN', 'name' => 'Mexican Peso',        'symbol' => 'Mex$','decimals' => 2],

            // South America
            ['code' => 'ARS', 'name' => 'Argentine Peso',      'symbol' => 'AR$', 'decimals' => 2],
            ['code' => 'BRL', 'name' => 'Brazilian Real',      'symbol' => 'R$',  'decimals' => 2],
            ['code' => 'CLP', 'name' => 'Chilean Peso',        'symbol' => 'CL$', 'decimals' => 0],
            ['code' => 'COP', 'name' => 'Colombian Peso',      'symbol' => 'CO$', 'decimals' => 2],
            ['code' => 'PEN', 'name' => 'Peruvian Sol',        'symbol' => 'S/',  'decimals' => 2],
            ['code' => 'UYU', 'name' => 'Uruguayan Peso',      'symbol' => '$U',  'decimals' => 2],
            ['code' => 'BOB', 'name' => 'Bolivian Boliviano',  'symbol' => 'Bs',  'decimals' => 2],
            ['code' => 'PYG', 'name' => 'Paraguayan Guaraní',  'symbol' => '₲',   'decimals' => 0],
            ['code' => 'VES', 'name' => 'Venezuelan Bolívar',  'symbol' => 'Bs.S','decimals' => 2],

            // Europe
            ['code' => 'EUR', 'name' => 'Euro',                'symbol' => '€',   'decimals' => 2],
            ['code' => 'GBP', 'name' => 'British Pound',       'symbol' => '£',   'decimals' => 2],
            ['code' => 'CHF', 'name' => 'Swiss Franc',         'symbol' => 'CHF', 'decimals' => 2],
            ['code' => 'NOK', 'name' => 'Norwegian Krone',     'symbol' => 'kr',  'decimals' => 2],
            ['code' => 'SEK', 'name' => 'Swedish Krona',       'symbol' => 'kr',  'decimals' => 2],
            ['code' => 'DKK', 'name' => 'Danish Krone',        'symbol' => 'kr',  'decimals' => 2],
            ['code' => 'PLN', 'name' => 'Polish Złoty',        'symbol' => 'zł',  'decimals' => 2],
            ['code' => 'CZK', 'name' => 'Czech Koruna',        'symbol' => 'Kč',  'decimals' => 2],
            ['code' => 'HUF', 'name' => 'Hungarian Forint',    'symbol' => 'Ft',  'decimals' => 2],
            ['code' => 'RON', 'name' => 'Romanian Leu',        'symbol' => 'lei', 'decimals' => 2],
            ['code' => 'TRY', 'name' => 'Turkish Lira',        'symbol' => '₺',   'decimals' => 2],

            // Asia-Pacific
            ['code' => 'JPY', 'name' => 'Japanese Yen',        'symbol' => '¥',   'decimals' => 0],
            ['code' => 'CNY', 'name' => 'Chinese Yuan',        'symbol' => '¥',   'decimals' => 2],
            ['code' => 'KRW', 'name' => 'South Korean Won',    'symbol' => '₩',   'decimals' => 0],
            ['code' => 'HKD', 'name' => 'Hong Kong Dollar',    'symbol' => 'HK$', 'decimals' => 2],
            ['code' => 'SGD', 'name' => 'Singapore Dollar',    'symbol' => 'S$',  'decimals' => 2],
            ['code' => 'TWD', 'name' => 'Taiwan Dollar',       'symbol' => 'NT$', 'decimals' => 2],
            ['code' => 'INR', 'name' => 'Indian Rupee',        'symbol' => '₹',   'decimals' => 2],
            ['code' => 'IDR', 'name' => 'Indonesian Rupiah',   'symbol' => 'Rp',  'decimals' => 2],
            ['code' => 'PHP', 'name' => 'Philippine Peso',     'symbol' => '₱',   'decimals' => 2],
            ['code' => 'THB', 'name' => 'Thai Baht',           'symbol' => '฿',   'decimals' => 2],
            ['code' => 'MYR', 'name' => 'Malaysian Ringgit',   'symbol' => 'RM',  'decimals' => 2],
            ['code' => 'AUD', 'name' => 'Australian Dollar',   'symbol' => 'A$',  'decimals' => 2],
            ['code' => 'NZD', 'name' => 'New Zealand Dollar',  'symbol' => 'NZ$', 'decimals' => 2],

            // Middle East / Africa
            ['code' => 'AED', 'name' => 'UAE Dirham',          'symbol' => 'AED', 'decimals' => 2],
            ['code' => 'SAR', 'name' => 'Saudi Riyal',         'symbol' => 'SR',  'decimals' => 2],
            ['code' => 'ZAR', 'name' => 'South African Rand',  'symbol' => 'R',   'decimals' => 2],
            ['code' => 'EGP', 'name' => 'Egyptian Pound',      'symbol' => 'E£',  'decimals' => 2],
        ];

        foreach ($currencies as $c) {
            DB::table('currencies')->updateOrInsert(
                ['code' => $c['code']],
                [
                    'slug'           => Str::random(22),
                    'name'           => $c['name'],
                    'symbol'         => $c['symbol'],
                    'decimal_places' => $c['decimals'],
                    'is_active'      => true,
                    'created_by'     => 1,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]
            );
        }
    }
}
