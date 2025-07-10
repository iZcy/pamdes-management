<?php
// app/Http/Middleware/ResolveVillageContext.php - Make village optional

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
        $host = $request->getHost();
        $village = $this->getVillageFromHost($host);

        // Always set village context (null is acceptable)
        $request->attributes->set('village', $village);
        $request->attributes->set('village_id', $village['id'] ?? null);

        // Share with views (null-safe)
        view()->share('currentVillage', $village);
        config(['pamdes.current_village' => $village]);

        return $next($request);
    }

    private function getVillageFromHost(string $host): ?array
    {
        // Only resolve village for subdomain routing
        if (str_contains($host, 'pamdes.')) {
            $hostParts = explode('.', $host);
            if (count($hostParts) >= 3 && $hostParts[0] === 'pamdes') {
                $villageSlug = $hostParts[1];
                return $this->villageService->getVillageBySlug($villageSlug);
            }
        }

        // For localhost/admin access, no village required
        return null;
    }
}
