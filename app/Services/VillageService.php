<?php
// app/Services/VillageService.php

namespace App\Services;

use App\Models\Village;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class VillageService
{
    protected VillageApiService $apiService;
    protected int $freshDataMinutes = 30;

    public function __construct(VillageApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    /**
     * Get village by slug with hybrid database + API approach
     */
    public function getVillageBySlug(string $slug): ?array
    {
        Log::info("VillageService: Getting village by slug: {$slug}");

        // 1. Try database first
        $village = Village::bySlug($slug)->first();

        // 2. Return fresh data from database
        if ($village && $village->is_data_fresh) {
            Log::info("VillageService: Returning fresh data from database for {$slug}");
            return $this->formatVillageData($village);
        }

        // 3. Try API for fresh data
        if ($village?->sync_enabled !== false) {
            try {
                Log::info("VillageService: Fetching fresh data from API for {$slug}");
                $apiData = $this->apiService->getVillageBySlug($slug);

                if ($apiData) {
                    // Save to database
                    $village = $this->syncVillageData($apiData);
                    Log::info("VillageService: Successfully synced data from API for {$slug}");
                    return $this->formatVillageData($village);
                }
            } catch (\Exception $e) {
                Log::warning("VillageService: API failed for {$slug}: " . $e->getMessage());
            }
        }

        // 4. Fallback to stale database data
        if ($village) {
            Log::info("VillageService: Returning stale data from database for {$slug}");
            return $this->formatVillageData($village);
        }

        Log::warning("VillageService: No village data found for {$slug}");
        return null;
    }

    /**
     * Get village by ID
     */
    public function getVillageById(string $villageId): ?array
    {
        $village = Village::find($villageId);

        if (!$village || !$village->is_data_fresh) {
            $this->refreshVillageData($villageId);
            $village = Village::find($villageId);
        }

        return $village ? $this->formatVillageData($village) : null;
    }

    /**
     * Get all active villages
     */
    public function getAllVillages(bool $forceRefresh = false): Collection
    {
        if ($forceRefresh) {
            $this->syncAllVillages();
        }

        return Village::active()->get();
    }

    /**
     * Sync village data from API to database (updated to match main system structure)
     */
    public function syncVillageData(array $apiData): Village
    {
        Log::info("VillageService: Syncing village data", ['village_id' => $apiData['id'] ?? 'unknown']);

        // Extract existing PAMDes settings if village exists
        $existingVillage = Village::find($apiData['id']);
        $existingPamdesSettings = $existingVillage?->pamdes_settings ?? [];

        return Village::updateOrCreate(
            ['id' => $apiData['id']],
            [
                'name' => $apiData['name'],
                'slug' => $apiData['slug'],
                'description' => $apiData['description'] ?? null,
                'domain' => $apiData['domain'] ?? null,
                'latitude' => $apiData['latitude'] ?? null,
                'longitude' => $apiData['longitude'] ?? null,
                'phone_number' => $apiData['phone_number'] ?? null,
                'email' => $apiData['email'] ?? null,
                'address' => $apiData['address'] ?? null,
                'image_url' => $apiData['image_url'] ?? null,
                'settings' => $apiData['settings'] ?? [],
                'is_active' => $apiData['is_active'] ?? true,
                'established_at' => isset($apiData['established_at']) ?
                    \Carbon\Carbon::parse($apiData['established_at']) : null,
                'pamdes_settings' => array_merge(
                    $existingPamdesSettings,
                    $apiData['pamdes_settings'] ?? []
                ),
                'sync_enabled' => $apiData['sync_enabled'] ?? true,
                'last_synced_at' => now(),
            ]
        );
    }

    /**
     * Sync all villages from API
     */
    public function syncAllVillages(): int
    {
        try {
            $apiVillages = $this->apiService->getActiveVillages();
            $syncedCount = 0;

            foreach ($apiVillages as $villageData) {
                $this->syncVillageData($villageData);
                $syncedCount++;
            }

            Log::info("VillageService: Synced {$syncedCount} villages from API");
            return $syncedCount;
        } catch (\Exception $e) {
            Log::error("VillageService: Failed to sync all villages: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Refresh specific village data
     */
    public function refreshVillageData(string $villageId): bool
    {
        try {
            // Try to get fresh data from API by ID or slug
            $village = Village::find($villageId);
            if (!$village) {
                return false;
            }

            $apiData = $this->apiService->getVillageBySlug($village->slug);
            if ($apiData) {
                $this->syncVillageData($apiData);
                return true;
            }
        } catch (\Exception $e) {
            Log::warning("VillageService: Failed to refresh village {$villageId}: " . $e->getMessage());
        }

        return false;
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
     * Format village data for consistent output (updated structure)
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
            'sync_enabled' => $village->sync_enabled,
            'last_synced_at' => $village->last_synced_at?->toISOString(),
            'is_data_fresh' => $village->is_data_fresh,
            'full_name' => $village->full_name,
            'coordinates' => $village->getCoordinates(),
            // Helper accessors for PAMDes
            'default_admin_fee' => $village->getDefaultAdminFee(),
            'default_maintenance_fee' => $village->getDefaultMaintenanceFee(),
            'auto_generate_bills' => $village->isAutoGenerateBillsEnabled(),
        ];
    }

    // Additional helper methods...
    public function getStaleVillages(): Collection
    {
        return Village::stale($this->freshDataMinutes)
            ->syncEnabled()
            ->get();
    }

    public function clearCache(string $slug = null): void
    {
        if ($slug) {
            Cache::forget("village_data_{$slug}");
        } else {
            Cache::forget('active_villages');
        }

        $this->apiService->clearVillageCache($slug);
    }
}
