<?php
// app/Http/Middleware/ResolveVillageContext.php - Dynamic domain version

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
        // Get domains from config
        $superAdminDomain = config('pamdes.domains.super_admin');
        $mainDomain = config('pamdes.domains.main');
        $villagePattern = config('pamdes.domains.village_pattern');

        // Super Admin: Configured super admin domain
        if ($host === $superAdminDomain) {
            return [
                'type' => 'super_admin',
                'village' => null,
                'village_id' => null,
                'is_super_admin' => true,
            ];
        }

        // Main PAMDes website: Configured main domain
        if ($host === $mainDomain) {
            return [
                'type' => 'public_website',
                'village' => null,
                'village_id' => null,
                'is_super_admin' => false,
            ];
        }

        // Village-specific websites: Extract pattern and village slug
        $villageSlug = $this->extractVillageSlug($host, $villagePattern);

        if ($villageSlug) {
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

    private function extractVillageSlug(string $host, string $pattern): ?string
    {
        // Convert pattern to regex
        // Example: pamdes-{village}.example.com -> /^pamdes-(.+)\.example\.com$/
        $regex = str_replace(
            ['{village}', '.'],
            ['(.+)', '\.'],
            '/^' . preg_quote($pattern, '/') . '$/'
        );
        $regex = str_replace('\(\.\+\)', '(.+)', $regex);

        if (preg_match($regex, $host, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
