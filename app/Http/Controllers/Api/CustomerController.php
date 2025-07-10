<?php
// app/Http/Controllers/Api/CustomerController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    /**
     * Get customers for a specific village
     */
    public function index(Request $request): JsonResponse
    {
        $villageId = $request->get('village_id') ?? $request->attributes->get('village_id');

        $query = Customer::query();

        if ($villageId) {
            $query->byVillage($villageId);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $customers = $query->with(['waterUsages.billingPeriod'])
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $customers->items(),
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    /**
     * Get customer summary for village dashboard
     */
    public function summary(Request $request): JsonResponse
    {
        $villageId = $request->get('village_id') ?? $request->attributes->get('village_id');

        $query = Customer::query();

        if ($villageId) {
            $query->byVillage($villageId);
        }

        $totalCustomers = $query->count();
        $activeCustomers = $query->active()->count();
        $totalOutstanding = \App\Models\Bill::whereHas('waterUsage.customer', function ($q) use ($villageId) {
            if ($villageId) {
                $q->where('village_id', $villageId);
            }
        })->unpaid()->sum('total_amount');

        $overdueCount = \App\Models\Bill::whereHas('waterUsage.customer', function ($q) use ($villageId) {
            if ($villageId) {
                $q->where('village_id', $villageId);
            }
        })->overdue()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_customers' => $totalCustomers,
                'active_customers' => $activeCustomers,
                'inactive_customers' => $totalCustomers - $activeCustomers,
                'total_outstanding' => $totalOutstanding,
                'overdue_bills_count' => $overdueCount,
                'collection_rate' => $this->calculateCollectionRate($villageId),
            ],
        ]);
    }

    private function calculateCollectionRate(?string $villageId): float
    {
        $query = \App\Models\Bill::whereHas('waterUsage.customer', function ($q) use ($villageId) {
            if ($villageId) {
                $q->where('village_id', $villageId);
            }
        });

        $totalBilled = $query->sum('total_amount');
        $totalPaid = $query->paid()->sum('total_amount');

        if ($totalBilled == 0) return 0;

        return ($totalPaid / $totalBilled) * 100;
    }
}
