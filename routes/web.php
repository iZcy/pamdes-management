<?php
// routes/web.php - Working version with village context

use Illuminate\Support\Facades\Route;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Web Routes - PAMDes System with Village Context
|--------------------------------------------------------------------------
*/

// Apply village context middleware to all routes
Route::middleware(['village.context'])->group(function () {

    // Homepage
    Route::get('/', function () {
        $village = config('pamdes.current_village');

        if ($village) {
            // Show village-specific welcome page
            return view('welcome', compact('village'));
        }

        // Default welcome page
        return view('welcome');
    })->name('home');

    // Customer Portal - Village-specific bill checking
    Route::prefix('portal')->name('portal.')->group(function () {
        Route::get('/', function () {
            $village = config('pamdes.current_village');
            return view('customer-portal.index', compact('village'));
        })->name('index');

        Route::post('/lookup', function () {
            request()->validate([
                'customer_code' => 'required|string|max:20'
            ]);

            $villageId = config('pamdes.current_village_id');

            if (!$villageId) {
                return back()->withErrors(['customer_code' => 'Village context not found.']);
            }

            $customer = Customer::where('customer_code', request('customer_code'))
                ->where('village_id', $villageId)
                ->first();

            if (!$customer) {
                return back()->withErrors(['customer_code' => 'Kode pelanggan tidak ditemukan.']);
            }

            return redirect()->route('portal.bills', $customer->customer_code);
        })->name('lookup');

        Route::get('/bills/{customer_code}', function ($customerCode) {
            $villageId = config('pamdes.current_village_id');

            if (!$villageId) {
                abort(404, 'Village not found');
            }

            $customer = Customer::where('customer_code', $customerCode)
                ->where('village_id', $villageId)
                ->firstOrFail();

            $bills = $customer->bills()->unpaid()->with(['waterUsage.billingPeriod'])->get();

            return view('customer-portal.bills', compact('customer', 'bills'));
        })->name('bills');
    });
});

// Admin routes (protected by auth)
Route::middleware(['auth'])->prefix('admin')->group(function () {

    // Receipt printing routes
    Route::get('payments/{payment}/receipt', function (\App\Models\Payment $payment) {
        return view('receipts.payment', compact('payment'));
    })->name('payment.receipt');

    Route::get('bills/{bill}/invoice', function (\App\Models\Bill $bill) {
        return view('receipts.invoice', compact('bill'));
    })->name('bill.invoice');
});

// Health check (no middleware needed)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'village' => config('pamdes.current_village.name', 'Unknown'),
        'host' => request()->getHost(),
    ]);
});

Route::get('/debug-auth-flow', function () {
    return response()->json([
        'host' => request()->getHost(),
        'session_id' => session()->getId(),
        'session_domain' => config('session.domain'),
        'auth_check' => Auth::check(),
        'auth_user' => User::find(Auth::id())?->only(['id', 'email', 'role']),
        'can_access_panel' => Auth::user() ? User::find(Auth::id())->canAccessPanel(new \Filament\Panel()) : false,
    ]);
});
