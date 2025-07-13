<?php
// database/seeders/WaterUsageSeeder.php - Updated with more realistic usage patterns

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WaterUsage;
use App\Models\Customer;
use App\Models\BillingPeriod;
use App\Models\User;
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

            // Create usage patterns that will work well with tariff ranges
            foreach ($customers as $customer) {
                $lastMeterReading = rand(100, 500); // Starting meter reading

                // Assign customer type for realistic usage patterns
                $customerType = $this->getCustomerType();

                foreach ($periods as $period) {
                    // Generate realistic usage based on customer type and tariff ranges
                    $usage = $this->generateRealisticUsage($customerType);

                    $initialMeter = $lastMeterReading;
                    $finalMeter = $initialMeter + $usage;

                    // Get available readers (operators) for this village
                    $readers = User::whereHas('villages', function ($q) use ($village) {
                        $q->where('villages.id', $village->id);
                    })
                        ->whereIn('role', ['operator', 'village_admin'])
                        ->where('is_active', true)
                        ->get();

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
                        'reader_id' => $readers->isNotEmpty() ? $readers->random()->id : null,
                        'notes' => fake()->optional(0.3)->sentence(),
                    ]);

                    $lastMeterReading = $finalMeter;
                    $villageUsages++;
                }
            }

            $this->command->info("Created {$villageUsages} water usages for {$village->name}");
            $totalUsages += $villageUsages;

            // Show usage distribution for this village
            $this->showUsageDistribution($village);
        }

        $this->command->info("Total water usages created: {$totalUsages}");

        // Show overall summary
        $this->command->info('');
        $this->command->info('📊 Usage Summary by Village:');
        foreach ($villages as $village) {
            $villageUsageCount = WaterUsage::whereHas('customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->count();

            $avgUsage = WaterUsage::whereHas('customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->avg('total_usage_m3');

            $minUsage = WaterUsage::whereHas('customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->min('total_usage_m3');

            $maxUsage = WaterUsage::whereHas('customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->max('total_usage_m3');

            $this->command->info("- {$village->name}: {$villageUsageCount} usages");
            $this->command->info("  Range: {$minUsage}-{$maxUsage}m³ | Average: " . round($avgUsage, 1) . "m³");
        }
    }

    /**
     * Get customer type for realistic usage patterns
     */
    private function getCustomerType(): string
    {
        $types = [
            'low_usage' => 30,      // 30% - Small households (5-15 m³)
            'medium_usage' => 40,   // 40% - Average households (10-25 m³)
            'high_usage' => 25,     // 25% - Large households (20-40 m³)
            'very_high_usage' => 5  // 5% - Very large households/small business (35-60 m³)
        ];

        $random = rand(1, 100);
        $cumulative = 0;

        foreach ($types as $type => $percentage) {
            $cumulative += $percentage;
            if ($random <= $cumulative) {
                return $type;
            }
        }

        return 'medium_usage';
    }

    /**
     * Generate realistic usage based on customer type
     * Ensures good distribution across tariff ranges
     */
    private function generateRealisticUsage(string $customerType): int
    {
        switch ($customerType) {
            case 'low_usage':
                // Most will be in first tariff range (0-10 m³)
                return rand(5, 15);

            case 'medium_usage':
                // Will span first and second tariff ranges (10-25 m³)
                return rand(8, 25);

            case 'high_usage':
                // Will hit second and third tariff ranges (20-40 m³)
                return rand(18, 40);

            case 'very_high_usage':
                // Will hit all tariff ranges including highest (35-60 m³)
                return rand(32, 60);

            default:
                return rand(10, 25);
        }
    }

    /**
     * Show usage distribution for a village
     */
    private function showUsageDistribution(Village $village): void
    {
        $usages = WaterUsage::whereHas('customer', function ($q) use ($village) {
            $q->where('village_id', $village->id);
        })->pluck('total_usage_m3');

        if ($usages->isEmpty()) {
            return;
        }

        // Create distribution buckets based on typical tariff ranges
        $distribution = [
            '0-10 m³' => $usages->filter(fn($u) => $u >= 0 && $u <= 10)->count(),
            '11-20 m³' => $usages->filter(fn($u) => $u >= 11 && $u <= 20)->count(),
            '21-30 m³' => $usages->filter(fn($u) => $u >= 21 && $u <= 30)->count(),
            '31-40 m³' => $usages->filter(fn($u) => $u >= 31 && $u <= 40)->count(),
            '40+ m³' => $usages->filter(fn($u) => $u > 40)->count(),
        ];

        $this->command->info("  Usage Distribution:");
        foreach ($distribution as $range => $count) {
            $percentage = round(($count / $usages->count()) * 100, 1);
            $this->command->info("    {$range}: {$count} ({$percentage}%)");
        }
    }
}
