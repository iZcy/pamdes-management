<?php

// database/seeders/BillingPeriodSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BillingPeriod;

class BillingPeriodSeeder extends Seeder
{
    public function run(): void
    {
        $villages = ['bayan-village-id', 'senaru-village-id', 'pemenang-village-id'];

        foreach ($villages as $villageId) {
            // Create periods for last 6 months
            for ($i = 0; $i < 6; $i++) {
                $date = now()->subMonths($i);

                BillingPeriod::create([
                    'year' => $date->year,
                    'month' => $date->month,
                    'village_id' => $villageId,
                    'status' => $i === 0 ? 'active' : ($i === 1 ? 'completed' : 'inactive'),
                    'reading_start_date' => $date->startOfMonth(),
                    'reading_end_date' => $date->endOfMonth(),
                    'billing_due_date' => $date->addDays(15),
                ]);
            }
        }
    }
}
