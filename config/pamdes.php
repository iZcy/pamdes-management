<?php
// config/pamdes.php - Updated for new domain configuration

return [
    /*
     |--------------------------------------------------------------------------
     | PAMDes Domain Configuration
     |--------------------------------------------------------------------------
     */
    'domains' => [
        'main' => env('PAMDES_MAIN_DOMAIN', 'pamdes.dev-svnova.id'),
        'super_admin' => env('PAMDES_SUPER_ADMIN_DOMAIN', 'pamdes.dev-svnova.id'),
        'village_pattern' => env('PAMDES_VILLAGE_DOMAIN_PATTERN', 'pamdes-{village}.dev-svnova.id'),
    ],

    /*
     |--------------------------------------------------------------------------
     | Village System Settings
     |--------------------------------------------------------------------------
     */
    'billing' => [
        'default_admin_fee' => env('PAMDES_DEFAULT_ADMIN_FEE', 5000),
        'default_maintenance_fee' => env('PAMDES_DEFAULT_MAINTENANCE_FEE', 2000),
        'overdue_days_threshold' => 30,
        'auto_generate_bills' => env('PAMDES_AUTO_GENERATE_BILLS', true),
        'default_due_days' => 30,
    ],

    /*
     |--------------------------------------------------------------------------
     | Current Context (Set by middleware)
     |--------------------------------------------------------------------------
     */
    'current_village' => null,
    'current_village_id' => null,
    'is_super_admin_domain' => false,
];
