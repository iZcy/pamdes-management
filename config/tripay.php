<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tripay Configuration for Pamdes
    |--------------------------------------------------------------------------
    |
    | Configuration for Tripay payment gateway integration with villages
    |
    */

    // Production Configuration (Main/Global)
    'api_key' => env('TRIPAY_API_KEY'),
    'private_key' => env('TRIPAY_PRIVATE_KEY'),
    'merchant_code' => env('TRIPAY_MERCHANT_CODE'),

    // Sandbox Configuration (Main/Global)
    'api_key_sb' => env('TRIPAY_API_KEY_SANDBOX'),
    'private_key_sb' => env('TRIPAY_PRIVATE_KEY_SANDBOX'),
    'merchant_code_sb' => env('TRIPAY_MERCHANT_CODE_SANDBOX'),

    // URLs
    'base_url_production' => 'https://tripay.co.id/api',
    'base_url_sandbox' => 'https://tripay.co.id/api-sandbox',

    // Default settings
    'default_timeout_minutes' => 15,
    'default_payment_method' => 'QRIS',

    // Callback settings
    'callback_url' => env('TRIPAY_CALLBACK_URL', env('APP_URL') . '/tripay/callback'),
    'return_url' => env('TRIPAY_RETURN_URL', env('APP_URL') . '/tripay/return'),
];
