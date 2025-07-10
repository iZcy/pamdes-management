<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Village System Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for integrating with the main village management system
    |
    */

    'api' => [
        'url' => env('VILLAGE_SYSTEM_URL', 'https://kecamatanbayan.id'),
        'key' => env('VILLAGE_SYSTEM_API_KEY'),
        'timeout' => env('VILLAGE_SYSTEM_TIMEOUT', 10),
    ],

    'cache' => [
        'village_data_ttl' => 300, // 5 minutes
        'settings_ttl' => 1800,    // 30 minutes
        'villages_list_ttl' => 600, // 10 minutes
    ],

    'features' => [
        'auto_sync_enabled' => env('VILLAGE_AUTO_SYNC', true),
        'notifications_enabled' => env('VILLAGE_NOTIFICATIONS', true),
        'sso_enabled' => env('VILLAGE_SSO_ENABLED', true),
    ],

    'pamdes' => [
        'default_admin_fee' => 5000,
        'default_maintenance_fee' => 2000,
        'overdue_days_threshold' => 30,
        'auto_generate_bills' => true,
    ],
];
