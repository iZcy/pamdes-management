<?php
// database/seeders/CustomerSeeder.php - Fixed to use existing villages

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\Village;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // Get existing villages from the database
        $villages = Village::where('is_active', true)->get();

        if ($villages->isEmpty()) {
            $this->command->error('No active villages found. Please run VillageSeeder first.');
            return;
        }

        foreach ($villages as $village) {
            $this->command->info("Creating customers for village: {$village->name}");

            // Create 25-35 customers per village
            $customerCount = rand(25, 35);

            for ($i = 1; $i <= $customerCount; $i++) {
                Customer::create([
                    'customer_code' => Customer::generateCustomerCode($village->id),
                    'name' => fake()->name(),
                    'phone_number' => fake()->phoneNumber(),
                    'status' => fake()->randomElement(['active', 'active', 'active', 'inactive']), // 75% active
                    'address' => fake()->address(),
                    'rt' => str_pad(fake()->numberBetween(1, 15), 2, '0', STR_PAD_LEFT),
                    'rw' => str_pad(fake()->numberBetween(1, 8), 2, '0', STR_PAD_LEFT),
                    'village' => $village->name,
                    'village_id' => $village->id,
                ]);
            }

            $this->command->info("Created {$customerCount} customers for {$village->name}");
        }

        $totalCustomers = Customer::count();
        $this->command->info("Total customers created: {$totalCustomers}");
    }
}
