<?php

return [

    'guard' => 'web',

    'permissions' => [
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'users.suspend',
        'users.reset_password',
        'system.settings.view',
        'system.settings.update',
        'regions.view',
        'regions.manage',
        'companies.view',
        'companies.manage',
        'products.view',
        'products.manage',
        'pharmacies.view',
        'pharmacies.manage',
        'distribution.view',
        'distribution.manage',
        'orders.view',
        'orders.submit',
        'orders.manage',
    ],

    'role_permissions' => [
        'supervisor' => [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.suspend',
            'users.reset_password',
            'system.settings.view',
            'system.settings.update',
            'regions.view',
            'companies.view',
            'products.view',
            'pharmacies.view',
            'distribution.view',
            'orders.view',
        ],
        'invoicer' => [
            'regions.view',
            'regions.manage',
            'companies.view',
            'companies.manage',
            'products.view',
            'products.manage',
            'pharmacies.view',
            'pharmacies.manage',
            'distribution.view',
            'distribution.manage',
            'orders.view',
            'orders.manage',
        ],
        'rep' => [
            'regions.view',
            'companies.view',
            'products.view',
            'pharmacies.view',
            'orders.submit',
        ],
    ],

    'settings' => [
        'low_stock_threshold' => [
            'value' => (string) env('PHARMACY_LOW_STOCK_THRESHOLD', 100),
            'type' => 'integer',
        ],
        'app_locale' => [
            'value' => env('APP_LOCALE', 'ar'),
            'type' => 'string',
        ],
        'currency_code' => [
            'value' => env('PHARMACY_CURRENCY_CODE', 'IQD'),
            'type' => 'string',
        ],
    ],

];
