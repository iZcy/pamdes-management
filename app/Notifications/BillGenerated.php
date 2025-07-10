<?php

// app/Notifications/BillGenerated.php - Complete implementation
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Bill;

class BillGenerated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Bill $bill
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tagihan Air Baru - ' . $this->bill->waterUsage->billingPeriod->period_name)
            ->greeting('Halo ' . $this->bill->waterUsage->customer->name)
            ->line('Tagihan air untuk periode ' . $this->bill->waterUsage->billingPeriod->period_name . ' telah dibuat.')
            ->line('Pemakaian air: ' . $this->bill->waterUsage->total_usage_m3 . ' m³')
            ->line('Total tagihan: Rp ' . number_format($this->bill->total_amount))
            ->line('Jatuh tempo: ' . $this->bill->due_date->format('d F Y'))
            ->action('Lihat Tagihan', route('customer.bills', $this->bill->waterUsage->customer->customer_code))
            ->line('Harap lakukan pembayaran sebelum tanggal jatuh tempo.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'bill_id' => $this->bill->bill_id,
            'customer_name' => $this->bill->waterUsage->customer->name,
            'period' => $this->bill->waterUsage->billingPeriod->period_name,
            'amount' => $this->bill->total_amount,
            'due_date' => $this->bill->due_date,
        ];
    }
}
