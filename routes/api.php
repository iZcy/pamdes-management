<?php
// routes/api.php - Fixed version without closure middleware

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Customer;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\User;
use App\Models\Village;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| API Routes - Clean PAMDes System
|--------------------------------------------------------------------------
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public API routes (with rate limiting)
Route::middleware(['throttle:60,1'])->group(function () {

    // Health check
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'PAMDes Management System',
            'timestamp' => now()->toISOString(),
        ]);
    });

    // Village information
    Route::get('/village/{id}', function ($id) {
        $village = Village::find($id);

        if (!$village) {
            return response()->json(['error' => 'Village not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $village,
        ]);
    });

    // Customer lookup by village
    Route::get('/village/{villageId}/customer/{customerCode}', function ($villageId, $customerCode) {
        $customer = Customer::where('customer_code', $customerCode)
            ->where('village_id', $villageId)
            ->with(['bills' => function ($query) {
                $query->unpaid()->with(['waterUsage.billingPeriod']);
            }])
            ->first();

        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => [
                    'code' => $customer->customer_code,
                    'name' => $customer->name,
                    'address' => $customer->full_address,
                    'status' => $customer->status,
                ],
                'bills' => $customer->bills->map(function ($bill) {
                    return [
                        'id' => $bill->bill_id,
                        'period' => $bill->waterUsage->billingPeriod->period_name,
                        'usage' => $bill->waterUsage->total_usage_m3,
                        'amount' => $bill->total_amount,
                        'status' => $bill->status,
                        'due_date' => $bill->due_date,
                        'is_overdue' => $bill->is_overdue,
                        'days_overdue' => $bill->days_overdue,
                    ];
                }),
                'total_outstanding' => $customer->bills->sum('total_amount'),
            ],
        ]);
    });

    // Village statistics
    Route::get('/village/{villageId}/stats', function ($villageId) {
        return response()->json([
            'success' => true,
            'data' => [
                'customers' => [
                    'total' => Customer::where('village_id', $villageId)->count(),
                    'active' => Customer::where('village_id', $villageId)->active()->count(),
                ],
                'billing' => [
                    'outstanding_amount' => Bill::whereHas('waterUsage.customer', function ($q) use ($villageId) {
                        $q->where('village_id', $villageId);
                    })->unpaid()->sum('total_amount'),
                    'overdue_count' => Bill::whereHas('waterUsage.customer', function ($q) use ($villageId) {
                        $q->where('village_id', $villageId);
                    })->overdue()->count(),
                ],
                'payments' => [
                    'this_month' => Payment::whereHas('bill.waterUsage.customer', function ($q) use ($villageId) {
                        $q->where('village_id', $villageId);
                    })->thisMonth()->sum('amount_paid'),
                ],
            ],
        ]);
    });
});

// Protected API routes (require authentication)
Route::middleware(['auth:sanctum'])->group(function () {

    // Admin dashboard data
    Route::get('/admin/dashboard', function () {
        $user = User::find(Auth::user()->id);
        $villageId = $user->getCurrentVillageContext();

        if (!$villageId) {
            return response()->json(['error' => 'No village context'], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'village' => Village::find($villageId),
                'stats' => [
                    'customers' => Customer::where('village_id', $villageId)->count(),
                    'active_customers' => Customer::where('village_id', $villageId)->active()->count(),
                    'bills_unpaid' => Bill::whereHas('waterUsage.customer', function ($q) use ($villageId) {
                        $q->where('village_id', $villageId);
                    })->unpaid()->count(),
                    'payments_today' => Payment::whereHas('bill.waterUsage.customer', function ($q) use ($villageId) {
                        $q->where('village_id', $villageId);
                    })->whereDate('payment_date', today())->count(),
                ],
            ],
        ]);
    });

    // Super admin only: Village management
    Route::prefix('admin/villages')->group(function () {

        Route::get('/', function () {
            if (!User::find(Auth::user()->id)?->isSuperAdmin()) {
                return response()->json(['error' => 'Super admin access required'], 403);
            }

            return response()->json([
                'success' => true,
                'data' => Village::with(['customers'])->get(),
            ]);
        });

        Route::post('/', function (Request $request) {
            if (!User::find(Auth::user()->id)?->isSuperAdmin()) {
                return response()->json(['error' => 'Super admin access required'], 403);
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'slug' => 'required|string|max:255|unique:villages,slug',
                'description' => 'nullable|string',
                'phone_number' => 'nullable|string',
                'email' => 'nullable|email',
                'address' => 'nullable|string',
            ]);

            $village = app(\App\Services\VillageService::class)->createVillage($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Village created successfully',
                'data' => $village,
            ], 201);
        });
    });
});
