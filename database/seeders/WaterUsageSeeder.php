<?php
// database/seeders/WaterUsageSeeder.php - Fixed to use existing data

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WaterUsage;
use App\Models\Customer;
use App\Models\BillingPeriod;
use App\Models\Village;

class WaterUsageSeeder extends Seeder
{
    public function run(): void
    {
        $villages = Village::where('is_active', true)->get();

        if ($villages->isEmpty()) {
            $this->command->error('No active villages found. Please run VillageSeeder first.');
            return;
        }

        $totalUsages = 0;

        foreach ($villages as $village) {
            $this->command->info("Creating water usages for village: {$village->name}");

            // Get customers for this village
            $customers = Customer::where('village_id', $village->id)
                ->where('status', 'active')
                ->get();

            if ($customers->isEmpty()) {
                $this->command->warn("No active customers found for {$village->name}. Skipping...");
                continue;
            }

            // Get billing periods for this village
            $periods = BillingPeriod::where('village_id', $village->id)
                ->where('status', '!=', 'inactive')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->get();

            if ($periods->isEmpty()) {
                $this->command->warn("No active billing periods found for {$village->name}. Skipping...");
                continue;
            }

            $villageUsages = 0;

            foreach ($customers as $customer) {
                $lastMeterReading = rand(100, 500); // Starting meter reading

                foreach ($periods as $period) {
                    // Generate realistic usage (5-50 m³ per month)
                    $usage = rand(5, 50);
                    $initialMeter = $lastMeterReading;
                    $finalMeter = $initialMeter + $usage;

                    // Random date within the reading period
                    $usageDate = $period->reading_start_date
                        ? $period->reading_start_date->copy()->addDays(rand(1, 15))
                        : now()->subDays(rand(1, 30));

                    WaterUsage::create([
                        'customer_id' => $customer->customer_id,
                        'period_id' => $period->period_id,
                        'initial_meter' => $initialMeter,
                        'final_meter' => $finalMeter,
                        'total_usage_m3' => $usage,
                        'usage_date' => $usageDate,
                        'reader_name' => fake()->name(),
                        'notes' => fake()->optional(0.3)->sentence(),
                    ]);

                    $lastMeterReading = $finalMeter;
                    $villageUsages++;
                }
            }

            $this->command->info("Created {$villageUsages} water usages for {$village->name}");
            $totalUsages += $villageUsages;
        }

        $this->command->info("Total water usages created: {$totalUsages}");

        // Show summary by village
        foreach ($villages as $village) {
            $villageUsageCount = WaterUsage::whereHas('customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->count();

            $this->command->info("- {$village->name}: {$villageUsageCount} usages");
        }
    }
}
