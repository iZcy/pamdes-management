<?php
// app/Filament/Widgets/StatsOverview.php - Village-aware version

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Customer;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $user = Auth::user();
        $user = User::find($user->id);
        $currentVillage = $user?->getCurrentVillageContext();

        if (!$currentVillage) {
            return [
                Stat::make('Informasi', 'Pilih desa untuk melihat statistik')
                    ->description('Sistem PAMDes')
                    ->color('gray'),
            ];
        }

        // Get village name for display
        $village = \App\Models\Village::find($currentVillage);
        $villageName = $village?->name ?? 'Unknown';

        // Build queries with village filter
        $customerQuery = Customer::where('village_id', $currentVillage);
        $billQuery = Bill::whereHas('waterUsage.customer', fn($q) => $q->where('village_id', $currentVillage));
        $paymentQuery = Payment::whereHas('bill.waterUsage.customer', fn($q) => $q->where('village_id', $currentVillage));

        return [
            Stat::make('Total Pelanggan', $customerQuery->count())
                ->description("Desa {$villageName}")
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
