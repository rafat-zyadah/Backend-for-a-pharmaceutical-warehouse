<?php

return [
    'name' => env('APP_NAME', 'Pharmacy Warehouse API'),
    'low_stock_threshold' => (int) env('PHARMACY_LOW_STOCK_THRESHOLD', 100),

    'default_supervisor' => [
        'username' => env('PHARMACY_DEFAULT_SUPERVISOR_USERNAME', 'supervisor'),
        'password' => env('PHARMACY_DEFAULT_SUPERVISOR_PASSWORD', 'password'),
        'name' => env('PHARMACY_DEFAULT_SUPERVISOR_NAME', 'Supervisor Admin'),
        'phone' => env('PHARMACY_DEFAULT_SUPERVISOR_PHONE', '0500000001'),
        'email' => env('PHARMACY_DEFAULT_SUPERVISOR_EMAIL', 'supervisor@example.com'),
    ],
];
