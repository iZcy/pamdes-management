<?php
// database/seeders/BillSeeder.php - Updated to ensure bills use correct tariff calculation

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

            // Validate that village has tariffs
            $tariffCount = WaterTariff::where('village_id', $village->id)->count();
            if ($tariffCount < 3) {
                $this->command->error("Village {$village->name} has only {$tariffCount} tariff ranges. Please run WaterTariffSeeder first.");
                continue;
            }

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
            $calculationErrors = 0;

            foreach ($waterUsages as $usage) {
                try {
                    // Calculate water charges using tariff - this is the key fix
                    $calculation = WaterTariff::calculateBill(
                        $usage->total_usage_m3,
                        $village->id
                    );

                    // Get village-specific fees or use defaults
                    $adminFee = $village->getDefaultAdminFee();
                    $maintenanceFee = $village->getDefaultMaintenanceFee();

                    $waterCharge = $calculation['total_charge'];
                    $totalAmount = $waterCharge + $adminFee + $maintenanceFee;

                    // All bills start as unpaid - payments will mark them as paid
                    $status = 'unpaid';
                    $paymentDate = null;

                    // Get the appropriate tariff for logging/debugging
                    $tariff = WaterTariff::where('village_id', $village->id)
                        ->where('usage_min', '<=', $usage->total_usage_m3)
                        ->where(function ($q) use ($usage) {
                            $q->where('usage_max', '>=', $usage->total_usage_m3)
                                ->orWhereNull('usage_max');
                        })
                        ->orderBy('usage_min', 'desc')
                        ->first();

                    Bill::create([
                        'customer_id' => $usage->customer_id,
                        'usage_id' => $usage->usage_id,
                        'tariff_id' => $tariff?->tariff_id, // Link to specific tariff used
                        'water_charge' => $waterCharge,
                        'admin_fee' => $adminFee,
                        'maintenance_fee' => $maintenanceFee,
                        'total_amount' => $totalAmount,
                        'status' => $status,
                        'due_date' => $usage->billingPeriod->billing_due_date,
                        'payment_date' => $paymentDate,
                    ]);

                    $villageBills++;

                    // Log some examples for verification
                    if ($villageBills <= 3) {
                        $this->command->info("  Example: {$usage->total_usage_m3}m³ = Rp" . number_format($waterCharge) . " (using {$calculation['breakdown'][0]['range']} etc.)");
                    }
                } catch (\Exception $e) {
                    $calculationErrors++;
                    $this->command->error("  Failed to create bill for usage {$usage->usage_id}: " . $e->getMessage());
                    $this->command->error("  Usage: {$usage->total_usage_m3}m³, Village: {$village->name}");
                }
            }

            if ($calculationErrors > 0) {
                $this->command->error("  {$calculationErrors} bills failed to generate due to calculation errors");
            }

            $this->command->info("Created {$villageBills} bills for {$village->name}");
            $totalBills += $villageBills;
        }

        $this->command->info("Total bills created: {$totalBills}");

        // Show summary statistics
        $this->command->info('');
        $this->command->info('📊 Bill Status Summary:');
        $this->command->info('- Paid: ' . Bill::where('status', 'paid')->count());
        $this->command->info('- Unpaid: ' . Bill::where('status', 'unpaid')->count());
        $overdueCount = Bill::where('status', 'unpaid')->where('due_date', '<', now())->count();
        $this->command->info('- Overdue (unpaid past due): ' . $overdueCount);

        $this->command->info('');
        $this->command->info('📋 Bill Summary by Village:');
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

            $averageAmount = $villageBillCount > 0 ? $totalAmount / $villageBillCount : 0;

            $this->command->info("- {$village->name}: {$villageBillCount} bills (Paid: {$paidCount}, Unpaid: {$unpaidCount}, Overdue: {$overdueCount})");
            $this->command->info("  Total: Rp " . number_format($totalAmount) . " | Average: Rp " . number_format($averageAmount));
        }

        // Show sample bill calculations
        $this->command->info('');
        $this->command->info('💡 Sample Bill Calculations by Village:');
        foreach ($villages as $village) {
            $sampleBill = Bill::whereHas('waterUsage.customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->with(['waterUsage.customer'])->first();

            if ($sampleBill) {
                $this->command->info("📄 {$village->name} - {$sampleBill->waterUsage->customer->customer_code}:");
                $this->command->info("   Usage: {$sampleBill->waterUsage->total_usage_m3}m³");
                $this->command->info("   Water: Rp" . number_format($sampleBill->water_charge));
                $this->command->info("   Admin: Rp" . number_format($sampleBill->admin_fee));
                $this->command->info("   Maintenance: Rp" . number_format($sampleBill->maintenance_fee));
                $this->command->info("   Total: Rp" . number_format($sampleBill->total_amount));
            }
        }

        $this->command->info('');
        $this->command->info('✅ Bill generation completed with tariff-based calculations!');
    }
}
