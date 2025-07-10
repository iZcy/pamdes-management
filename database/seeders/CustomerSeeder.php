<?php

// database/seeders/CustomerSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $villages = [
            ['id' => 'bayan-village-id', 'name' => 'Bayan'],
            ['id' => 'senaru-village-id', 'name' => 'Senaru'],
            ['id' => 'pemenang-village-id', 'name' => 'Pemenang'],
        ];

        foreach ($villages as $village) {
            for ($i = 1; $i <= 50; $i++) {
                Customer::create([
                    'customer_code' => Customer::generateCustomerCode($village['id']),
                    'name' => fake()->name(),
                    'phone_number' => fake()->phoneNumber(),
                    'status' => fake()->randomElement(['active', 'inactive']),
                    'address' => fake()->address(),
                    'rt' => str_pad(fake()->numberBetween(1, 10), 2, '0', STR_PAD_LEFT),
                    'rw' => str_pad(fake()->numberBetween(1, 5), 2, '0', STR_PAD_LEFT),
                    'village' => $village['name'],
                    'village_id' => $village['id'],
                ]);
            }
        }
    }
}
