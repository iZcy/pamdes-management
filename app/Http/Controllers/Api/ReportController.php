<?php

// app/Http/Controllers/Api/ReportController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\BillingPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Get comprehensive PAMDes report for village integration
     */
    public function villageReport(Request $request): JsonResponse
    {
        $villageId = $request->get('village_id') ?? $request->attributes->get('village_id');

        if (!$villageId) {
            return response()->json(['error' => 'Village ID required'], 400);
        }

        $currentMonth = Carbon::now();
        $previousMonth = Carbon::now()->subMonth();

        return response()->json([
            'success' => true,
            'data' => [
                'village_id' => $villageId,
                'report_date' => $currentMonth->toDateString(),
                'customers' => $this->getCustomerStats($villageId),
                'billing' => $this->getBillingStats($villageId, $currentMonth),
                'payments' => $this->getPaymentStats($villageId, $currentMonth),
                'trends' => $this->getTrendData($villageId, $currentMonth, $previousMonth),
                'alerts' => $this->getAlerts($villageId),
            ],
        ]);
    }

    private function getCustomerStats(string $villageId): array
    {
        $customers = Customer::byVillage($villageId);

        return [
            'total' => $customers->count(),
            'active' => $customers->active()->count(),
            'with_outstanding' => $customers->whereHas('bills', function ($q) {
                $q->unpaid();
            })->count(),
        ];
    }

    private function getBillingStats(string $villageId, Carbon $month): array
    {
        $bills = Bill::whereHas('waterUsage.customer', function ($q) use ($villageId) {
            $q->where('village_id', $villageId);
        })->whereMonth('created_at', $month->month)->whereYear('created_at', $month->year);

        return [
            'total_bills' => $bills->count(),
            'total_amount' => $bills->sum('total_amount'),
            'paid_bills' => $bills->paid()->count(),
            'paid_amount' => $bills->paid()->sum('total_amount'),
            'overdue_bills' => $bills->overdue()->count(),
            'overdue_amount' => $bills->overdue()->sum('total_amount'),
        ];
    }

    private function getPaymentStats(string $villageId, Carbon $month): array
    {
        $payments = Payment::whereHas('bill.waterUsage.customer', function ($q) use ($villageId) {
            $q->where('village_id', $villageId);
        })->whereMonth('payment_date', $month->month)->whereYear('payment_date', $month->year);

        return [
            'total_payments' => $payments->count(),
            'total_amount' => $payments->sum('amount_paid'),
            'payment_methods' => $payments->selectRaw('payment_method, COUNT(*) as count, SUM(amount_paid) as total')
                ->groupBy('payment_method')
                ->get()
                ->keyBy('payment_method')
                ->toArray(),
        ];
    }

    private function getTrendData(string $villageId, Carbon $current, Carbon $previous): array
    {
        $currentStats = $this->getBillingStats($villageId, $current);
        $previousStats = $this->getBillingStats($villageId, $previous);

        return [
            'billing_growth' => $this->calculateGrowthRate(
                $currentStats['total_amount'],
                $previousStats['total_amount']
            ),
            'collection_improvement' => $this->calculateGrowthRate(
                $currentStats['paid_amount'],
                $previousStats['paid_amount']
            ),
            'customer_payment_trend' => $this->calculateGrowthRate(
                $currentStats['paid_bills'],
                $previousStats['paid_bills']
            ),
        ];
    }

    private function getAlerts(string $villageId): array
    {
        $alerts = [];

        // Check for high overdue rate
        $overdueRate = $this->calculateOverdueRate($villageId);
        if ($overdueRate > 20) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "Tingkat keterlambatan pembayaran tinggi: {$overdueRate}%",
                'action' => 'review_collection_process',
            ];
        }

        // Check for customers with long outstanding bills
        $longOverdueCount = Bill::whereHas('waterUsage.customer', function ($q) use ($villageId) {
            $q->where('village_id', $villageId);
        })->where('due_date', '<', Carbon::now()->subDays(90))->unpaid()->count();

        if ($longOverdueCount > 0) {
            $alerts[] = [
                'type' => 'danger',
                'message' => "{$longOverdueCount} pelanggan memiliki tunggakan lebih dari 3 bulan",
                'action' => 'escalate_collection',
            ];
        }

        return $alerts;
    }

    private function calculateGrowthRate(float $current, float $previous): float
    {
        if ($previous == 0) return $current > 0 ? 100 : 0;
        return (($current - $previous) / $previous) * 100;
    }

    private function calculateOverdueRate(string $villageId): float
    {
        $totalBills = Bill::whereHas('waterUsage.customer', function ($q) use ($villageId) {
            $q->where('village_id', $villageId);
        })->count();

        if ($totalBills == 0) return 0;

        $overdueBills = Bill::whereHas('waterUsage.customer', function ($q) use ($villageId) {
            $q->where('village_id', $villageId);
        })->overdue()->count();

        return ($overdueBills / $totalBills) * 100;
    }
}
