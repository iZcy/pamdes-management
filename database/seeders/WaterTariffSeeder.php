<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WaterTariff;

class WaterTariffSeeder extends Seeder
{
    public function run(): void
    {
        // Global tariff structure (can be overridden per village)
        $tariffs = [
            ['min' => 0, 'max' => 10, 'price' => 3000],
            ['min' => 11, 'max' => 20, 'price' => 3500],
            ['min' => 21, 'max' => 30, 'price' => 4000],
            ['min' => 31, 'max' => 999, 'price' => 4500],
        ];

        foreach ($tariffs as $tariff) {
            WaterTariff::create([
                'usage_min' => $tariff['min'],
                'usage_max' => $tariff['max'],
                'price_per_m3' => $tariff['price'],
                'village_id' => null, // Global tariff
                'is_active' => true,
            ]);
        }

        // Example village-specific tariff for Bayan
        $bayanTariffs = [
            ['min' => 0, 'max' => 10, 'price' => 2500],
            ['min' => 11, 'max' => 20, 'price' => 3000],
            ['min' => 21, 'max' => 30, 'price' => 3500],
            ['min' => 31, 'max' => 999, 'price' => 4000],
        ];

        foreach ($bayanTariffs as $tariff) {
            WaterTariff::create([
                'usage_min' => $tariff['min'],
                'usage_max' => $tariff['max'],
                'price_per_m3' => $tariff['price'],
                'village_id' => 'bayan-village-id', // Specific to Bayan village
                'is_active' => true,
            ]);
        }
    }
}
