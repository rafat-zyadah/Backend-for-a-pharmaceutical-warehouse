<?php

return [

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | System permissions (Spatie)
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'users.suspend',
        'users.reset_password',
        'system.settings.view',
        'system.settings.update',
    ],

    /*
    |--------------------------------------------------------------------------
    | Role → permission mapping
    |--------------------------------------------------------------------------
    */
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
        ],
        'invoicer' => [],
        'rep' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default application settings (stored in DB after install)
    |--------------------------------------------------------------------------
    */
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
