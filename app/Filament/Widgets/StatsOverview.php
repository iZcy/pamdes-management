<?php

// app/Filament/Widgets/StatsOverview.php - Complete implementation
namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Customer;
use App\Models\Bill;
use App\Models\Payment;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $villageId = config('pamdes.current_village.id');

        $customerQuery = Customer::query();
        $billQuery = Bill::query();
        $paymentQuery = Payment::query();

        if ($villageId) {
            $customerQuery->byVillage($villageId);
            $billQuery->whereHas('waterUsage.customer', fn($q) => $q->where('village_id', $villageId));
            $paymentQuery->whereHas('bill.waterUsage.customer', fn($q) => $q->where('village_id', $villageId));
        }

        return [
            Stat::make('Total Pelanggan', $customerQuery->count())
                ->description('Pelanggan terdaftar')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary')
                ->chart([7, 3, 4, 5, 6, 3, 5, 3]),

            Stat::make('Pelanggan Aktif', $customerQuery->active()->count())
                ->description('Status aktif')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart([3, 3, 4, 5, 6, 7, 8, 7]),

            Stat::make('Tagihan Belum Bayar', 'Rp ' . number_format($billQuery->unpaid()->sum('total_amount')))
                ->description('Total outstanding')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning')
                ->chart([15, 4, 10, 2, 12, 4, 12, 4]),

            Stat::make('Pembayaran Bulan Ini', 'Rp ' . number_format($paymentQuery->thisMonth()->sum('amount_paid')))
                ->description('Total terkumpul')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart([2, 3, 4, 5, 6, 5, 4, 3]),
        ];
    }
}
