<?php

use Illuminate\Support\Facades\Route;
use App\Models\Customer;
use App\Models\Bill;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

// Public homepage - customer portal
// Route::get('/', function () {
//     $village = request()->attributes->get('village');
//     return view('customer-portal.index', compact('village'));
// })->middleware(['village.context'])->name('home');

// Village-specific routes (with village context middleware)
Route::middleware(['village.context'])->group(function () {

    // Public customer portal
    Route::prefix('portal')->group(function () {
        Route::get('/', function () {
            $village = request()->attributes->get('village');
            return view('customer-portal.index', compact('village'));
        })->name('customer.portal');

        Route::get('/bill/{customer_code}', function ($customerCode) {
            $customer = Customer::where('customer_code', $customerCode)->firstOrFail();
            $bills = $customer->bills()->unpaid()->get();
            return view('customer-portal.bills', compact('customer', 'bills'));
        })->name('customer.bills');

        // Public bill lookup (POST request for security)
        Route::post('/lookup', function () {
            $request = request();
            $request->validate([
                'customer_code' => 'required|string|max:20'
            ]);

            $customer = Customer::where('customer_code', $request->customer_code)->first();

            if (!$customer) {
                return back()->withErrors(['customer_code' => 'Kode pelanggan tidak ditemukan.']);
            }

            return redirect()->route('customer.bills', $customer->customer_code);
        })->name('customer.lookup');
    });

    // Public bill check API endpoint
    Route::get('/api/bill-check/{customer_code}', function ($customerCode) {
        $customer = Customer::where('customer_code', $customerCode)->first();

        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $bills = $customer->bills()->unpaid()->with(['waterUsage.billingPeriod'])->get();

        return response()->json([
            'customer' => [
                'name' => $customer->name,
                'code' => $customer->customer_code,
                'address' => $customer->full_address,
                'status' => $customer->status,
            ],
            'bills' => $bills->map(function ($bill) {
                return [
                    'period' => $bill->waterUsage->billingPeriod->period_name,
                    'usage' => $bill->waterUsage->total_usage_m3,
                    'amount' => $bill->total_amount,
                    'due_date' => $bill->due_date->format('Y-m-d'),
                    'is_overdue' => $bill->is_overdue,
                    'days_overdue' => $bill->days_overdue,
                ];
            }),
            'total_outstanding' => $bills->sum('total_amount'),
        ]);
    });
});

// Admin routes
Route::middleware(['auth'])->prefix('admin')->group(function () {
    // Receipt printing routes
    Route::get('payments/{payment}/receipt', function (\App\Models\Payment $payment) {
        return view('receipts.payment', compact('payment'));
    })->name('payment.receipt');

    Route::get('bills/{bill}/invoice', function (Bill $bill) {
        return view('receipts.invoice', compact('bill'));
    })->name('bill.invoice');
});
