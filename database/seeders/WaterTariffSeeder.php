<?php
// database/seeders/WaterTariffSeeder.php - Updated to ensure minimum 3 ranges per village

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Village;
use App\Services\TariffRangeService;

class WaterTariffSeeder extends Seeder
{
    public function run(): void
    {
        // Get existing villages
        $villages = Village::where('is_active', true)->get();

        if ($villages->isEmpty()) {
            $this->command->error('No active villages found. Please run VillageSeeder first.');
            return;
        }

        $service = app(TariffRangeService::class);

        // Create village-specific tariffs for each village using smart service
        foreach ($villages as $village) {
            $this->command->info("Creating smart tariff structure for: {$village->name}");

            // Ensure each village has at least 3 ranges with good variation
            $tariffRanges = [];

            // Customize tariffs for specific villages but ensure minimum 3 ranges
            if ($village->slug === 'bayan') {
                $tariffRanges = [
                    ['start' => 0, 'price' => 2500],   // 0-10 m³
                    ['start' => 11, 'price' => 3000],  // 11-20 m³
                    ['start' => 21, 'price' => 3500],  // 21-30 m³
                    ['start' => 31, 'price' => 4000],  // 31+ m³
                ];
            } elseif ($village->slug === 'senaru') {
                $tariffRanges = [
                    ['start' => 0, 'price' => 2800],   // 0-15 m³
                    ['start' => 16, 'price' => 3200],  // 16-25 m³
                    ['start' => 26, 'price' => 3800],  // 26+ m³
                ];
            } elseif ($village->slug === 'pemenang') {
                $tariffRanges = [
                    ['start' => 0, 'price' => 3000],   // 0-12 m³
                    ['start' => 13, 'price' => 3500],  // 13-22 m³
                    ['start' => 23, 'price' => 4000],  // 23+ m³
                ];
            } else {
                // Default structure for other villages (minimum 3 ranges)
                $tariffRanges = [
                    ['start' => 0, 'price' => 2500],   // 0-10 m³
                    ['start' => 11, 'price' => 3000],  // 11-20 m³
                    ['start' => 21, 'price' => 3500],  // 21+ m³
                ];
            }

            // Ensure we have at least 3 ranges
            if (count($tariffRanges) < 3) {
                $this->command->warn("Village {$village->name} has less than 3 ranges, adding defaults");
                $tariffRanges = [
                    ['start' => 0, 'price' => 2500],
                    ['start' => 11, 'price' => 3000],
                    ['start' => 21, 'price' => 3500],
                ];
            }

            // Create tariffs using the smart service
            $createdCount = 0;
            foreach ($tariffRanges as $range) {
                try {
                    $tariff = $service->createTariffRange(
                        $village->id,
                        $range['start'],
                        $range['price']
                    );

                    $this->command->info("  ✓ Created range: {$tariff->usage_range} - Rp " . number_format($tariff->price_per_m3));
                    $createdCount++;
                } catch (\Exception $e) {
                    $this->command->error("  ✗ Failed to create range starting at {$range['start']}: " . $e->getMessage());
                }
            }

            $this->command->info("Created {$createdCount} smart tariff ranges for {$village->name}");

            // Validate that we have at least 3 ranges
            $actualRanges = \App\Models\WaterTariff::where('village_id', $village->id)->count();
            if ($actualRanges < 3) {
                $this->command->error("WARNING: {$village->name} only has {$actualRanges} tariff ranges!");
            } else {
                $this->command->info("✓ {$village->name} has {$actualRanges} tariff ranges (minimum requirement met)");
            }
        }

        $totalTariffs = \App\Models\WaterTariff::count();
        $this->command->info("Total water tariffs created: {$totalTariffs}");
        $this->command->info("All tariffs use smart range management (no conflicts, auto-adjustment)");

        // Show final tariff summary by village
        $this->command->info('');
        $this->command->info('📊 FINAL TARIFF STRUCTURE BY VILLAGE:');
        $this->command->info('==========================================');

        foreach ($villages as $village) {
            $this->command->info("🏘️  {$village->name}:");

            try {
                $villageTariffs = $service->getVillageTariffs($village->id);

                if (empty($villageTariffs)) {
                    $this->command->warn("   No tariffs found");
                    continue;
                }

                foreach ($villageTariffs as $tariff) {
                    $editableInfo = [];
                    if ($tariff['editable_fields']['can_edit_min']) $editableInfo[] = 'min';
                    if ($tariff['editable_fields']['can_edit_max']) $editableInfo[] = 'max';
                    $editableText = !empty($editableInfo) ? ' (editable: ' . implode(', ', $editableInfo) . ')' : '';

                    $this->command->info("   📋 {$tariff['range_display']}: Rp " . number_format($tariff['price_per_m3']) . $editableText);
                }
            } catch (\Exception $e) {
                $this->command->error("   Error getting tariffs: " . $e->getMessage());
            }

            $this->command->info('');
        }

        // Show example calculations for each village
        $this->command->info('🧮 EXAMPLE CALCULATIONS:');
        $this->command->info('========================');

        foreach ($villages as $village) {
            $this->command->info("📍 Example for {$village->name}:");

            try {
                $usageExamples = [8, 15, 25, 35];

                foreach ($usageExamples as $usage) {
                    $calculation = \App\Models\WaterTariff::calculateBill($usage, $village->id);

                    $breakdown = [];
                    foreach ($calculation['breakdown'] as $tier) {
                        $breakdown[] = "{$tier['usage']} × Rp" . number_format($tier['rate']);
                    }

                    $this->command->info("   💧 {$usage} m³: " . implode(' + ', $breakdown) . " = Rp " . number_format($calculation['total_charge']));
                }
            } catch (\Exception $e) {
                $this->command->error("   Error calculating examples: " . $e->getMessage());
            }
            $this->command->info('');
        }

        $this->command->info('✅ Smart tariff seeding completed successfully!');
        $this->command->info('🎯 Features enabled:');
        $this->command->info('   • Minimum 3 ranges per village');
        $this->command->info('   • No range conflicts');
        $this->command->info('   • Auto-adjustment on edits');
        $this->command->info('   • Smart validation rules');
        $this->command->info('   • Infinite tier support');
    }
}
