<?php
// database/seeders/WaterTariffSeeder.php - Fixed to use existing villages

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WaterTariff;
use App\Models\Village;

class WaterTariffSeeder extends Seeder
{
    public function run(): void
    {
        // Create global tariff structure (applies to all villages by default)
        $globalTariffs = [
            ['min' => 0, 'max' => 10, 'price' => 3000],
            ['min' => 11, 'max' => 20, 'price' => 3500],
            ['min' => 21, 'max' => 30, 'price' => 4000],
            ['min' => 31, 'max' => 999, 'price' => 4500],
        ];

        $this->command->info('Creating global water tariffs...');

        foreach ($globalTariffs as $tariff) {
            WaterTariff::create([
                'usage_min' => $tariff['min'],
                'usage_max' => $tariff['max'],
                'price_per_m3' => $tariff['price'],
                'village_id' => null, // Global tariff
                'is_active' => true,
            ]);
        }

        $this->command->info('Created 4 global tariffs');

        // Get existing villages
        $villages = Village::where('is_active', true)->get();

        if ($villages->isEmpty()) {
            $this->command->warn('No active villages found. Only global tariffs created.');
            return;
        }

        // Create village-specific tariffs for some villages (as examples)
        $villageSpecificTariffs = [
            'bayan' => [
                ['min' => 0, 'max' => 10, 'price' => 2500],
                ['min' => 11, 'max' => 20, 'price' => 3000],
                ['min' => 21, 'max' => 30, 'price' => 3500],
                ['min' => 31, 'max' => 999, 'price' => 4000],
            ],
            'senaru' => [
                ['min' => 0, 'max' => 15, 'price' => 2800],
                ['min' => 16, 'max' => 25, 'price' => 3200],
                ['min' => 26, 'max' => 999, 'price' => 3800],
            ]
        ];

        foreach ($villages as $village) {
            if (isset($villageSpecificTariffs[$village->slug])) {
                $this->command->info("Creating village-specific tariffs for: {$village->name}");

                $tariffs = $villageSpecificTariffs[$village->slug];
                foreach ($tariffs as $tariff) {
                    WaterTariff::create([
                        'usage_min' => $tariff['min'],
                        'usage_max' => $tariff['max'],
                        'price_per_m3' => $tariff['price'],
                        'village_id' => $village->id,
                        'is_active' => true,
                    ]);
                }

                $this->command->info("Created " . count($tariffs) . " village-specific tariffs for {$village->name}");
            }
        }

        $totalTariffs = WaterTariff::count();
        $this->command->info("Total water tariffs created: {$totalTariffs}");
        $this->command->info("Global tariffs: " . WaterTariff::whereNull('village_id')->count());
        $this->command->info("Village-specific tariffs: " . WaterTariff::whereNotNull('village_id')->count());
    }
}
