<?php
// database/seeders/DatabaseSeeder.php - Updated with proper seeding order

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting PAMDes Database Seeding...');
        $this->command->info('');

        // 1. First, create villages (foundation data)
        $this->command->info('1️⃣ Creating villages...');
        $this->call(VillageSeeder::class);
        $this->command->info('');

        // 2. Create users and assign them to villages
        $this->command->info('2️⃣ Creating users and village assignments...');
        $this->call(UserSeeder::class);
        $this->command->info('');

        // 3. Create water tariffs (both global and village-specific)
        $this->command->info('3️⃣ Creating water tariffs...');
        $this->call(WaterTariffSeeder::class);
        $this->command->info('');

        // 4. Create billing periods for each village
        $this->command->info('4️⃣ Creating billing periods...');
        $this->call(BillingPeriodSeeder::class);
        $this->command->info('');

        // 5. Create customers for each village
        $this->command->info('5️⃣ Creating customers...');
        $this->call(CustomerSeeder::class);
        $this->command->info('');

        // 6. Create water usage records
        $this->command->info('6️⃣ Creating water usage records...');
        $this->call(WaterUsageSeeder::class);
        $this->command->info('');

        // 7. Generate bills from water usage
        $this->command->info('7️⃣ Generating bills from water usage...');
        $this->call(BillSeeder::class);
        $this->command->info('');

        // 8. Create payment records for paid bills
        $this->command->info('8️⃣ Creating payment records...');
        $this->call(PaymentSeeder::class);
        $this->command->info('');

        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->info('');

        // Show final summary
        $this->showSummary();
    }

    private function showSummary(): void
    {
        $this->command->info('📊 FINAL SUMMARY');
        $this->command->info('================');

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

        // Show business metrics
        $totalRevenue = \App\Models\Payment::sum('amount_paid');
        $outstandingAmount = \App\Models\Bill::where('status', '!=', 'paid')->sum('total_amount');
        $collectionRate = $bills > 0 ? (\App\Models\Bill::where('status', 'paid')->count() / $bills * 100) : 0;

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

        foreach (\App\Models\Village::where('is_active', true)->get() as $village) {
            $villageUrl = str_replace('{village}', $village->slug, $villagePattern);
            $this->command->info("  🏘️  {$village->name}: http://{$villageUrl}/admin");
            $this->command->info("     📧 Email: admin@{$village->slug}.{$domain}");
            $this->command->info("     🔑 Password: password");
        }

        $this->command->info('');
        $this->command->info('🎉 Ready to use! Happy managing your PAMDes system!');
    }
}
