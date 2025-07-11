<?php
// Alternative: Update config/session.php to conditionally set domain

return [
    'driver' => env('SESSION_DRIVER', 'database'),
    'lifetime' => env('SESSION_LIFETIME', 120),
    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),
    'encrypt' => env('SESSION_ENCRYPT', false),
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION'),
    'table' => env('SESSION_TABLE', 'sessions'),
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', 'pamdes_session'),
    'path' => env('SESSION_PATH', '/'),

    // Conditional domain setting
    'domain' => env('SESSION_DOMAIN', function () {
        // Only use shared domain for village subdomains
        $host = request()?->getHost() ?? '';

        if (preg_match('/^pamdes-[^.]+\.dev-pamdes\.id$/', $host)) {
            return '.dev-pamdes.id'; // Shared for village subdomains
        }

        return null; // Default for main domain
    }),

    'secure' => env('SESSION_SECURE_COOKIE'),
    'http_only' => env('SESSION_HTTP_ONLY', true),
    'same_site' => env('SESSION_SAME_SITE', 'lax'),
    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),
];
