<?php

// routes/web.php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    // Redirect to admin panel or show landing page
    return redirect('/admin');
});

// Village-specific routes (with village context middleware)
Route::middleware(['village.context'])->group(function () {

    // Public customer portal (if needed)
    Route::prefix('portal')->group(function () {
        Route::get('/', function () {
            $village = request()->attributes->get('village');
            return view('customer-portal.index', compact('village'));
        })->name('customer.portal');

        Route::get('/bill/{customer_code}', function ($customerCode) {
            // Show customer bill information
            $customer = \App\Models\Customer::where('customer_code', $customerCode)->firstOrFail();
            $bills = $customer->bills()->unpaid()->get();
            return view('customer-portal.bills', compact('customer', 'bills'));
        })->name('customer.bills');
    });
});

// Receipt printing routes
Route::middleware(['auth:admin'])->prefix('admin')->group(function () {
    Route::get('payments/{payment}/receipt', function (\App\Models\Payment $payment) {
        return view('receipts.payment', compact('payment'));
    })->name('payment.receipt');

    Route::get('bills/{bill}/invoice', function (\App\Models\Bill $bill) {
        return view('receipts.invoice', compact('bill'));
    })->name('bill.invoice');
});
