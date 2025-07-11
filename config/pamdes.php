<?php
// config/pamdes.php - Optimized version without redundancy

return [
    /*
    |--------------------------------------------------------------------------
    | PAMDes Domain Configuration
    |--------------------------------------------------------------------------
    */
    'domains' => [
        'main' => env('PAMDES_MAIN_DOMAIN', 'pamdes.local'),
        // Super admin uses APP_DOMAIN - no separate config needed
        'super_admin' => env('APP_DOMAIN', 'localhost'),
        'village_pattern' => env('PAMDES_VILLAGE_DOMAIN_PATTERN', 'pamdes-{village}.local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Village System Configuration (Independent)
    |--------------------------------------------------------------------------
    */
    'features' => [
        'notifications_enabled' => env('PAMDES_NOTIFICATIONS_ENABLED', true),
    ],

    'billing' => [
        'default_admin_fee' => env('PAMDES_DEFAULT_ADMIN_FEE', 5000),
        'default_maintenance_fee' => env('PAMDES_DEFAULT_MAINTENANCE_FEE', 2000),
        'overdue_days_threshold' => 30,
        'auto_generate_bills' => env('PAMDES_AUTO_GENERATE_BILLS', true),
        'allow_partial_payments' => false,
        'require_payment_confirmation' => true,
        'default_due_days' => 30,
        'late_fee_enabled' => false,
        'late_fee_amount' => 0,
        'late_fee_percentage' => 0,
    ],

    'cache' => [
        'village_data_ttl' => 3600, // 1 hour (local cache)
        'settings_ttl' => 7200,    // 2 hours
    ],

    'reports' => [
        'export_formats' => ['pdf', 'excel', 'csv'],
        'auto_generate_monthly' => true,
        'retain_reports_months' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Helper Methods for Domain Generation
    |--------------------------------------------------------------------------
    */
    'helpers' => [
        'get_village_domain' => function ($villageSlug) {
            $pattern = config('pamdes.domains.village_pattern');
            return str_replace('{village}', $villageSlug, $pattern);
        },

        'get_main_domain' => function () {
            return config('pamdes.domains.main');
        },

        'get_super_admin_domain' => function () {
            return config('pamdes.domains.super_admin');
        },
    ],
];
