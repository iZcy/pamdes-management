<?php
// routes/web.php - Fixed version without closure middleware

use Illuminate\Support\Facades\Route;
use App\Models\Customer;
use App\Models\Bill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Web Routes - Multi-Tenant (Fixed)
|--------------------------------------------------------------------------
*/

// Apply tenant context to all routes
Route::middleware(['village.context'])->group(function () {

    // Super Admin Routes (localhost or APP_URL)
    Route::prefix('super-admin')->name('super.')->group(function () {
        Route::get('/', function () {
            // Check if user has super admin access
            if (!request()->attributes->get('is_super_admin')) {
                abort(404);
            }

            // Super admin dashboard - can see all villages
            $villages = \App\Models\Village::with(['customers', 'billingPeriods'])->get();
            return view('super-admin.dashboard', compact('villages'));
        })->name('dashboard');

        Route::get('/villages', function () {
            if (!request()->attributes->get('is_super_admin')) {
                abort(404);
            }

            $villages = \App\Models\Village::with(['customers', 'billingPeriods'])->get();
            return view('super-admin.villages', compact('villages'));
        })->name('villages');
    });

    // Default route - handle based on tenant type
    Route::get('/', function () {
        $tenantType = request()->attributes->get('tenant_type');

        switch ($tenantType) {
            case 'super_admin':
                // Redirect to admin panel for super admin
                return redirect('/admin');

            case 'public_website':
                // Main PAMDes website homepage
                $villages = \App\Models\Village::active()->get();
                return view('public.homepage', compact('villages'));

            case 'village_website':
                // Village homepage
                $village = request()->attributes->get('village');
                $stats = [
                    'total_customers' => \App\Models\Customer::byVillage($village['id'])->count(),
                    'active_customers' => \App\Models\Customer::byVillage($village['id'])->active()->count(),
                    'total_outstanding' => \App\Models\Bill::whereHas('waterUsage.customer', function ($q) use ($village) {
                        $q->where('village_id', $village['id']);
                    })->unpaid()->sum('total_amount'),
                ];
                return view('village.homepage', compact('village', 'stats'));

            case 'village_not_found':
                abort(404, 'Village not found');

            default:
                abort(404, 'Unknown domain');
        }
    })->name('home');

    // Village-Specific Routes - only for village websites
    Route::group(['middleware' => 'ensure.village'], function () {

        // Customer Portal (public access)
        Route::prefix('portal')->name('village.portal')->group(function () {

            Route::get('/', function () {
                $village = request()->attributes->get('village');
                return view('village.portal.index', compact('village'));
            })->name('');

            Route::get('/check/{customer_code}', function ($customerCode) {
                $village = request()->attributes->get('village');

                try {
                    $customer = Customer::where('customer_code', $customerCode)
                        ->byVillage($village['id'])
                        ->firstOrFail();

                    $bills = $customer->bills()->unpaid()->with(['waterUsage.billingPeriod'])->get();

                    return view('village.portal.bills', compact('village', 'customer', 'bills'));
                } catch (\Exception $e) {
                    abort(404, 'Customer not found');
                }
            })->name('.bills');

            Route::post('/lookup', function () {
                $village = request()->attributes->get('village');

                try {
                    request()->validate([
                        'customer_code' => 'required|string|max:20'
                    ]);

                    $customer = Customer::where('customer_code', request('customer_code'))
                        ->byVillage($village['id'])
                        ->first();

                    if (!$customer) {
                        return back()->withErrors(['customer_code' => 'Kode pelanggan tidak ditemukan.']);
                    }

                    return redirect()->route('village.portal.bills', $customer->customer_code);
                } catch (\Exception $e) {
                    return back()->withErrors(['customer_code' => 'Terjadi kesalahan. Silakan coba lagi.']);
                }
            })->name('.lookup');
        });

        // Village API endpoints
        Route::prefix('api')->name('village.api')->group(function () {

            Route::get('/stats', function () {
                $village = request()->attributes->get('village');

                return response()->json([
                    'village' => $village,
                    'stats' => [
                        'customers' => \App\Models\Customer::byVillage($village['id'])->count(),
                        'active_customers' => \App\Models\Customer::byVillage($village['id'])->active()->count(),
                        'outstanding' => \App\Models\Bill::whereHas('waterUsage.customer', function ($q) use ($village) {
                            $q->where('village_id', $village['id']);
                        })->unpaid()->sum('total_amount'),
                    ]
                ]);
            })->name('.stats');

            Route::get('/customer/{customer_code}', function ($customerCode) {
                $village = request()->attributes->get('village');

                try {
                    $customer = Customer::where('customer_code', $customerCode)
                        ->byVillage($village['id'])
                        ->with(['bills.waterUsage.billingPeriod'])
                        ->first();

                    if (!$customer) {
                        return response()->json(['error' => 'Customer not found'], 404);
                    }

                    return response()->json([
                        'customer' => $customer,
                        'bills' => $customer->bills->map(function ($bill) {
                            return [
                                'id' => $bill->bill_id,
                                'period' => $bill->waterUsage->billingPeriod->period_name,
                                'usage' => $bill->waterUsage->total_usage_m3,
                                'amount' => $bill->total_amount,
                                'status' => $bill->status,
                                'due_date' => $bill->due_date,
                                'is_overdue' => $bill->is_overdue,
                            ];
                        })
                    ]);
                } catch (\Exception $e) {
                    return response()->json(['error' => 'Internal server error'], 500);
                }
            })->name('.customer');
        });
    });

    // Public Website Routes (pamdes.local only)
    Route::group(['middleware' => 'ensure.public'], function () {

        Route::get('/villages', function () {
            $villages = \App\Models\Village::active()->get();
            return view('public.villages', compact('villages'));
        })->name('public.villages');

        Route::get('/about', function () {
            return view('public.about');
        })->name('public.about');
    });
});

// Admin routes (protected by auth)
Route::middleware(['auth', 'village.access'])->prefix('admin')->group(function () {

    // Receipt printing routes
    Route::get('payments/{payment}/receipt', function (\App\Models\Payment $payment) {
        try {
            return view('receipts.payment', compact('payment'));
        } catch (\Exception $e) {
            Log::error('Error generating payment receipt: ' . $e->getMessage());
            abort(500, 'Unable to generate receipt');
        }
    })->name('payment.receipt');

    Route::get('bills/{bill}/invoice', function (Bill $bill) {
        try {
            return view('receipts.invoice', compact('bill'));
        } catch (\Exception $e) {
            Log::error('Error generating bill invoice: ' . $e->getMessage());
            abort(500, 'Unable to generate invoice');
        }
    })->name('bill.invoice');
});

// Health check (no middleware)
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'tenant' => request()->attributes->get('tenant_type', 'unknown'),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'timestamp' => now()->toISOString(),
        ], 500);
    }
});
