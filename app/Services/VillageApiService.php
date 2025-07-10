<?php

// app/Services/VillageApiService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class VillageApiService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.village_system.url');
        $this->apiKey = config('services.village_system.api_key');
        $this->timeout = config('services.village_system.timeout', 10);
    }

    /**
     * Get village data by slug from the main village system
     */
    public function getVillageBySlug(string $slug): ?array
    {
        $cacheKey = "village_data_{$slug}";

        return Cache::remember($cacheKey, 300, function () use ($slug) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ])
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/api/villages/slug/{$slug}");

                if ($response->successful()) {
                    return $response->json('data');
                }

                Log::warning('Failed to fetch village data', [
                    'slug' => $slug,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return null;
            } catch (\Exception $e) {
                Log::error('Village API error', [
                    'slug' => $slug,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Get village data by ID from the main village system
     */
    public function getVillageById(string $villageId): ?array
    {
        $cacheKey = "village_data_id_{$villageId}";

        return Cache::remember($cacheKey, 300, function () use ($villageId) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ])
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/api/villages/{$villageId}");

                if ($response->successful()) {
                    return $response->json('data');
                }

                Log::warning('Failed to fetch village data by ID', [
                    'village_id' => $villageId,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return null;
            } catch (\Exception $e) {
                Log::error('Village API error by ID', [
                    'village_id' => $villageId,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Get all active villages
     */
    public function getActiveVillages(): array
    {
        $cacheKey = "active_villages";

        return Cache::remember($cacheKey, 600, function () {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ])
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/api/villages?status=active");

                if ($response->successful()) {
                    return $response->json('data') ?? [];
                }

                return [];
            } catch (\Exception $e) {
                Log::error('Failed to fetch active villages', [
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Send PAMDes summary data to village system
     */
    public function sendPamdesSummary(string $villageId, array $summaryData): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/api/villages/{$villageId}/pamdes-summary", $summaryData);

            if ($response->successful()) {
                Log::info('PAMDes summary sent successfully', [
                    'village_id' => $villageId,
                    'data' => $summaryData,
                ]);
                return true;
            }

            Log::warning('Failed to send PAMDes summary', [
                'village_id' => $villageId,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Error sending PAMDes summary', [
                'village_id' => $villageId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Notify village system about important PAMDes events
     */
    public function sendNotification(string $villageId, string $type, array $data): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/api/villages/{$villageId}/notifications", [
                    'type' => $type,
                    'source' => 'pamdes',
                    'data' => $data,
                    'timestamp' => now()->toISOString(),
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Error sending notification to village system', [
                'village_id' => $villageId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Validate user token with village system
     */
    public function validateUserToken(string $token): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/api/auth/validate");

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error validating user token', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get village settings and preferences
     */
    public function getVillageSettings(string $villageId): array
    {
        $cacheKey = "village_settings_{$villageId}";

        return Cache::remember($cacheKey, 1800, function () use ($villageId) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ])
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/api/villages/{$villageId}/settings");

                if ($response->successful()) {
                    return $response->json('data') ?? [];
                }

                return [];
            } catch (\Exception $e) {
                Log::error('Failed to fetch village settings', [
                    'village_id' => $villageId,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Clear village cache
     */
    public function clearVillageCache(string $slug = null, string $villageId = null): void
    {
        if ($slug) {
            Cache::forget("village_data_{$slug}");
        }

        if ($villageId) {
            Cache::forget("village_data_id_{$villageId}");
            Cache::forget("village_settings_{$villageId}");
        }

        if (!$slug && !$villageId) {
            Cache::forget('active_villages');
        }
    }

    /**
     * Batch get villages by multiple IDs
     */
    public function getVillagesByIds(array $villageIds): array
    {
        $villages = [];

        foreach ($villageIds as $villageId) {
            $village = $this->getVillageById($villageId);
            if ($village) {
                $villages[$villageId] = $village;
            }
        }

        return $villages;
    }

    /**
     * Check if village exists and is active
     */
    public function isVillageActive(string $villageId): bool
    {
        $village = $this->getVillageById($villageId);
        return $village && ($village['status'] ?? '') === 'active';
    }

    /**
     * Get village name by ID (utility method)
     */
    public function getVillageName(string $villageId): string
    {
        $village = $this->getVillageById($villageId);
        return $village['name'] ?? 'Unknown Village';
    }
}
