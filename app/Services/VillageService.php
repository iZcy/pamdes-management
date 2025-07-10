<?php
// app/Services/VillageService.php - Independent version

namespace App\Services;

use App\Models\Village;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class VillageService
{
    protected int $freshDataMinutes = 30;

    /**
     * Get village by slug - now only uses local database
     */
    public function getVillageBySlug(string $slug): ?array
    {
        Log::info("VillageService: Getting village by slug: {$slug}");

        $village = Village::bySlug($slug)->first();

        if ($village) {
            Log::info("VillageService: Found village in database for {$slug}");
            return $this->formatVillageData($village);
        }

        Log::warning("VillageService: No village data found for {$slug}");
        return null;
    }

    /**
     * Get village by ID - local database only
     */
    public function getVillageById(string $villageId): ?array
    {
        $village = Village::find($villageId);
        return $village ? $this->formatVillageData($village) : null;
    }

    /**
     * Get all active villages - local database only
     */
    public function getAllVillages(bool $forceRefresh = false): Collection
    {
        return Village::active()->get();
    }

    /**
     * Create or update village data locally
     */
    public function createOrUpdateVillage(array $villageData): Village
    {
        Log::info("VillageService: Creating/updating village", ['village_id' => $villageData['id'] ?? 'new']);

        return Village::updateOrCreate(
            ['id' => $villageData['id'] ?? null],
            [
                'name' => $villageData['name'],
                'slug' => $villageData['slug'],
                'description' => $villageData['description'] ?? null,
                'domain' => $villageData['domain'] ?? null,
                'latitude' => $villageData['latitude'] ?? null,
                'longitude' => $villageData['longitude'] ?? null,
                'phone_number' => $villageData['phone_number'] ?? null,
                'email' => $villageData['email'] ?? null,
                'address' => $villageData['address'] ?? null,
                'image_url' => $villageData['image_url'] ?? null,
                'settings' => $villageData['settings'] ?? [],
                'is_active' => $villageData['is_active'] ?? true,
                'established_at' => isset($villageData['established_at']) ?
                    \Carbon\Carbon::parse($villageData['established_at']) : null,
                'pamdes_settings' => $villageData['pamdes_settings'] ?? [
                    'default_admin_fee' => 5000,
                    'default_maintenance_fee' => 2000,
                    'auto_generate_bills' => true,
                    'overdue_threshold_days' => 30,
                ],
                'sync_enabled' => false, // No external sync
                'last_synced_at' => now(),
            ]
        );
    }

    /**
     * Update PAMDes-specific settings
     */
    public function updatePamdesSettings(string $villageId, array $settings): bool
    {
        $village = Village::find($villageId);
        if (!$village) {
            return false;
        }

        $currentSettings = $village->pamdes_settings ?? [];
        $mergedSettings = array_merge($currentSettings, $settings);

        $village->update(['pamdes_settings' => $mergedSettings]);

        Log::info("VillageService: Updated PAMDes settings for village {$villageId}");
        return true;
    }

    /**
     * Format village data for consistent output
     */
    protected function formatVillageData(Village $village): array
    {
        return [
            'id' => $village->id,
            'village_id' => $village->id, // For backward compatibility
            'name' => $village->name,
            'slug' => $village->slug,
            'description' => $village->description,
            'domain' => $village->domain,
            'latitude' => $village->latitude,
            'longitude' => $village->longitude,
            'phone_number' => $village->phone_number,
            'email' => $village->email,
            'address' => $village->address,
            'image_url' => $village->image_url,
            'settings' => $village->settings ?? [],
            'is_active' => $village->is_active,
            'established_at' => $village->established_at?->toISOString(),
            'pamdes_settings' => $village->pamdes_settings ?? [],
            'sync_enabled' => false, // Always false for independent system
            'last_synced_at' => $village->last_synced_at?->toISOString(),
            'is_data_fresh' => true, // Always fresh since it's local
            'full_name' => $village->full_name,
            'coordinates' => $village->getCoordinates(),
            // Helper accessors for PAMDes
            'default_admin_fee' => $village->getDefaultAdminFee(),
            'default_maintenance_fee' => $village->getDefaultMaintenanceFee(),
            'auto_generate_bills' => $village->isAutoGenerateBillsEnabled(),
        ];
    }

    /**
     * Get villages that need attention (local implementation)
     */
    public function getVillagesNeedingAttention(): Collection
    {
        return Village::where('is_active', true)
            ->whereHas('customers', function ($query) {
                $query->whereHas('bills', function ($billQuery) {
                    $billQuery->where('status', 'overdue');
                });
            })
            ->get();
    }

    /**
     * Clear local cache (simplified)
     */
    public function clearCache(string $slug = null): void
    {
        if ($slug) {
            Cache::forget("village_data_{$slug}");
        }
        Cache::forget('active_villages');
    }

    /**
     * Activate/deactivate village
     */
    public function setVillageStatus(string $villageId, bool $isActive): bool
    {
        $village = Village::find($villageId);
        if (!$village) {
            return false;
        }

        $village->update(['is_active' => $isActive]);
        $this->clearCache($village->slug);

        return true;
    }
}
