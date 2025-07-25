<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\User;
use App\Models\WaterUsage;
use App\Models\WaterTariff;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillFactory extends Factory
{
    protected $model = Bill::class;

    public function definition(): array
    {
        $waterCharge = $this->faker->numberBetween(15000, 100000);
        $adminFee = $this->faker->numberBetween(3000, 10000);
        $maintenanceFee = $this->faker->numberBetween(2000, 8000);
        $totalAmount = $waterCharge + $adminFee + $maintenanceFee;

        return [
            'bill_ref' => null,
            'bundle_reference' => Bill::generateBundleReference(),
            'customer_id' => Customer::factory(),
            'usage_id' => WaterUsage::factory(),
            'tariff_id' => WaterTariff::factory(),
            'water_charge' => $waterCharge,
            'admin_fee' => $adminFee,
            'maintenance_fee' => $maintenanceFee,
            'total_amount' => $totalAmount,
            'bill_count' => 1, // Default to single bill
            'status' => $this->faker->randomElement(['unpaid', 'paid', 'overdue', 'pending']),
            'payment_method' => $this->faker->randomElement(['cash', 'transfer', 'qris', 'other']),
            'payment_reference' => null,
            'tripay_data' => null,
            'collector_id' => null,
            'paid_at' => null,
            'expires_at' => null,
            'due_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'payment_date' => null,
            'notes' => $this->faker->optional(0.3)->sentence(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'payment_date' => $this->faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'payment_reference' => $this->faker->uuid(),
        ]);
    }

    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'unpaid',
            'paid_at' => null,
            'payment_date' => null,
            'payment_reference' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'paid_at' => null,
            'payment_date' => null,
            'expires_at' => $this->faker->dateTimeBetween('now', '+7 days'),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'overdue',
            'due_date' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
            'paid_at' => null,
            'payment_date' => null,
        ]);
    }

    public function bundle(int $billCount = null): static
    {
        $count = $billCount ?? $this->faker->numberBetween(2, 5);
        
        return $this->state(fn (array $attributes) => [
            'bill_count' => $count,
            'total_amount' => $this->faker->numberBetween(50000, 500000), // Higher amount for bundles
            'water_charge' => 0, // Bundle bills don't have individual charges
            'admin_fee' => 0,
            'maintenance_fee' => 0,
            'usage_id' => null, // Bundle bills don't have direct usage
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'cash',
            'tripay_data' => null,
            'collector_id' => User::factory(),
        ]);
    }

    public function transfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'transfer',
            'collector_id' => null,
            'tripay_data' => [
                'merchant_ref' => $this->faker->uuid(),
                'payment_method' => 'transfer',
                'payment_name' => 'Transfer Bank',
                'checkout_url' => $this->faker->url(),
                'account_number' => $this->faker->bankAccountNumber(),
                'bank_name' => $this->faker->randomElement(['BCA', 'Mandiri', 'BRI', 'BNI']),
            ],
        ]);
    }

    public function qris(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'qris',
            'collector_id' => null,
            'tripay_data' => [
                'merchant_ref' => $this->faker->uuid(),
                'payment_method' => 'qris',
                'payment_name' => 'QRIS',
                'checkout_url' => $this->faker->url(),
                'qr_url' => $this->faker->imageUrl(300, 300, 'business', true, 'QR Code'),
                'qr_string' => $this->faker->regexify('[A-Z0-9]{32}'),
            ],
        ]);
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => $customer->customer_id,
        ]);
    }

    public function forUsage(WaterUsage $usage): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_id' => $usage->usage_id,
            'customer_id' => $usage->customer_id,
            'tariff_id' => $usage->tariff_id ?? WaterTariff::factory(),
        ]);
    }

    public function withAmount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'total_amount' => $amount,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => $this->faker->dateTimeBetween('-7 days', '-1 day'),
            'paid_at' => null,
            'payment_date' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'paid_at' => null,
            'payment_date' => null,
            'expires_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }
}