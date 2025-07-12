<?php
// Update config/export.php - Complete export configuration

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
        'font_size' => env('EXPORT_PDF_FONT_SIZE', '10px'),
        'margins' => [
            'top' => env('EXPORT_PDF_MARGIN_TOP', '15mm'),
            'bottom' => env('EXPORT_PDF_MARGIN_BOTTOM', '15mm'),
            'left' => env('EXPORT_PDF_MARGIN_LEFT', '10mm'),
            'right' => env('EXPORT_PDF_MARGIN_RIGHT', '10mm'),
        ],
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
        'encoding' => env('EXPORT_CSV_ENCODING', 'UTF-8'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_records' => env('EXPORT_MAX_RECORDS', 50000),
        'memory_limit' => env('EXPORT_MEMORY_LIMIT', '1024M'),
        'time_limit' => env('EXPORT_TIME_LIMIT', 300), // 5 minutes
        'chunk_size' => env('EXPORT_CHUNK_SIZE', 1000), // Process in chunks
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
    | Export Column Mappings
    |--------------------------------------------------------------------------
    */
    'column_mappings' => [
        'bills' => [
            'customer_code' => 'Kode Pelanggan',
            'customer_name' => 'Nama Pelanggan',
            'village_name' => 'Desa',
            'period_name' => 'Periode',
            'usage_m3' => 'Pemakaian (m³)',
            'water_charge' => 'Biaya Air',
            'admin_fee' => 'Biaya Admin',
            'maintenance_fee' => 'Biaya Pemeliharaan',
            'total_amount' => 'Total Tagihan',
            'status' => 'Status',
            'due_date' => 'Jatuh Tempo',
            'payment_date' => 'Tanggal Bayar',
            'payment_method' => 'Metode Bayar',
            'created_at' => 'Dibuat',
        ],
        'customers' => [
            'customer_code' => 'Kode Pelanggan',
            'name' => 'Nama',
            'phone_number' => 'Telepon',
            'address' => 'Alamat',
            'village_name' => 'Desa',
            'status' => 'Status',
            'created_at' => 'Terdaftar',
            'rt' => 'RT',
            'rw' => 'RW',
            'village' => 'Kelurahan',
        ],
        'payments' => [
            'payment_date' => 'Tanggal Bayar',
            'customer_code' => 'Kode Pelanggan',
            'customer_name' => 'Nama Pelanggan',
            'village_name' => 'Desa',
            'period_name' => 'Periode',
            'amount_paid' => 'Jumlah Bayar',
            'change_given' => 'Kembalian',
            'payment_method' => 'Metode',
            'collector_name' => 'Petugas',
            'payment_reference' => 'Referensi',
        ],
        'water_usage' => [
            'customer_code' => 'Kode Pelanggan',
            'customer_name' => 'Nama Pelanggan',
            'village_name' => 'Desa',
            'period_name' => 'Periode',
            'usage_date' => 'Tanggal Baca',
            'initial_meter' => 'Meter Awal',
            'final_meter' => 'Meter Akhir',
            'total_usage_m3' => 'Pemakaian (m³)',
            'reader_name' => 'Petugas Baca',
            'notes' => 'Catatan',
        ],
        'water_tariffs' => [
            'village_name' => 'Desa',
            'usage_range' => 'Rentang Pemakaian',
            'price_per_m3' => 'Harga per m³',
            'is_active' => 'Status',
            'created_at' => 'Dibuat',
        ],
        'billing_periods' => [
            'village_name' => 'Desa',
            'period_name' => 'Periode',
            'status' => 'Status',
            'reading_start_date' => 'Mulai Baca',
            'reading_end_date' => 'Selesai Baca',
            'billing_due_date' => 'Jatuh Tempo',
            'total_customers' => 'Jumlah Pelanggan',
            'total_billed' => 'Total Tagihan',
            'collection_rate' => 'Tingkat Penagihan',
        ],
        'villages' => [
            'name' => 'Nama Desa',
            'slug' => 'Slug',
            'phone_number' => 'Telepon',
            'email' => 'Email',
            'address' => 'Alamat',
            'customers_count' => 'Jumlah Pelanggan',
            'is_active' => 'Status',
            'established_at' => 'Didirikan',
        ],
        'users' => [
            'name' => 'Nama',
            'email' => 'Email',
            'role' => 'Role',
            'villages' => 'Desa',
            'contact_info' => 'Kontak',
            'is_active' => 'Status',
            'created_at' => 'Terdaftar',
        ],
        'variables' => [
            'village_name' => 'Desa',
            'tripay_use_main' => 'Gunakan Config Global',
            'tripay_is_production' => 'Mode Produksi',
            'tripay_timeout_minutes' => 'Timeout (Menit)',
            'configuration_status' => 'Status Konfigurasi',
            'updated_at' => 'Terakhir Diupdate',
        ],
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
        'auto_download' => env('EXPORT_AUTO_DOWNLOAD', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Export File Naming
    |--------------------------------------------------------------------------
    */
    'file_naming' => [
        'pattern' => '{title}_{village}_{timestamp}',
        'timestamp_format' => 'Y-m-d_H-i-s',
        'sanitize_filename' => true,
        'max_filename_length' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        'include_summary' => env('EXPORT_INCLUDE_SUMMARY', true),
        'include_filters' => env('EXPORT_INCLUDE_FILTERS', true),
        'include_timestamps' => env('EXPORT_INCLUDE_TIMESTAMPS', true),
        'watermark' => env('EXPORT_WATERMARK', 'PAMDes Management System'),
        'show_village_info' => env('EXPORT_SHOW_VILLAGE_INFO', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Security
    |--------------------------------------------------------------------------
    */
    'security' => [
        'require_auth' => env('EXPORT_REQUIRE_AUTH', true),
        'log_exports' => env('EXPORT_LOG_EXPORTS', true),
        'rate_limit' => env('EXPORT_RATE_LIMIT', '10,1'), // 10 exports per minute
    ],
];
