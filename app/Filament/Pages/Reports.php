<?php

// app/Filament/Pages/Reports.php - New Reports page
namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Customer;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\BillingPeriod;
use Carbon\Carbon;

class Reports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static string $view = 'filament.pages.reports';
    protected static ?string $title = 'Laporan';
    protected static ?string $navigationGroup = 'Laporan';

    public $selectedPeriod = null;
    public $selectedVillage = null;

    public function mount()
    {
        $this->selectedPeriod = BillingPeriod::where('status', 'active')->first()?->period_id;
        $this->selectedVillage = config('pamdes.current_village.id');
    }

    public function getCollectionReport()
    {
        $query = Payment::with(['bill.waterUsage.customer']);

        if ($this->selectedVillage) {
            $query->whereHas('bill.waterUsage.customer', function ($q) {
                $q->where('village_id', $this->selectedVillage);
            });
        }

        return $query->thisMonth()->get()->groupBy('payment_method');
    }

    public function getOutstandingReport()
    {
        $query = Bill::with(['waterUsage.customer', 'waterUsage.billingPeriod'])
            ->where('status', '!=', 'paid');

        if ($this->selectedVillage) {
            $query->whereHas('waterUsage.customer', function ($q) {
                $q->where('village_id', $this->selectedVillage);
            });
        }

        return $query->get();
    }

    public function getUsageReport()
    {
        if (!$this->selectedPeriod) return collect();

        $period = BillingPeriod::find($this->selectedPeriod);
        if (!$period) return collect();

        return $period->waterUsages()
            ->with('customer')
            ->orderBy('total_usage_m3', 'desc')
            ->limit(10)
            ->get();
    }
}
