<?php

// database/seeders/WaterUsageSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WaterUsage;
use App\Models\Customer;
use App\Models\BillingPeriod;

class WaterUsageSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::all();
        $periods = BillingPeriod::where('status', '!=', 'inactive')->get();

        foreach ($customers as $customer) {
            foreach ($periods as $period) {
                $initialMeter = fake()->numberBetween(100, 500);
                $finalMeter = $initialMeter + fake()->numberBetween(5, 50);

                $usage = WaterUsage::create([
                    'customer_id' => $customer->customer_id,
                    'period_id' => $period->period_id,
                    'initial_meter' => $initialMeter,
                    'final_meter' => $finalMeter,
                    'total_usage_m3' => $finalMeter - $initialMeter,
                    'usage_date' => $period->reading_start_date->addDays(fake()->numberBetween(1, 10)),
                    'reader_name' => fake()->name(),
                    'notes' => fake()->optional()->sentence(),
                ]);

                // Generate bill for completed periods
                if ($period->status === 'completed') {
                    $usage->generateBill([
                        'admin_fee' => 5000,
                        'maintenance_fee' => 2000,
                    ]);
                }
            }
        }
    }
}
