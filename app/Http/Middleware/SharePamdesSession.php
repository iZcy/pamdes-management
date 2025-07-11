<?php
// Create app/Http/Middleware/SharePamdesSession.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SharePamdesSession
{
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();

        // Only apply to PAMDes domains
        if (str_contains($host, 'dev-pamdes.id')) {
            $sessionCookieName = config('session.cookie', 'pamdes_session');

            // Check if we have a session cookie from another PAMDes domain
            if ($request->hasCookie($sessionCookieName)) {
                $sessionId = $request->cookie($sessionCookieName);

                // Start the session with the existing session ID
                if ($sessionId) {
                    session()->setId($sessionId);
                    Log::info("Using existing PAMDes session", [
                        'host' => $host,
                        'session_id' => substr($sessionId, 0, 10) . '...',
                    ]);
                }
            }
        }

        $response = $next($request);

        // After processing, set the cookie for the shared domain
        if (str_contains($host, 'dev-pamdes.id')) {
            $sessionId = session()->getId();

            // Set cookie for the current domain AND the shared domain
            $response->withCookie(cookie(
                config('session.cookie', 'pamdes_session'),
                $sessionId,
                config('session.lifetime', 120),
                '/',
                '.dev-pamdes.id', // Shared domain
                config('session.secure', false),
                config('session.http_only', true),
                false,
                config('session.same_site', 'lax')
            ));

            Log::info("Setting shared PAMDes session cookie", [
                'host' => $host,
                'session_id' => substr($sessionId, 0, 10) . '...',
                'domain' => '.dev-pamdes.id',
            ]);
        }

        return $response;
    }
}
