<?php
// database/seeders/PaymentSeeder.php - Updated for new simplified payment structure

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Payment;
use App\Models\Bill;
use App\Models\Village;
use App\Models\User;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $villages = Village::where('is_active', true)->get();

        if ($villages->isEmpty()) {
            $this->command->error('No active villages found.');
            return;
        }

        $totalPayments = 0;

        foreach ($villages as $village) {
            $this->command->info("Creating payments for village: {$village->name}");

            // Get active collectors for this village
            $collectors = User::whereHas('villages', function ($q) use ($village) {
                $q->where('villages.id', $village->id);
            })
                ->whereIn('role', ['collector', 'operator'])
                ->where('is_active', true)
                ->get();

            if ($collectors->isEmpty()) {
                $this->command->warn("No active collectors found for {$village->name}. Skipping...");
                continue;
            }

            // Get unpaid bills that can be paid for this village
            $unpaidBills = Bill::whereHas('customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })
                ->where('status', 'unpaid')
                ->with(['customer', 'waterUsage.billingPeriod'])
                ->get();

            if ($unpaidBills->isEmpty()) {
                $this->command->warn("No unpaid bills found for {$village->name}. Skipping...");
                continue;
            }

            // Pay a percentage of bills (70% paid for completed periods)
            $billsToPay = $unpaidBills->filter(function ($bill) {
                return $bill->waterUsage->billingPeriod->status === 'completed' && rand(1, 10) <= 7;
            });

            $villagePayments = 0;

            // Create individual payments for selected bills
            foreach ($billsToPay as $bill) {
                // Determine payment method (realistic distribution)
                $paymentMethods = [
                    'cash' => 60,     // 60% cash
                    'transfer' => 25, // 25% transfer
                    'qris' => 10,     // 10% QRIS
                    'other' => 5      // 5% other
                ];

                $random = rand(1, 100);
                $paymentMethod = 'cash';
                $cumulative = 0;

                foreach ($paymentMethods as $method => $percentage) {
                    $cumulative += $percentage;
                    if ($random <= $cumulative) {
                        $paymentMethod = $method;
                        break;
                    }
                }

                // Calculate payment details
                $billAmount = $bill->total_amount;
                $changeGiven = 0;

                if ($paymentMethod === 'cash' && rand(1, 3) === 1) {
                    // 33% chance for cash payments to have change
                    $roundUpAmount = ceil($billAmount / 5000) * 5000; // Round up to nearest 5000
                    if ($roundUpAmount > $billAmount) {
                        $changeGiven = $roundUpAmount - $billAmount;
                    }
                }

                // Generate transaction reference for digital payments
                $transactionRef = null;
                if (in_array($paymentMethod, ['transfer', 'qris'])) {
                    $transactionRef = 'TXN-' . strtoupper($village->slug) . '-' . date('YmdHis', strtotime($bill->payment_date)) . '-' . uniqid();
                }

                // Select random collector from this village
                $collector = $collectors->random();

                // Generate realistic payment date for completed periods
                $paymentDate = $bill->due_date
                    ? $bill->due_date->subDays(rand(0, 10))
                    : now()->subDays(rand(1, 30));

                // Generate realistic notes (20% chance)
                $notes = null;
                if (rand(1, 5) === 1) {
                    $noteOptions = [
                        'Pembayaran tepat waktu',
                        'Dibayar di kantor desa',
                        'Pelanggan datang sendiri',
                        'Pembayaran melalui petugas',
                        'Pembayaran via transfer mobile banking',
                        'Dibayar oleh keluarga',
                    ];
                    $notes = fake()->randomElement($noteOptions);
                }

                // Create payment using the new payBills method
                $payment = Payment::payBills([$bill->bill_id], [
                    'payment_date' => $paymentDate,
                    'change_given' => $changeGiven,
                    'payment_method' => $paymentMethod,
                    'transaction_ref' => $transactionRef,
                    'collector_id' => $collector->id,
                    'notes' => $notes,
                ]);

                $villagePayments++;
            }

            // Create some bundle payments (20% chance for customers with multiple unpaid bills)
            if (rand(1, 5) === 1) {
                $customers = $village->customers()->has('bills')->get();
                foreach ($customers->take(3) as $customer) {
                    $unpaidBills = $customer->bills()->where('status', 'unpaid')->limit(rand(2, 4))->get();
                    if ($unpaidBills->count() >= 2) {
                        $transactionRef = 'TXN-' . strtoupper($village->slug) . '-' . now()->format('YmdHis') . '-' . uniqid();
                        
                        $payment = Payment::payBills($unpaidBills->pluck('bill_id')->toArray(), [
                            'payment_date' => now()->subDays(rand(0, 7)),
                            'payment_method' => 'qris',
                            'transaction_ref' => $transactionRef,
                            'notes' => 'Bundle payment via portal',
                        ]);
                        
                        $villagePayments++;
                    }
                }
            }

            $this->command->info("Created {$villagePayments} payment records for {$village->name}");
            $totalPayments += $villagePayments;
        }

        $this->command->info("Total payment records created: {$totalPayments}");

        // Show summary statistics
        $this->command->info('');
        $this->command->info('Payment Method Summary:');
        $this->command->info('- Cash: ' . Payment::where('payment_method', 'cash')->count());
        $this->command->info('- Transfer: ' . Payment::where('payment_method', 'transfer')->count());
        $this->command->info('- QRIS: ' . Payment::where('payment_method', 'qris')->count());
        $this->command->info('- Other: ' . Payment::where('payment_method', 'other')->count());

        $this->command->info('');
        $this->command->info('Payment Summary by Village:');
        foreach ($villages as $village) {
            $villagePaymentCount = Payment::whereHas('bills.customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->count();

            $totalPaid = Payment::whereHas('bills.customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->sum('total_amount');

            $totalChange = Payment::whereHas('bills.customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->sum('change_given');

            $this->command->info("- {$village->name}: {$villagePaymentCount} payments - Total Collected: Rp " . number_format($totalPaid) . " (Change Given: Rp " . number_format($totalChange) . ")");
        }

        // Show collector performance summary (Fixed)
        $this->command->info('');
        $this->command->info('Collector Performance Summary:');
        foreach ($villages as $village) {
            $this->command->info("Village: {$village->name}");

            $collectors = User::whereHas('villages', function ($q) use ($village) {
                $q->where('villages.id', $village->id);
            })
                ->whereIn('role', ['collector', 'operator'])
                ->where('is_active', true)
                ->get();

            foreach ($collectors as $collector) {
                // Get payment count for this collector in the last 30 days
                $recentPaymentCount = Payment::where('collector_id', $collector->id)
                    ->whereDate('payment_date', '>=', now()->subDays(30))
                    ->count();

                $totalCollected = Payment::where('collector_id', $collector->id)
                    ->sum('total_amount');

                $this->command->info("  - {$collector->name}: {$recentPaymentCount} payments (last 30 days), Rp " . number_format($totalCollected) . " total collected");
            }
        }

        $this->command->info('');
        $this->command->info('Recent Payments (Last 7 days):');
        $recentPayments = Payment::where('payment_date', '>=', now()->subDays(7))->count();
        $this->command->info("- {$recentPayments} payments in the last 7 days");

        $todayPayments = Payment::whereDate('payment_date', today())->count();
        $this->command->info("- {$todayPayments} payments today");

        // Show payments with notes
        $paymentsWithNotes = Payment::whereNotNull('notes')->count();
        $this->command->info("- {$paymentsWithNotes} payments have notes");

        $this->command->info('');
        $this->command->info('✅ Payment seeding completed successfully!');
    }
}
