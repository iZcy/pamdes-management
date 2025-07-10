<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            VillageSeeder::class,
            WaterTariffSeeder::class,
            BillingPeriodSeeder::class,
            CustomerSeeder::class,
            WaterUsageSeeder::class,
        ]);
    }
}
