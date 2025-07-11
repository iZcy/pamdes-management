<?php
// app/Http/Middleware/SetVillageContext.php - Working version for your domains

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\VillageService;
use Illuminate\Support\Facades\Log;

class SetVillageContext
{
    protected VillageService $villageService;

    public function __construct(VillageService $villageService)
    {
        $this->villageService = $villageService;
    }

    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();
        $villageId = null;
        $village = null;
        $isSuperAdmin = false;

        Log::info("SetVillageContext: Processing host: {$host}");

        // Extract village from domain pattern
        $villageSlug = $this->extractVillageSlug($host);

        if ($villageSlug) {
            Log::info("SetVillageContext: Found village slug: {$villageSlug}");
            $villageData = $this->villageService->getVillageBySlug($villageSlug);
            if ($villageData) {
                $village = $villageData;
                $villageId = $villageData['id'];
                Log::info("SetVillageContext: Found village: {$village['name']}");
            }
        } else {
            // Main domain or development fallback
            Log::info("SetVillageContext: No village slug found, using default village");
            $villageData = $this->villageService->getDefaultVillage();
            if ($villageData) {
                $village = $villageData;
                $villageId = $villageData['id'];
            }
        }

        // Set context in config for easy access
        config([
            'pamdes.current_village' => $village,
            'pamdes.current_village_id' => $villageId,
            'pamdes.is_super_admin_domain' => $isSuperAdmin,
        ]);

        // Share with views
        view()->share([
            'currentVillage' => $village,
            'currentVillageId' => $villageId,
            'isSuperAdminDomain' => $isSuperAdmin,
        ]);

        Log::info("SetVillageContext: Context set", [
            'village_id' => $villageId,
            'village_name' => $village['name'] ?? 'None',
        ]);

        return $next($request);
    }

    private function extractVillageSlug(string $host): ?string
    {
        // Get the village pattern from config
        $pattern = config('pamdes.domains.village_pattern', env('PAMDES_VILLAGE_DOMAIN_PATTERN'));

        Log::info("SetVillageContext: Using pattern: {$pattern}");

        if (!$pattern || !str_contains($pattern, '{village}')) {
            return null;
        }

        // Convert pattern to regex
        // Example: pamdes-{village}.dev-pamdes.id -> /^pamdes-(.+)\.dev-pamdes\.id$/
        $regex = str_replace(
            ['{village}', '.', '-'],
            ['([^.-]+)', '\.', '\-'],
            $pattern
        );
        $regex = '/^' . $regex . '$/';

        Log::info("SetVillageContext: Using regex: {$regex}");

        if (preg_match($regex, $host, $matches)) {
            Log::info("SetVillageContext: Regex matched", ['matches' => $matches]);
            return $matches[1] ?? null;
        }

        Log::info("SetVillageContext: No regex match for host: {$host}");
        return null;
    }
}
