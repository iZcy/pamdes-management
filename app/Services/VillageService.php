<?php
// app/Services/VillageService.php - Simplified version

namespace App\Services;

use App\Models\Village;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VillageService
{
    public function getVillageBySlug(string $slug): ?array
    {
        $village = Village::where('slug', $slug)->where('is_active', true)->first();

        if ($village) {
            return $this->formatVillageData($village);
        }

        return null;
    }

    public function getVillageById(string $villageId): ?array
    {
        $village = Village::where('id', $villageId)->where('is_active', true)->first();

        if ($village) {
            return $this->formatVillageData($village);
        }

        return null;
    }

    public function getDefaultVillage(): ?array
    {
        $village = Village::where('is_active', true)->first();

        if ($village) {
            return $this->formatVillageData($village);
        }

        return null;
    }

    public function getAllActiveVillages()
    {
        return Village::where('is_active', true)->get();
    }

    public function createVillage(array $data): Village
    {
        return Village::create(array_merge($data, [
            'id' => Str::uuid()->toString(),
            'is_active' => true,
            'established_at' => now(),
            'pamdes_settings' => [
                'default_admin_fee' => 5000,
                'default_maintenance_fee' => 2000,
                'auto_generate_bills' => true,
                'overdue_threshold_days' => 30,
            ],
        ]));
    }

    protected function formatVillageData(Village $village): array
    {
        return [
            'id' => $village->id,
            'name' => $village->name,
            'slug' => $village->slug,
            'description' => $village->description,
            'phone_number' => $village->phone_number,
            'email' => $village->email,
            'address' => $village->address,
            'is_active' => $village->is_active,
            'pamdes_settings' => $village->pamdes_settings ?? [],
            'default_admin_fee' => $village->getDefaultAdminFee(),
            'default_maintenance_fee' => $village->getDefaultMaintenanceFee(),
        ];
    }
}
