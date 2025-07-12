<?php
// config/export.php - Export configuration

return [
    /*
    |--------------------------------------------------------------------------
    | Default Export Settings
    |--------------------------------------------------------------------------
    */
    'default_format' => env('EXPORT_DEFAULT_FORMAT', 'pdf'),

    /*
    |--------------------------------------------------------------------------
    | Export Storage Configuration
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'disk' => env('EXPORT_STORAGE_DISK', 'public'),
        'path' => env('EXPORT_STORAGE_PATH', 'exports'),
        'cleanup_days' => env('EXPORT_CLEANUP_DAYS', 7), // Auto-delete exports after 7 days
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Export Configuration
    |--------------------------------------------------------------------------
    */
    'pdf' => [
        'paper' => env('EXPORT_PDF_PAPER', 'A4'),
        'orientation' => env('EXPORT_PDF_ORIENTATION', 'landscape'),
        'dpi' => env('EXPORT_PDF_DPI', 150),
        'enable_remote' => env('DOMPDF_ENABLE_REMOTE', true),
        'enable_css_float' => env('DOMPDF_ENABLE_CSS_FLOAT', true),
        'enable_html5_parser' => env('DOMPDF_ENABLE_HTML5_PARSER', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | CSV Export Configuration
    |--------------------------------------------------------------------------
    */
    'csv' => [
        'delimiter' => env('EXPORT_CSV_DELIMITER', ','),
        'enclosure' => env('EXPORT_CSV_ENCLOSURE', '"'),
        'escape' => env('EXPORT_CSV_ESCAPE', '\\'),
        'include_bom' => env('EXPORT_CSV_INCLUDE_BOM', true), // For Excel compatibility
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_records' => env('EXPORT_MAX_RECORDS', 10000),
        'memory_limit' => env('EXPORT_MEMORY_LIMIT', '512M'),
        'time_limit' => env('EXPORT_TIME_LIMIT', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Templates
    |--------------------------------------------------------------------------
    */
    'templates' => [
        'pdf' => 'exports.pdf-template',
        'csv' => null, // CSV doesn't need a template
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Notifications
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'success_duration' => 10000, // 10 seconds
        'error_duration' => 8000,    // 8 seconds
        'download_button' => true,
    ],
];
