<?php
// app/Filament/Widgets/StatsOverview.php - Simplified version
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
use App\Models\Village;
use Illuminate\Support\Facades\Auth;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $user = Auth::user();
        $user = User::find($user->id);

        // Super admin sees aggregated stats across all villages
        if ($user && $user->isSuperAdmin()) {
            return $this->getSuperAdminStats();
        }

        // Village admin sees village-specific stats
        $currentVillage = $user?->getCurrentVillageContext();

        if (!$currentVillage) {
            return [
                Stat::make('Tidak Ada Akses', 'No Village Access')
                    ->description('Silakan hubungi administrator')
                    ->color('danger'),
            ];
        }

        return $this->getVillageStats($currentVillage);
    }

    /**
     * Super admin stats - aggregated across all villages
     */
    protected function getSuperAdminStats(): array
    {
        $totalVillages = Village::active()->count();
        $totalCustomers = Customer::count();
        $activeCustomers = Customer::active()->count();
        $totalRevenue = Payment::sum('total_amount');
        $thisMonthRevenue = Payment::whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)->sum('total_amount');
        $unpaidBills = Bill::where('status', '!=', 'paid')->count();
        $overdueBills = Bill::where('status', 'overdue')->count();
        $todayPayments = Payment::whereDate('payment_date', today())->count();

        return [
            Stat::make('Total Desa', number_format($totalVillages))
                ->description('Desa aktif dalam sistem')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('primary')
                ->url(route('filament.admin.resources.villages.index')),

            Stat::make('Total Pelanggan', number_format($totalCustomers))
                ->description("{$activeCustomers} aktif di seluruh sistem")
                ->descriptionIcon('heroicon-m-users')
                ->color('info')
                ->chart($this->getGlobalCustomerTrend()),

            Stat::make('Pendapatan Global', 'Rp ' . number_format($totalRevenue))
                ->description('Rp ' . number_format($thisMonthRevenue) . ' bulan ini')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart($this->getGlobalRevenueTrend()),

            Stat::make('Tagihan Menunggak', number_format($unpaidBills))
                ->description("{$overdueBills} terlambat | {$todayPayments} bayar hari ini")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueBills > 0 ? 'danger' : 'warning')
                ->chart($this->getGlobalBillTrend()),
        ];
    }

    /**
     * Village-specific stats
     */
    protected function getVillageStats(string $villageId): array
    {
        // Get village name for display
        $village = Village::find($villageId);
        $villageName = $village?->name ?? 'Unknown';

        // Build queries with village filter
        $customerQuery = Customer::where('village_id', $villageId);
        $billQuery = Bill::whereHas('waterUsage.customer', fn($q) => $q->where('village_id', $villageId));
        $paymentQuery = Payment::whereHas('bills.customer', fn($q) => $q->where('village_id', $villageId));

        // Calculate stats
        $totalCustomers = $customerQuery->count();
        $activeCustomers = $customerQuery->active()->count();
        $newCustomersThisMonth = $customerQuery->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->count();

        $unpaidBills = $billQuery->where('status', '!=', 'paid')->count();
        $overdueBills = $billQuery->where('status', 'overdue')->count();
        $unpaidAmount = $billQuery->where('status', '!=', 'paid')->sum('total_amount');

        $thisMonthPayments = $paymentQuery->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)->count();
        $todayPayments = $paymentQuery->whereDate('payment_date', today())->count();
        $thisMonthRevenue = $paymentQuery->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)->sum('total_amount');
        $totalRevenue = $paymentQuery->sum('total_amount');

        // System resources stats
        $totalUsages = WaterUsage::whereHas('customer', fn($q) => $q->where('village_id', $villageId))->count();
        $activePeriods = BillingPeriod::where('village_id', $villageId)->where('status', 'active')->count();
        $totalTariffs = WaterTariff::where('village_id', $villageId)->count();

        return [
            // Primary Stats Row
            Stat::make('Total Pelanggan', number_format($totalCustomers))
                ->description("Desa {$villageName} | {$activeCustomers} aktif")
                ->descriptionIcon('heroicon-m-users')
                ->color('primary')
                ->chart($this->getCustomerTrend($villageId))
                ->url(route('filament.admin.resources.customers.index')),

            Stat::make('Tagihan Menunggak', 'Rp ' . number_format($unpaidAmount))
                ->description("{$unpaidBills} tagihan | {$overdueBills} terlambat")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueBills > 0 ? 'danger' : 'warning')
                ->chart($this->getBillTrend($villageId))
                ->url(route('filament.admin.resources.bills.index')),

            Stat::make('Pendapatan Bulan Ini', 'Rp ' . number_format($thisMonthRevenue))
                ->description("{$thisMonthPayments} pembayaran | {$todayPayments} hari ini")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart($this->getRevenueTrend($villageId))
                ->url(route('filament.admin.resources.payments.index')),

            Stat::make('Sistem & Data', "{$totalUsages} pembacaan")
                ->description("{$activePeriods} periode aktif | {$totalTariffs} tarif")
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('info')
                ->chart($this->getUsageTrend($villageId))
                ->url(route('filament.admin.resources.water-usages.index')),
        ];
    }

    // Global trend methods for super admin
    protected function getGlobalCustomerTrend(): array
    {
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = Customer::whereDate('created_at', '<=', $date->endOfMonth())->count();
            $trend[] = $count;
        }
        return $trend;
    }

    protected function getGlobalRevenueTrend(): array
    {
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $revenue = Payment::whereMonth('payment_date', $date->month)
                ->whereYear('payment_date', $date->year)
                ->sum('total_amount');
            $trend[] = $revenue / 1000000; // Convert to millions
        }
        return $trend;
    }

    protected function getGlobalBillTrend(): array
    {
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $amount = Bill::where('status', '!=', 'paid')
                ->whereDate('created_at', '<=', $date)
                ->sum('total_amount');
            $trend[] = $amount / 1000000; // Convert to millions
        }
        return $trend;
    }

    // Village-specific trend methods
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

    protected function getBillTrend(string $villageId): array
    {
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $amount = Bill::whereHas('waterUsage.customer', fn($q) => $q->where('village_id', $villageId))
                ->where('status', '!=', 'paid')
                ->whereDate('created_at', '<=', $date)
                ->sum('total_amount');
            $trend[] = $amount / 1000000;
        }
        return $trend;
    }

    protected function getRevenueTrend(string $villageId): array
    {
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $revenue = Payment::whereHas('bills.customer', fn($q) => $q->where('village_id', $villageId))
                ->whereMonth('payment_date', $date->month)
                ->whereYear('payment_date', $date->year)
                ->sum('total_amount');
            $trend[] = $revenue / 1000000;
        }
        return $trend;
    }

    protected function getUsageTrend(string $villageId): array
    {
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = WaterUsage::whereHas('customer', fn($q) => $q->where('village_id', $villageId))
                ->whereMonth('usage_date', $date->month)
                ->whereYear('usage_date', $date->year)
                ->count();
            $trend[] = $count;
        }
        return $trend;
    }

    protected function getColumns(): int
    {
        return 4;
    }
}
