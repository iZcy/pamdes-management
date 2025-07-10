<?php
// app/Services/ReportService.php - New service for independent reporting

namespace App\Services;

use App\Models\Village;
use App\Models\Customer;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\BillingPeriod;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ReportService
{
    public function generateVillageReport(string $villageId, array $options = []): array
    {
        $village = Village::find($villageId);
        if (!$village) {
            throw new \Exception("Village not found: {$villageId}");
        }

        $period = $options['period'] ?? 'current_month';
        $dateRange = $this->getDateRange($period, $options);

        return [
            'village' => [
                'id' => $village->id,
                'name' => $village->name,
                'slug' => $village->slug,
            ],
            'period' => [
                'type' => $period,
                'start_date' => $dateRange['start']->toDateString(),
                'end_date' => $dateRange['end']->toDateString(),
            ],
            'generated_at' => now()->toISOString(),
            'customers' => $this->getCustomerStats($villageId),
            'billing' => $this->getBillingStats($villageId, $dateRange),
            'payments' => $this->getPaymentStats($villageId, $dateRange),
            'financial' => $this->getFinancialSummary($villageId, $dateRange),
        ];
    }

    protected function getDateRange(string $period, array $options): array
    {
        switch ($period) {
            case 'current_month':
                return [
                    'start' => now()->startOfMonth(),
                    'end' => now()->endOfMonth(),
                ];
            case 'last_month':
                return [
                    'start' => now()->subMonth()->startOfMonth(),
                    'end' => now()->subMonth()->endOfMonth(),
                ];
            case 'current_year':
                return [
                    'start' => now()->startOfYear(),
                    'end' => now()->endOfYear(),
                ];
            case 'custom':
                return [
                    'start' => Carbon::parse($options['start_date']),
                    'end' => Carbon::parse($options['end_date']),
                ];
            default:
                return [
                    'start' => now()->startOfMonth(),
                    'end' => now()->endOfMonth(),
                ];
        }
    }

    protected function getCustomerStats(string $villageId): array
    {
        $customers = Customer::byVillage($villageId);

        return [
            'total' => $customers->count(),
            'active' => $customers->active()->count(),
            'with_outstanding' => $customers->whereHas('bills', function ($q) {
                $q->unpaid();
            })->count(),
            'new_this_month' => $customers->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];
    }

    protected function getBillingStats(string $villageId, array $dateRange): array
    {
        $bills = Bill::whereHas('waterUsage.customer', function ($q) use ($villageId) {
            $q->where('village_id', $villageId);
        })->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);

        return [
            'total_bills' => $bills->count(),
            'total_amount' => $bills->sum('total_amount'),
            'paid_bills' => $bills->paid()->count(),
            'paid_amount' => $bills->paid()->sum('total_amount'),
            'overdue_bills' => $bills->overdue()->count(),
            'overdue_amount' => $bills->overdue()->sum('total_amount'),
            'collection_rate' => $this->calculateCollectionRate($bills),
        ];
    }

    protected function getPaymentStats(string $villageId, array $dateRange): array
    {
        $payments = Payment::whereHas('bill.waterUsage.customer', function ($q) use ($villageId) {
            $q->where('village_id', $villageId);
        })->whereBetween('payment_date', [$dateRange['start'], $dateRange['end']]);

        return [
            'total_payments' => $payments->count(),
            'total_amount' => $payments->sum('amount_paid'),
            'by_method' => $payments->selectRaw('payment_method, COUNT(*) as count, SUM(amount_paid) as total')
                ->groupBy('payment_method')
                ->get()
                ->keyBy('payment_method')
                ->toArray(),
        ];
    }

    protected function getFinancialSummary(string $villageId, array $dateRange): array
    {
        $revenue = Payment::whereHas('bill.waterUsage.customer', function ($q) use ($villageId) {
            $q->where('village_id', $villageId);
        })->whereBetween('payment_date', [$dateRange['start'], $dateRange['end']])->sum('amount_paid');

        $outstanding = Bill::whereHas('waterUsage.customer', function ($q) use ($villageId) {
            $q->where('village_id', $villageId);
        })->unpaid()->sum('total_amount');

        return [
            'revenue' => $revenue,
            'outstanding' => $outstanding,
            'collection_efficiency' => $revenue > 0 ? ($revenue / ($revenue + $outstanding)) * 100 : 0,
        ];
    }

    protected function calculateCollectionRate($billsQuery): float
    {
        $total = $billsQuery->sum('total_amount');
        $paid = $billsQuery->paid()->sum('total_amount');

        return $total > 0 ? ($paid / $total) * 100 : 0;
    }

    public function exportReport(array $reportData, string $format = 'json'): string
    {
        $fileName = "exports/report_{$reportData['village']['slug']}_{$reportData['period']['type']}_" . now()->format('Y-m-d_H-i-s');

        switch ($format) {
            case 'json':
                $fileName .= '.json';
                Storage::put($fileName, json_encode($reportData, JSON_PRETTY_PRINT));
                break;
            case 'csv':
                $fileName .= '.csv';
                $this->exportToCsv($reportData, $fileName);
                break;
            default:
                throw new \Exception("Unsupported export format: {$format}");
        }

        return $fileName;
    }

    protected function exportToCsv(array $reportData, string $fileName): void
    {
        $csvData = [];

        // Add summary data
        $csvData[] = ['Section', 'Metric', 'Value'];
        $csvData[] = ['Village', 'Name', $reportData['village']['name']];
        $csvData[] = ['Period', 'Start Date', $reportData['period']['start_date']];
        $csvData[] = ['Period', 'End Date', $reportData['period']['end_date']];

        // Add customer stats
        foreach ($reportData['customers'] as $key => $value) {
            $csvData[] = ['Customers', ucfirst(str_replace('_', ' ', $key)), $value];
        }

        // Add billing stats
        foreach ($reportData['billing'] as $key => $value) {
            $csvData[] = ['Billing', ucfirst(str_replace('_', ' ', $key)), $value];
        }

        // Add payment stats
        foreach ($reportData['payments'] as $key => $value) {
            if (is_array($value)) continue; // Skip complex arrays for CSV
            $csvData[] = ['Payments', ucfirst(str_replace('_', ' ', $key)), $value];
        }

        $handle = fopen('php://temp', 'w');
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        Storage::put($fileName, $csvContent);
    }
}
