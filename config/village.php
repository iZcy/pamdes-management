<?php
// config/village.php - Updated for independent system

return [
    /*
    |--------------------------------------------------------------------------
    | Village System Configuration (Independent)
    |--------------------------------------------------------------------------
    |
    | Configuration for the independent PAMDes village management system
    |
    */

    // 'features' => [
    //     'notifications_enabled' => true,
    // ],

    'pamdes' => [
        'default_admin_fee' => 5000,
        'default_maintenance_fee' => 2000,
        'overdue_days_threshold' => 30,
        'auto_generate_bills' => true,
        'allow_partial_payments' => false,
        'require_payment_confirmation' => true,
    ],

    'billing' => [
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
];
