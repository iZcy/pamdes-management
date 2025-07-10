<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
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

        $customerQuery = \App\Models\Customer::query();
        $billQuery = \App\Models\Bill::query();
        $paymentQuery = \App\Models\Payment::query();

        if ($villageId) {
            $customerQuery->byVillage($villageId);
            $billQuery->whereHas('waterUsage.customer', fn($q) => $q->where('village_id', $villageId));
            $paymentQuery->whereHas('bill.waterUsage.customer', fn($q) => $q->where('village_id', $villageId));
        }

        return [
            [
                'label' => 'Total Pelanggan',
                'value' => $customerQuery->count(),
                'description' => 'Pelanggan terdaftar',
                'icon' => 'heroicon-m-users',
                'color' => 'primary',
            ],
            [
                'label' => 'Pelanggan Aktif',
                'value' => $customerQuery->active()->count(),
                'description' => 'Status aktif',
                'icon' => 'heroicon-m-check-circle',
                'color' => 'success',
            ],
            [
                'label' => 'Tagihan Belum Bayar',
                'value' => 'Rp ' . number_format($billQuery->unpaid()->sum('total_amount')),
                'description' => 'Total outstanding',
                'icon' => 'heroicon-m-document-text',
                'color' => 'warning',
            ],
            [
                'label' => 'Pembayaran Bulan Ini',
                'value' => 'Rp ' . number_format($paymentQuery->thisMonth()->sum('amount_paid')),
                'description' => 'Total terkumpul',
                'icon' => 'heroicon-m-banknotes',
                'color' => 'success',
            ],
        ];
    }
}
