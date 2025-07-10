<?php

// database/seeders/DatabaseSeeder.php - Updated
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            WaterTariffSeeder::class,
            BillingPeriodSeeder::class,
            CustomerSeeder::class,
            WaterUsageSeeder::class,
        ]);
    }
}
