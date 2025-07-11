<?php
// routes/web.php - Updated with better error handling

use Illuminate\Support\Facades\Route;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

require __DIR__ . '/tripay.php';

// Apply village context middleware to all routes
Route::middleware(['village.context'])->group(function () {
    Route::get('/', function () {
        return redirect(filament()->getLoginUrl());
    })->name('home');

    // Customer Portal - Village-specific bill checking
    Route::prefix('portal')->name('portal.')->group(function () {
        Route::get('/', function () {
            $village = config('pamdes.current_village');

            // Debug session and CSRF
            Log::info('Portal index accessed', [
                'village' => $village,
                'session_id' => session()->getId(),
                'csrf_token' => csrf_token(),
                'host' => request()->getHost(),
            ]);

            return view('customer-portal.index', compact('village'));
        })->name('index');

        Route::post('/lookup', function () {
            // Add debugging
            Log::info('Lookup request received', [
                'request_data' => request()->all(),
                'session_id' => session()->getId(),
                'csrf_token' => csrf_token(),
                'host' => request()->getHost(),
                'village_id' => config('pamdes.current_village_id'),
            ]);

            try {
                request()->validate([
                    'customer_code' => 'required|string|max:20'
                ]);

                $villageId = config('pamdes.current_village_id');

                if (!$villageId) {
                    Log::error('Village context not found', [
                        'host' => request()->getHost(),
                        'config' => config('pamdes'),
                    ]);
                    return back()->withErrors(['customer_code' => 'Village context not found.']);
                }

                $customer = Customer::where('customer_code', request('customer_code'))
                    ->where('village_id', $villageId)
                    ->first();

                if (!$customer) {
                    Log::info('Customer not found', [
                        'customer_code' => request('customer_code'),
                        'village_id' => $villageId,
                    ]);
                    return back()->withErrors(['customer_code' => 'Kode pelanggan tidak ditemukan.']);
                }

                Log::info('Customer found, redirecting', [
                    'customer_code' => $customer->customer_code,
                    'customer_id' => $customer->id,
                ]);

                return redirect()->route('portal.bills', $customer->customer_code);
            } catch (\Exception $e) {
                Log::error('Error in lookup', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return back()->withErrors(['customer_code' => 'Terjadi kesalahan. Silakan coba lagi.']);
            }
        })->name('lookup');

        Route::get('/bills/{customer_code}', function ($customerCode) {
            $villageId = config('pamdes.current_village_id');

            if (!$villageId) {
                abort(404, 'Village not found');
            }

            $customer = Customer::where('customer_code', $customerCode)
                ->where('village_id', $villageId)
                ->firstOrFail();

            // Get unpaid bills
            $bills = $customer->bills()
                ->unpaid()
                ->with(['waterUsage.billingPeriod'])
                ->get();

            // Get paid bills (last 10 for history)
            $paidBills = $customer->bills()
                ->paid()
                ->with(['waterUsage.billingPeriod', 'latestPayment'])
                ->orderBy('payment_date', 'desc')
                ->limit(10)
                ->get();

            return view('customer-portal.bills', compact('customer', 'bills', 'paidBills'));
        })->name('bills');
    });

    Route::fallback(function () {
        return redirect(filament()->getLoginUrl());
    });
});

// Admin routes (protected by auth)
Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('payments/{payment}/receipt', function (\App\Models\Payment $payment) {
        return view('receipts.payment', compact('payment'));
    })->name('payment.receipt');

    Route::get('bills/{bill}/invoice', function (\App\Models\Bill $bill) {
        return view('receipts.invoice', compact('bill'));
    })->name('bill.invoice');
});

// Health check and debug routes
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'village' => config('pamdes.current_village.name', 'Unknown'),
        'host' => request()->getHost(),
    ]);
});
