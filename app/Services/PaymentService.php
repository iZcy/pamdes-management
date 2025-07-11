<?php
// app/Services/PaymentService.php - Updated for independent system

namespace App\Services;

use App\Models\Bill;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function processPayment(Bill $bill, array $paymentData): Payment
    {
        return DB::transaction(function () use ($bill, $paymentData) {
            $payment = $bill->markAsPaid($paymentData);

            // Send notification locally (log instead of external API)
            // if (config('village.features.notifications_enabled')) {
            //     $this->logPaymentNotification($payment);
            // }

            return $payment;
        });
    }

    protected function logPaymentNotification(Payment $payment): void
    {
        // Log payment notification locally instead of sending to external system
        Log::info("Payment received notification", [
            'village_id' => $payment->bill->waterUsage->customer->village_id,
            'customer_code' => $payment->customer->customer_code,
            'customer_name' => $payment->customer->name,
            'amount' => $payment->amount_paid,
            'payment_date' => $payment->payment_date->toDateString(),
            'payment_method' => $payment->payment_method,
            'timestamp' => now()->toISOString(),
        ]);

        // Could also send email notification to village admin if configured
        if (config('mail.enabled', true)) {
            try {
                // Send email to village admin about payment
                $villageEmail = $payment->bill->waterUsage->customer->village_id
                    ? \App\Models\Village::find($payment->bill->waterUsage->customer->village_id)?->email
                    : config('mail.admin_email');

                // if ($villageEmail) {
                //     \Mail::to($villageEmail)->send(new \App\Mail\PaymentNotification($payment));
                // }
            } catch (\Exception $e) {
                Log::warning("Failed to send payment email notification: " . $e->getMessage());
            }
        }
    }

    public function generateReceiptData(Payment $payment): array
    {
        return [
            'payment' => $payment,
            'customer' => $payment->customer,
            'bill' => $payment->bill,
            'period' => $payment->bill->waterUsage->billingPeriod,
            'usage' => $payment->bill->waterUsage,
            'receipt_number' => 'RCP-' . str_pad($payment->payment_id, 8, '0', STR_PAD_LEFT),
            'printed_at' => now(),
            'village' => $payment->bill->waterUsage->customer->village_id
                ? \App\Models\Village::find($payment->bill->waterUsage->customer->village_id)
                : null,
        ];
    }

    public function getPaymentStats(string $villageId = null, array $dateRange = []): array
    {
        $query = Payment::query();

        if ($villageId) {
            $query->whereHas('bill.waterUsage.customer', function ($q) use ($villageId) {
                $q->where('village_id', $villageId);
            });
        }

        if (!empty($dateRange)) {
            $query->whereBetween('payment_date', $dateRange);
        }

        return [
            'total_payments' => $query->count(),
            'total_amount' => $query->sum('amount_paid'),
            'by_method' => $query->selectRaw('payment_method, COUNT(*) as count, SUM(amount_paid) as total')
                ->groupBy('payment_method')
                ->get()
                ->keyBy('payment_method')
                ->toArray(),
            'daily_totals' => $query->selectRaw('DATE(payment_date) as date, COUNT(*) as count, SUM(amount_paid) as total')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->toArray(),
        ];
    }
}
