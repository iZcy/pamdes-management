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

Route::get('/debug-context', function () {
    return response()->json([
        'host' => request()->getHost(),
        'current_village' => config('pamdes.current_village'),
        'current_village_id' => config('pamdes.current_village_id'),
        'is_super_admin_domain' => config('pamdes.is_super_admin_domain'),
        'tenant_context' => config('pamdes.tenant'),
        'env_pattern' => env('PAMDES_VILLAGE_DOMAIN_PATTERN', 'village'),
        'config_pattern' => config('pamdes.domains.village_pattern'),
    ]);
});

Route::get('/debug-user-access', function () {
    $currentVillageId = config('pamdes.current_village_id');

    // Find the Bayan admin user
    $user = \App\Models\User::where('email', 'admin@bayan.dev-pamdes.id')->first();

    if (!$user) {
        return response()->json(['error' => 'User not found']);
    }

    // Check user's village relationships
    $userVillages = $user->villages()->get();
    $primaryVillage = $user->primaryVillage();

    // Check access methods
    $hasAccessToCurrentVillage = $user->hasAccessToVillage($currentVillageId);
    $accessibleVillages = $user->getAccessibleVillages();

    return response()->json([
        'current_village_id' => $currentVillageId,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
        ],
        'user_villages' => $userVillages->map(function ($village) {
            return [
                'id' => $village->id,
                'name' => $village->name,
                'slug' => $village->slug,
                'is_primary' => $village->pivot->is_primary ?? false,
            ];
        }),
        'primary_village' => $primaryVillage ? [
            'id' => $primaryVillage->id,
            'name' => $primaryVillage->name,
            'slug' => $primaryVillage->slug,
        ] : null,
        'has_access_to_current_village' => $hasAccessToCurrentVillage,
        'accessible_villages' => $accessibleVillages->map(function ($village) {
            return [
                'id' => $village->id,
                'name' => $village->name,
                'slug' => $village->slug,
            ];
        }),
        'user_village_pivot_table' => DB::table('user_villages')->where('user_id', $user->id)->get(),
    ]);
});

Route::middleware(['auth'])->get('/debug-auth', function () {
    $user = User::find(Auth::id());
    $currentVillageId = config('pamdes.current_village_id');
    $tenantContext = config('pamdes.tenant');

    if (!$user) {
        return response()->json(['error' => 'Not authenticated']);
    }

    // Get the full user model to access methods
    $fullUser = \App\Models\User::find($user->id);

    // Test the canAccessPanel method
    $panel = new \Filament\Panel(); // Create a dummy panel for testing

    return response()->json([
        'authenticated_user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
        ],
        'current_village_id' => $currentVillageId,
        'tenant_context' => $tenantContext,
        'user_methods' => [
            'isSuperAdmin' => $fullUser->isSuperAdmin(),
            'isVillageAdmin' => $fullUser->isVillageAdmin(),
            'hasAccessToVillage' => $fullUser->hasAccessToVillage($currentVillageId),
            'getCurrentVillageContext' => $fullUser->getCurrentVillageContext(),
        ],
        'config_values' => [
            'is_super_admin_domain' => config('pamdes.is_super_admin_domain'),
            'current_village' => config('pamdes.current_village.name'),
        ],
        // 'can_access_panel' => $fullUser->canAccessPanel($panel), // This might cause issues
    ]);
});
