<?php
// database/seeders/PaymentSeeder.php - Generate payments from existing paid bills with collector references

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Payment;
use App\Models\Bill;
use App\Models\Village;
use App\Models\Collector;
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
                ->whereIn('role', ['collector', 'cashier', 'operator'])
                ->where('is_active', true)
                ->get();

            if ($collectors->isEmpty()) {
                $this->command->warn("No active collectors found for {$village->name}. Skipping...");
                continue;
            }

            // Get paid bills that don't have payment records yet for this village
            $paidBills = Bill::whereHas('waterUsage.customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })
                ->where('status', 'paid')
                ->whereDoesntHave('payments')
                ->with(['waterUsage.customer', 'waterUsage.billingPeriod'])
                ->get();

            if ($paidBills->isEmpty()) {
                $this->command->warn("No paid bills without payment records found for {$village->name}. Skipping...");
                continue;
            }

            $villagePayments = 0;

            foreach ($paidBills as $bill) {
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

                // Sometimes customers pay more (for change scenarios)
                $amountPaid = $billAmount;
                $changeGiven = 0;

                if ($paymentMethod === 'cash' && rand(1, 3) === 1) {
                    // 33% chance for cash payments to have change
                    $roundUpAmount = ceil($billAmount / 5000) * 5000; // Round up to nearest 5000
                    if ($roundUpAmount > $billAmount) {
                        $amountPaid = $roundUpAmount;
                        $changeGiven = $amountPaid - $billAmount;
                    }
                }

                // Generate payment reference for non-cash payments
                $paymentReference = null;
                if ($paymentMethod === 'transfer') {
                    $paymentReference = 'TRF' . date('Ymd', strtotime($bill->payment_date)) . rand(1000, 9999);
                } elseif ($paymentMethod === 'qris') {
                    $paymentReference = 'QR' . date('Ymd', strtotime($bill->payment_date)) . rand(100000, 999999);
                }

                // Select random collector from this village
                $collector = $collectors->random();

                // Use payment date from bill, or generate realistic date
                $paymentDate = $bill->payment_date ?: $bill->due_date->subDays(rand(0, 10));

                // Generate realistic notes (20% chance)
                $notes = null;
                if (rand(1, 5) === 1) {
                    $noteOptions = [
                        'Pembayaran tepat waktu',
                        'Dibayar di kantor desa',
                        'Pelanggan datang sendiri',
                        'Pembayaran melalui petugas',
                        'Bayar sekaligus 2 bulan',
                        'Cicilan pembayaran',
                        'Pembayaran via transfer mobile banking',
                        'Dibayar oleh keluarga',
                    ];
                    $notes = fake()->randomElement($noteOptions);
                }

                Payment::create([
                    'bill_id' => $bill->bill_id,
                    'payment_date' => $paymentDate,
                    'amount_paid' => $amountPaid,
                    'change_given' => $changeGiven,
                    'payment_method' => $paymentMethod,
                    'payment_reference' => $paymentReference,
                    'collector_id' => $collector->id,
                    'notes' => $notes,
                ]);

                $villagePayments++;
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
            $villagePaymentCount = Payment::whereHas('bill.waterUsage.customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->count();

            $totalPaid = Payment::whereHas('bill.waterUsage.customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->sum('amount_paid');

            $totalChange = Payment::whereHas('bill.waterUsage.customer', function ($q) use ($village) {
                $q->where('village_id', $village->id);
            })->sum('change_given');

            $this->command->info("- {$village->name}: {$villagePaymentCount} payments - Total Collected: Rp " . number_format($totalPaid) . " (Change Given: Rp " . number_format($totalChange) . ")");
        }

        // Show collector performance summary
        $this->command->info('');
        $this->command->info('Collector Performance Summary:');
        foreach ($villages as $village) {
            $this->command->info("Village: {$village->name}");

            $collectors = User::whereHas('villages', function ($q) use ($village) {
                $q->where('villages.id', $village->id);
            })
                ->whereIn('role', ['collector', 'cashier', 'operator'])
                ->where('is_active', true)
                ->withCount(['payments as payments_count' => function ($q) {
                    $q->whereDate('payment_date', '>=', now()->subDays(30)); // Last 30 days
                }])
                ->get();

            foreach ($collectors as $collector) {
                $totalCollected = Payment::where('collector_id', $collector->collector_id)
                    ->sum('amount_paid');

                $this->command->info("  - {$collector->name}: {$collector->payments_count} payments, Rp " . number_format($totalCollected) . " collected");
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
    }
}
