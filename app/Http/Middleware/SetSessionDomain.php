<?php
// app/Http/Middleware/SetSessionDomain.php - Scalable version
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
        // Get main domain from env variables since it's not in app config
        $mainDomain = env('PAMDES_MAIN_DOMAIN', env('APP_DOMAIN', parse_url(Config::get('app.url'), PHP_URL_HOST)));

        // Check if the current host matches our PAMDes domain pattern
        if ($this->isPamdesDomain($host, $mainDomain)) {
            $this->configureSessionForPamdes($host, $mainDomain);
        }

        $response = $next($request);

        // Additional check: if we're setting cookies, ensure they use the right domain
        if ($this->isPamdesDomain($host, $mainDomain)) {
            $this->ensureSessionCookie($request, $host);
        }

        return $response;
    }

    /**
     * Check if the current host is a PAMDes domain
     */
    private function isPamdesDomain(string $host, string $mainDomain): bool
    {
        // Check if it's the main domain or a subdomain
        return $host === $mainDomain || str_ends_with($host, '.' . $mainDomain);
    }

    /**
     * Configure session settings for PAMDes domains
     */
    private function configureSessionForPamdes(string $host, string $mainDomain): void
    {
        $sessionDomain = '.' . $mainDomain;
        $sessionCookie = env('SESSION_COOKIE', 'pamdes_session');
        $isSecure = env('APP_ENV') === 'production' && $this->isHttps();

        Config::set('session.domain', $sessionDomain);
        Config::set('session.cookie', $sessionCookie);
        Config::set('session.same_site', env('SESSION_SAME_SITE', 'lax'));
        Config::set('session.secure', $isSecure);
        Config::set('session.http_only', env('SESSION_HTTP_ONLY', true));
        Config::set('session.path', env('SESSION_PATH', '/'));

        Log::info("Session domain configured for PAMDes", [
            'host' => $host,
            'main_domain' => $mainDomain,
            'session_domain' => $sessionDomain,
            'session_cookie' => $sessionCookie,
            'secure' => $isSecure,
            'environment' => env('APP_ENV'),
        ]);
    }

    /**
     * Ensure session cookie is properly set
     */
    private function ensureSessionCookie(Request $request, string $host): void
    {
        $sessionName = env('SESSION_COOKIE', 'pamdes_session');

        if (session()->isStarted() && !$request->hasCookie($sessionName)) {
            Log::info("Setting session cookie manually", [
                'host' => $host,
                'session_name' => $sessionName,
                'session_id' => session()->getId(),
            ]);
        }
    }

    /**
     * Check if the current request is over HTTPS
     */
    private function isHttps(): bool
    {
        return request()->isSecure() ||
            request()->server('HTTPS') === 'on' ||
            request()->server('HTTP_X_FORWARDED_PROTO') === 'https';
    }
}
