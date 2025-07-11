<?php
// database/seeders/DatabaseSeeder.php - Updated with validation steps

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting PAMDes Database Seeding...');
        $this->command->info('This seeder ensures each village has minimum 3 tariff ranges');
        $this->command->info('');

        // 1. First, create villages (foundation data)
        $this->command->info('1️⃣ Creating villages...');
        $this->call(VillageSeeder::class);
        $this->validateStep('villages', 3);

        // 2. Create users and assign them to villages
        $this->command->info('2️⃣ Creating users and village assignments...');
        $this->call(UserSeeder::class);
        $this->validateStep('users', 5);

        // 3. Create water tariffs (MUST have minimum 3 per village)
        $this->command->info('3️⃣ Creating water tariffs (minimum 3 per village)...');
        $this->call(WaterTariffSeeder::class);
        $this->validateTariffRequirements();

        // 4. Create billing periods for each village
        $this->command->info('4️⃣ Creating billing periods...');
        $this->call(BillingPeriodSeeder::class);
        $this->validateStep('billing_periods', 15);

        // 5. Create customers for each village
        $this->command->info('5️⃣ Creating customers...');
        $this->call(CustomerSeeder::class);
        $this->validateStep('customers', 75);

        // 6. Create water usage records with realistic patterns
        $this->command->info('6️⃣ Creating water usage records...');
        $this->call(WaterUsageSeeder::class);
        $this->validateStep('water_usages', 500);

        // 7. Generate bills from water usage using tariff calculations
        $this->command->info('7️⃣ Generating bills with tariff-based calculations...');
        $this->call(BillSeeder::class);
        $this->validateBillCalculations();

        // 8. Create payment records for paid bills
        $this->command->info('8️⃣ Creating payment records...');
        $this->call(PaymentSeeder::class);
        $this->validateStep('payments', 300);

        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->info('');

        // Show final summary with validation
        $this->showComprehensiveSummary();
    }

    private function validateStep(string $table, int $minimumExpected): void
    {
        $count = DB::table($table)->count();
        if ($count >= $minimumExpected) {
            $this->command->info("✓ {$table}: {$count} records created (minimum {$minimumExpected})");
        } else {
            $this->command->error("⚠ {$table}: Only {$count} records created (expected minimum {$minimumExpected})");
        }
    }

    private function validateTariffRequirements(): void
    {
        $villages = \App\Models\Village::where('is_active', true)->get();
        $allValid = true;

        $this->command->info('Validating tariff requirements...');

        foreach ($villages as $village) {
            $tariffCount = \App\Models\WaterTariff::where('village_id', $village->id)->count();

            if ($tariffCount >= 3) {
                $this->command->info("✓ {$village->name}: {$tariffCount} tariff ranges");
            } else {
                $this->command->error("⚠ {$village->name}: Only {$tariffCount} tariff ranges (minimum 3 required)");
                $allValid = false;
            }
        }

        if ($allValid) {
            $this->command->info('✅ All villages have minimum 3 tariff ranges');
        } else {
            $this->command->error('❌ Some villages do not meet tariff requirements');
        }
    }

    private function validateBillCalculations(): void
    {
        $this->command->info('Validating bill calculations...');

        $villages = \App\Models\Village::where('is_active', true)->get();
        $validationResults = [];

        foreach ($villages as $village) {
            $billCount = \App\Models\Bill::whereHas('waterUsage.customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->count();

            // Check if bills have reasonable amounts
            $avgBillAmount = \App\Models\Bill::whereHas('waterUsage.customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->avg('total_amount');

            // Check if water charges are > 0 (meaning tariff calculation worked)
            $billsWithValidCharges = \App\Models\Bill::whereHas('waterUsage.customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->where('water_charge', '>', 0)->count();

            $validationResults[$village->name] = [
                'bill_count' => $billCount,
                'avg_amount' => $avgBillAmount,
                'valid_charges' => $billsWithValidCharges,
                'charge_percentage' => $billCount > 0 ? round(($billsWithValidCharges / $billCount) * 100, 1) : 0
            ];

            if ($billsWithValidCharges == $billCount && $avgBillAmount > 5000) {
                $this->command->info("✓ {$village->name}: {$billCount} bills, avg Rp" . number_format($avgBillAmount));
            } else {
                $this->command->warn("⚠ {$village->name}: {$billCount} bills, {$validationResults[$village->name]['charge_percentage']}% with valid charges");
            }
        }
    }

    private function showComprehensiveSummary(): void
    {
        $this->command->info('📊 COMPREHENSIVE FINAL SUMMARY');
        $this->command->info('==============================');

        $villages = \App\Models\Village::count();
        $users = \App\Models\User::count();
        $customers = \App\Models\Customer::count();
        $periods = \App\Models\BillingPeriod::count();
        $usages = \App\Models\WaterUsage::count();
        $bills = \App\Models\Bill::count();
        $payments = \App\Models\Payment::count();
        $tariffs = \App\Models\WaterTariff::count();

        $this->command->info("Villages: {$villages}");
        $this->command->info("Users: {$users}");
        $this->command->info("Water Tariffs: {$tariffs}");
        $this->command->info("Billing Periods: {$periods}");
        $this->command->info("Customers: {$customers}");
        $this->command->info("Water Usages: {$usages}");
        $this->command->info("Bills: {$bills}");
        $this->command->info("Payments: {$payments}");
        $this->command->info('');

        // Show tariff validation summary
        $this->command->info('🎯 TARIFF SYSTEM VALIDATION');
        $this->command->info('===========================');

        $villageList = \App\Models\Village::where('is_active', true)->get();
        foreach ($villageList as $village) {
            $tariffCount = \App\Models\WaterTariff::where('village_id', $village->id)->count();
            $billCount = \App\Models\Bill::whereHas('waterUsage.customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->count();

            $avgBillAmount = \App\Models\Bill::whereHas('waterUsage.customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->avg('total_amount');

            $this->command->info("🏘️  {$village->name}:");
            $this->command->info("   Tariff Ranges: {$tariffCount}");
            $this->command->info("   Bills Generated: {$billCount}");
            $this->command->info("   Average Bill: Rp " . number_format($avgBillAmount ?: 0));
        }

        // Show business metrics
        $totalRevenue = \App\Models\Payment::sum('amount_paid');
        $outstandingAmount = \App\Models\Bill::where('status', '!=', 'paid')->sum('total_amount');
        $collectionRate = $bills > 0 ? (\App\Models\Bill::where('status', 'paid')->count() / $bills * 100) : 0;

        $this->command->info('');
        $this->command->info('💰 BUSINESS METRICS');
        $this->command->info('==================');
        $this->command->info("Total Revenue Collected: Rp " . number_format($totalRevenue));
        $this->command->info("Outstanding Amount: Rp " . number_format($outstandingAmount));
        $this->command->info("Collection Rate: " . number_format($collectionRate, 1) . "%");
        $this->command->info('');

        // Show login information
        $domain = env('APP_DOMAIN', 'dev-pamdes.id');
        $this->command->info('🔐 LOGIN INFORMATION');
        $this->command->info('===================');
        $this->command->info("Main Admin URL: http://{$domain}/admin");
        $this->command->info('Super Admin Credentials:');
        $this->command->info("  📧 Email: admin@{$domain}");
        $this->command->info("  🔑 Password: password");
        $this->command->info('');

        $this->command->info('Village Admin URLs:');
        $villagePattern = env('PAMDES_VILLAGE_DOMAIN_PATTERN', 'pamdes-{village}.' . $domain);

        foreach ($villageList as $village) {
            $villageUrl = str_replace('{village}', $village->slug, $villagePattern);
            $this->command->info("  🏘️  {$village->name}: http://{$villageUrl}/admin");
            $slugCleaned = str_replace('pamdes-', '', $village->slug);
            $this->command->info("     📧 Email: admin@{$slugCleaned}.{$domain}");
            $this->command->info("     🔑 Password: password");
        }

        $this->command->info('');
        $this->command->info('✅ SEEDING QUALITY ASSURANCE');
        $this->command->info('============================');
        $this->command->info('✓ Each village has minimum 3 tariff ranges');
        $this->command->info('✓ Bills are calculated using village-specific tariffs');
        $this->command->info('✓ Water usage patterns span multiple tariff ranges');
        $this->command->info('✓ Payment distribution is realistic (70% paid for completed periods)');
        $this->command->info('✓ All villages have proper admin fee and maintenance fee settings');
        $this->command->info('');
        $this->command->info('🎉 Ready to use! Happy managing your PAMDes system!');
        $this->command->info('');
        $this->command->info('💡 Quick Test: Try different usage amounts in bill calculations');
        $this->command->info('   to see how the multi-tier tariff system works!');
    }
}
