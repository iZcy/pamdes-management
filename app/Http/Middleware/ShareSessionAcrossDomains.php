<?php
// Create app/Http/Middleware/ShareSessionAcrossDomains.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;

class ShareSessionAcrossDomains
{
    public function handle(Request $request, Closure $next)
    {
        // Process the request
        $response = $next($request);

        $host = $request->getHost();

        // Only apply to PAMDes domains
        if (str_contains($host, 'dev-pamdes.id')) {
            // Get the session cookie from the response
            $sessionCookieName = config('session.cookie', 'laravel_session');

            // Check if we have a session cookie in the response
            foreach ($response->headers->getCookies() as $cookie) {
                if ($cookie->getName() === $sessionCookieName) {
                    // Clone the cookie with the shared domain
                    $sharedCookie = Cookie::make(
                        $cookie->getName(),
                        $cookie->getValue(),
                        $cookie->getExpiresTime(),
                        $cookie->getPath(),
                        '.dev-pamdes.id', // Shared domain
                        $cookie->isSecure(),
                        $cookie->isHttpOnly(),
                        false, // raw
                        $cookie->getSameSite()
                    );

                    // Add the shared cookie to the response
                    $response->headers->setCookie($sharedCookie);

                    Log::info("Setting shared session cookie", [
                        'host' => $host,
                        'cookie_name' => $sessionCookieName,
                        'cookie_domain' => '.dev-pamdes.id',
                        'session_id' => substr($cookie->getValue(), 0, 10) . '...',
                    ]);
                    break;
                }
            }
        }

        return $response;
    }
}
