<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\Customer;
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
            'customer_id' => Customer::factory(),
            'usage_id' => WaterUsage::factory(),
            'tariff_id' => WaterTariff::factory(),
            'water_charge' => $waterCharge,
            'admin_fee' => $adminFee,
            'maintenance_fee' => $maintenanceFee,
            'total_amount' => $totalAmount,
            'status' => $this->faker->randomElement(['unpaid', 'paid']),
            'transaction_ref' => null,
            'due_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'payment_date' => null,
            'notes' => $this->faker->optional(0.3)->sentence(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'payment_date' => $this->faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
        ]);
    }

    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'unpaid',
            'payment_date' => null,
            'transaction_ref' => null,
        ]);
    }

    public function pendingPayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'unpaid',
            'transaction_ref' => 'TXN-' . strtoupper($this->faker->lexify('???')) . '-' . now()->format('YmdHis') . '-' . uniqid(),
            'payment_date' => null,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'unpaid',
            'due_date' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
            'payment_date' => null,
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

    public function withTransactionRef(string $transactionRef): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_ref' => $transactionRef,
        ]);
    }
}