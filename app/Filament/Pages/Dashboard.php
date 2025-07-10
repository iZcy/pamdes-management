<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Customer;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\BillingPeriod;
use Filament\Widgets\StatsOverviewWidget\Stat;

class Dashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static string $view = 'filament.pages.dashboard';
    protected static ?string $title = 'Dashboard PAMDes';

    public function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\StatsOverview::class,
        ];
    }

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
                ->color('primary'),

            Stat::make('Pelanggan Aktif', $customerQuery->active()->count())
                ->description('Status aktif')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Tagihan Belum Bayar', 'Rp ' . number_format($billQuery->unpaid()->sum('total_amount')))
                ->description('Total outstanding')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning'),

            Stat::make('Pembayaran Bulan Ini', 'Rp ' . number_format($paymentQuery->thisMonth()->sum('amount_paid')))
                ->description('Total terkumpul')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }
}
