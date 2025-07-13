<?php
// app/Filament/Widgets/StatsOverview.php - Enhanced with comprehensive dashboard data
namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Customer;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\User;
use App\Models\BillingPeriod;
use App\Models\WaterUsage;
use App\Models\WaterTariff;
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

        // Additional comprehensive stats
        $totalCustomers = $customerQuery->count();
        $activeCustomers = $customerQuery->active()->count();
        $newCustomersThisMonth = $customerQuery->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->count();

        $totalBills = $billQuery->count();
        $unpaidBills = $billQuery->where('status', '!=', 'paid')->count();
        $overdueBills = $billQuery->where('status', 'overdue')->count();
        $unpaidAmount = $billQuery->where('status', '!=', 'paid')->sum('total_amount');

        $totalPayments = $paymentQuery->count();
        $thisMonthPayments = $paymentQuery->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)->count();
        $todayPayments = $paymentQuery->whereDate('payment_date', today())->count();
        $thisMonthRevenue = $paymentQuery->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)->sum('amount_paid');
        $totalRevenue = $paymentQuery->sum('amount_paid');

        // Water usage stats
        $totalUsages = WaterUsage::whereHas('customer', fn($q) => $q->where('village_id', $currentVillage))->count();
        $thisMonthUsages = WaterUsage::whereHas('customer', fn($q) => $q->where('village_id', $currentVillage))
            ->whereMonth('usage_date', now()->month)->whereYear('usage_date', now()->year)->count();

        // System resources stats
        $totalTariffs = WaterTariff::where('village_id', $currentVillage)->count();
        $activePeriods = BillingPeriod::where('village_id', $currentVillage)->where('status', 'active')->count();

        // Generate trend charts (last 7 days for payments, last 6 months for others)
        $paymentTrend = $this->getPaymentTrend($currentVillage);
        $billTrend = $this->getBillTrend($currentVillage);
        $customerTrend = $this->getCustomerTrend($currentVillage);
        $revenueTrend = $this->getRevenueTrend($currentVillage);

        return [
            // Primary Stats Row
            Stat::make('Total Pelanggan', number_format($totalCustomers))
                ->description("Desa {$villageName} | {$activeCustomers} aktif")
                ->descriptionIcon('heroicon-m-users')
                ->color('primary')
                ->chart($customerTrend)
                ->url(route('filament.admin.resources.customers.index')),

            Stat::make('Tagihan Menunggak', 'Rp ' . number_format($unpaidAmount))
                ->description("{$unpaidBills} tagihan | {$overdueBills} terlambat")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueBills > 0 ? 'danger' : 'warning')
                ->chart($billTrend)
                ->url(route('filament.admin.resources.bills.index')),

            Stat::make('Pendapatan Bulan Ini', 'Rp ' . number_format($thisMonthRevenue))
                ->description("{$thisMonthPayments} pembayaran | {$todayPayments} hari ini")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart($revenueTrend)
                ->url(route('filament.admin.resources.payments.index')),

            Stat::make('Pembacaan Meter', number_format($totalUsages))
                ->description("{$thisMonthUsages} bulan ini | {$activePeriods} periode aktif")
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('info')
                ->chart($paymentTrend)
                ->url(route('filament.admin.resources.water-usages.index')),

            // Secondary Stats Row
            Stat::make('Total Pendapatan', 'Rp ' . number_format($totalRevenue))
                ->description("Semua waktu | {$totalPayments} transaksi")
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('success')
                ->chart($this->getTotalRevenueTrend($currentVillage)),

            Stat::make('Tingkat Penagihan', $this->calculateCollectionRate($currentVillage) . '%')
                ->description($this->getCollectionDescription($currentVillage))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($this->getCollectionRateColor($currentVillage))
                ->chart($this->getCollectionTrend($currentVillage)),

            Stat::make('Pelanggan Baru', number_format($newCustomersThisMonth))
                ->description("Bulan ini | " . number_format(($newCustomersThisMonth / max($totalCustomers, 1)) * 100, 1) . "% pertumbuhan")
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('primary')
                ->chart($this->getNewCustomerTrend($currentVillage)),

            Stat::make('Sistem', "{$totalTariffs} tarif")
                ->description("Infrastruktur PAMDes | {$activePeriods} periode aktif")
                ->descriptionIcon('heroicon-m-cog-6-tooth')
                ->color('gray')
                ->chart([1, 1, 1, 1, 1, 1, 1])
                ->url(route('filament.admin.resources.water-tariffs.index')),
        ];
    }

    protected function getPaymentTrend(string $villageId): array
    {
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Payment::whereHas('bill.waterUsage.customer', fn($q) => $q->where('village_id', $villageId))
                ->whereDate('payment_date', $date)
                ->count();
            $trend[] = $count;
        }
        return $trend;
    }

    protected function getBillTrend(string $villageId): array
    {
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $amount = Bill::whereHas('waterUsage.customer', fn($q) => $q->where('village_id', $villageId))
                ->where('status', '!=', 'paid')
                ->whereDate('created_at', '<=', $date)
                ->sum('total_amount');
            $trend[] = $amount / 1000000; // Convert to millions for chart scale
        }
        return $trend;
    }

    protected function getCustomerTrend(string $villageId): array
    {
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = Customer::where('village_id', $villageId)
                ->whereDate('created_at', '<=', $date->endOfMonth())
                ->count();
            $trend[] = $count;
        }
        return $trend;
    }

    protected function getRevenueTrend(string $villageId): array
    {
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $revenue = Payment::whereHas('bill.waterUsage.customer', fn($q) => $q->where('village_id', $villageId))
                ->whereMonth('payment_date', $date->month)
                ->whereYear('payment_date', $date->year)
                ->sum('amount_paid');
            $trend[] = $revenue / 1000000; // Convert to millions for chart scale
        }
        return $trend;
    }

    protected function getTotalRevenueTrend(string $villageId): array
    {
        $trend = [];
        $runningTotal = 0;
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthlyRevenue = Payment::whereHas('bill.waterUsage.customer', fn($q) => $q->where('village_id', $villageId))
                ->whereMonth('payment_date', $date->month)
                ->whereYear('payment_date', $date->year)
                ->sum('amount_paid');
            $runningTotal += $monthlyRevenue;
            $trend[] = $runningTotal / 1000000; // Convert to millions for chart scale
        }
        return $trend;
    }

    protected function getCollectionTrend(string $villageId): array
    {
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $totalBilled = Bill::whereHas('waterUsage.customer', fn($q) => $q->where('village_id', $villageId))
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->sum('total_amount');

            $totalPaid = Payment::whereHas('bill.waterUsage.customer', fn($q) => $q->where('village_id', $villageId))
                ->whereMonth('payment_date', $date->month)
                ->whereYear('payment_date', $date->year)
                ->sum('amount_paid');

            $rate = $totalBilled > 0 ? ($totalPaid / $totalBilled) * 100 : 0;
            $trend[] = $rate;
        }
        return $trend;
    }

    protected function getNewCustomerTrend(string $villageId): array
    {
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = Customer::where('village_id', $villageId)
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->count();
            $trend[] = $count;
        }
        return $trend;
    }

    protected function calculateCollectionRate(string $villageId): float
    {
        $totalBilled = Bill::whereHas('waterUsage.customer', fn($q) => $q->where('village_id', $villageId))
            ->sum('total_amount');

        $totalPaid = Payment::whereHas('bill.waterUsage.customer', fn($q) => $q->where('village_id', $villageId))
            ->sum('amount_paid');

        if ($totalBilled == 0) return 0;
        return round(($totalPaid / $totalBilled) * 100, 1);
    }

    protected function getCollectionDescription(string $villageId): string
    {
        $rate = $this->calculateCollectionRate($villageId);

        if ($rate >= 90) {
            return "Sangat baik | Target tercapai";
        } elseif ($rate >= 75) {
            return "Baik | Mendekati target";
        } elseif ($rate >= 60) {
            return "Cukup | Perlu ditingkatkan";
        } else {
            return "Kurang | Perlu perhatian";
        }
    }

    protected function getCollectionRateColor(string $villageId): string
    {
        $rate = $this->calculateCollectionRate($villageId);

        if ($rate >= 90) return 'success';
        if ($rate >= 75) return 'primary';
        if ($rate >= 60) return 'warning';
        return 'danger';
    }

    // Override column span for better layout
    protected function getColumns(): int
    {
        return 4; // 4 columns for primary stats, then secondary stats below
    }
}
