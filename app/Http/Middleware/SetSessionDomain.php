<?php
// Update app/Http/Middleware/SetSessionDomain.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class SetSessionDomain
{
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();

        // Always use shared domain for PAMDes system
        // This ensures all domains can share the same session
        if (str_contains($host, 'dev-pamdes.id')) {
            Config::set('session.domain', '.dev-pamdes.id');

            Log::info("Setting shared session domain", [
                'host' => $host,
                'session_domain' => '.dev-pamdes.id',
            ]);
        } else {
            // For other domains, use default behavior
            Log::info("Using default session domain", [
                'host' => $host,
            ]);
        }

        return $next($request);
    }
}
