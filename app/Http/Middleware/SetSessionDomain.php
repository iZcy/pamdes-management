<?php
// app/Http/Middleware/SetSessionDomain.php - Enhanced version

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

        // Set session domain for PAMDes system
        if (str_contains($host, 'dev-pamdes.id')) {
            // Force session domain for all PAMDes subdomains
            Config::set('session.domain', '.dev-pamdes.id');
            Config::set('session.cookie', 'pamdes_session');
            Config::set('session.same_site', 'lax');
            Config::set('session.secure', false); // Set to true in production with HTTPS
            Config::set('session.http_only', true);
            Config::set('session.path', '/');

            Log::info("Session domain configured for PAMDes", [
                'host' => $host,
                'session_domain' => '.dev-pamdes.id',
                'session_cookie' => 'pamdes_session',
            ]);
        }

        $response = $next($request);

        // Additional check: if we're setting cookies, ensure they use the right domain
        if (str_contains($host, 'dev-pamdes.id')) {
            // This ensures the session cookie is set with the correct domain
            $sessionName = Config::get('session.cookie', 'pamdes_session');

            if (session()->isStarted() && !$request->hasCookie($sessionName)) {
                Log::info("Setting session cookie manually", [
                    'host' => $host,
                    'session_name' => $sessionName,
                    'session_id' => session()->getId(),
                ]);
            }
        }

        return $response;
    }
}
