<?php
// database/seeders/BillSeeder.php - Generate bills from existing water usages

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bill;
use App\Models\WaterUsage;
use App\Models\WaterTariff;
use App\Models\Village;
use App\Models\BillingPeriod;

class BillSeeder extends Seeder
{
    public function run(): void
    {
        $villages = Village::where('is_active', true)->get();

        if ($villages->isEmpty()) {
            $this->command->error('No active villages found.');
            return;
        }

        $totalBills = 0;

        foreach ($villages as $village) {
            $this->command->info("Creating bills for village: {$village->name}");

            // Get water usages that don't have bills yet for this village
            $waterUsages = WaterUsage::whereHas('customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })
                ->whereDoesntHave('bill')
                ->with(['customer', 'billingPeriod'])
                ->get();

            if ($waterUsages->isEmpty()) {
                $this->command->warn("No unbilled water usages found for {$village->name}. Skipping...");
                continue;
            }

            $villageBills = 0;

            foreach ($waterUsages as $usage) {
                // Calculate water charges using tariff
                $calculation = WaterTariff::calculateBill(
                    $usage->total_usage_m3,
                    $village->id
                );

                // Get village-specific fees or use defaults
                $adminFee = $village->getDefaultAdminFee();
                $maintenanceFee = $village->getDefaultMaintenanceFee();

                $waterCharge = $calculation['total_charge'];
                $totalAmount = $waterCharge + $adminFee + $maintenanceFee;

                // Determine bill status based on billing period
                $status = 'unpaid';
                $paymentDate = null;

                // For completed periods, make some bills paid (70% paid)
                if ($usage->billingPeriod->status === 'completed' && rand(1, 10) <= 7) {
                    $status = 'paid';
                    $paymentDate = $usage->billingPeriod->billing_due_date
                        ? $usage->billingPeriod->billing_due_date->subDays(rand(0, 10))
                        : now()->subDays(rand(1, 30));
                }

                // For overdue bills (5% chance for unpaid bills)
                if (
                    $status === 'unpaid' && $usage->billingPeriod->billing_due_date &&
                    $usage->billingPeriod->billing_due_date->isPast() && rand(1, 20) === 1
                ) {
                    $status = 'overdue';
                }

                Bill::create([
                    'usage_id' => $usage->usage_id,
                    'tariff_id' => null, // We could link to specific tariff if needed
                    'water_charge' => $waterCharge,
                    'admin_fee' => $adminFee,
                    'maintenance_fee' => $maintenanceFee,
                    'total_amount' => $totalAmount,
                    'status' => $status,
                    'due_date' => $usage->billingPeriod->billing_due_date,
                    'payment_date' => $paymentDate,
                ]);

                $villageBills++;
            }

            $this->command->info("Created {$villageBills} bills for {$village->name}");
            $totalBills += $villageBills;
        }

        $this->command->info("Total bills created: {$totalBills}");

        // Show summary statistics
        $this->command->info('');
        $this->command->info('Bill Status Summary:');
        $this->command->info('- Paid: ' . Bill::where('status', 'paid')->count());
        $this->command->info('- Unpaid: ' . Bill::where('status', 'unpaid')->count());
        $this->command->info('- Overdue: ' . Bill::where('status', 'overdue')->count());

        $this->command->info('');
        $this->command->info('Bill Summary by Village:');
        foreach ($villages as $village) {
            $villageBillCount = Bill::whereHas('waterUsage.customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->count();

            $paidCount = Bill::whereHas('waterUsage.customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->where('status', 'paid')->count();

            $unpaidCount = Bill::whereHas('waterUsage.customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->where('status', 'unpaid')->count();

            $overdueCount = Bill::whereHas('waterUsage.customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->where('status', 'overdue')->count();

            $totalAmount = Bill::whereHas('waterUsage.customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->sum('total_amount');

            $this->command->info("- {$village->name}: {$villageBillCount} bills (Paid: {$paidCount}, Unpaid: {$unpaidCount}, Overdue: {$overdueCount}) - Total: Rp " . number_format($totalAmount));
        }
    }
}
