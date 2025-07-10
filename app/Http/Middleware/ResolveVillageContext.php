<?php
// app/Http/Middleware/ResolveVillageContext.php - Correct multi-tenant version

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\VillageService;

class ResolveVillageContext
{
    protected VillageService $villageService;

    public function __construct(VillageService $villageService)
    {
        $this->villageService = $villageService;
    }

    public function handle(Request $request, Closure $next)
    {
        try {
            $host = $request->getHost();
            $tenantContext = $this->resolveTenantContext($host);

            // Set tenant context in request
            $request->attributes->set('tenant_type', $tenantContext['type']);
            $request->attributes->set('village', $tenantContext['village']);
            $request->attributes->set('village_id', $tenantContext['village_id']);
            $request->attributes->set('is_super_admin', $tenantContext['is_super_admin']);

            // Share with views and config
            view()->share('tenantContext', $tenantContext);
            config(['pamdes.tenant' => $tenantContext]);
        } catch (\Exception $e) {
            // Log error but don't break the request
            Log::warning('Failed to resolve tenant context: ' . $e->getMessage(), [
                'host' => $host,
                'path' => $request->getPathInfo(),
            ]);

            // Set safe defaults
            $defaultContext = [
                'type' => 'unknown',
                'village' => null,
                'village_id' => null,
                'is_super_admin' => false,
            ];

            $request->attributes->set('tenant_type', $defaultContext['type']);
            $request->attributes->set('village', $defaultContext['village']);
            $request->attributes->set('village_id', $defaultContext['village_id']);
            $request->attributes->set('is_super_admin', $defaultContext['is_super_admin']);

            view()->share('tenantContext', $defaultContext);
            config(['pamdes.tenant' => $defaultContext]);
        }

        return $next($request);
    }

    private function resolveTenantContext(string $host): array
    {
        // Get app URL for super admin detection
        $appUrl = parse_url(config('app.url'), PHP_URL_HOST);

        // Super Admin: APP_URL host (localhost by default, but configurable)
        if ($host === $appUrl) {
            return [
                'type' => 'super_admin',
                'village' => null,
                'village_id' => null,
                'is_super_admin' => true,
            ];
        }

        // Main PAMDes website: pamdes.local
        if ($host === 'pamdes.local') {
            return [
                'type' => 'public_website',
                'village' => null,
                'village_id' => null,
                'is_super_admin' => false,
            ];
        }

        // Village-specific websites: pamdes-{village}.local
        if (preg_match('/^pamdes-(.+)\.local$/', $host, $matches)) {
            $villageSlug = $matches[1];

            // Get village data by slug
            $village = $this->villageService->getVillageBySlug($villageSlug);

            if ($village) {
                return [
                    'type' => 'village_website',
                    'village' => $village,
                    'village_id' => $village['id'],
                    'is_super_admin' => false,
                ];
            } else {
                Log::warning("Village not found for slug: {$villageSlug}");
                return [
                    'type' => 'village_not_found',
                    'village' => null,
                    'village_id' => null,
                    'is_super_admin' => false,
                ];
            }
        }

        // Unknown domain
        return [
            'type' => 'unknown',
            'village' => null,
            'village_id' => null,
            'is_super_admin' => false,
        ];
    }
}
