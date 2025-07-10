<?php

// app/Notifications/PaymentReceived.php - Complete implementation
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Payment;

class PaymentReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Payment $payment
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Konfirmasi Pembayaran - ' . $this->payment->bill->waterUsage->billingPeriod->period_name)
            ->greeting('Halo ' . $this->payment->customer->name)
            ->line('Pembayaran Anda telah diterima dengan detail berikut:')
            ->line('Periode: ' . $this->payment->bill->waterUsage->billingPeriod->period_name)
            ->line('Jumlah dibayar: Rp ' . number_format($this->payment->amount_paid))
            ->line('Tanggal pembayaran: ' . $this->payment->payment_date->format('d F Y H:i'))
            ->line('Metode pembayaran: ' . $this->payment->payment_method_label)
            ->action('Cetak Kwitansi', route('payment.receipt', $this->payment->payment_id))
            ->line('Terima kasih atas pembayaran Anda.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->payment_id,
            'customer_name' => $this->payment->customer->name,
            'amount' => $this->payment->amount_paid,
            'payment_date' => $this->payment->payment_date,
            'method' => $this->payment->payment_method,
        ];
    }
}
