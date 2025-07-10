<?php

// app/Services/PaymentService.php
namespace App\Services;

use App\Models\Bill;
use App\Models\Payment;
use App\Notifications\PaymentReceived;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function processPayment(Bill $bill, array $paymentData): Payment
    {
        return DB::transaction(function () use ($bill, $paymentData) {
            $payment = $bill->markAsPaid($paymentData);

            // Send notification if enabled
            if (config('village.features.notifications_enabled')) {
                $this->sendPaymentNotification($payment);
            }

            return $payment;
        });
    }

    protected function sendPaymentNotification(Payment $payment): void
    {
        // Send to village system
        if (config('village.features.village_notifications')) {
            app(VillageApiService::class)->sendNotification(
                $payment->bill->waterUsage->customer->village_id,
                'payment_received',
                [
                    'customer_code' => $payment->customer->customer_code,
                    'customer_name' => $payment->customer->name,
                    'amount' => $payment->amount_paid,
                    'payment_date' => $payment->payment_date->toDateString(),
                    'payment_method' => $payment->payment_method,
                ]
            );
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
        ];
    }
}
