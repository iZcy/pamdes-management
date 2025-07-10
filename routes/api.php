<?php
// routes/api.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\ReportController;

/*
|--------------------------------------------------------------------------
| API Routes
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
            'service' => 'PAMDes Management',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
        ]);
    });

    // Village integration endpoints
    Route::prefix('villages/{village_id}')->group(function () {

        // Customer endpoints
        Route::get('/customers', [CustomerController::class, 'index']);
        Route::get('/customers/summary', [CustomerController::class, 'summary']);

        // Billing endpoints
        Route::get('/bills', [BillController::class, 'index']);
        Route::get('/bills/summary', [BillController::class, 'summary']);
        Route::get('/bills/overdue', [BillController::class, 'overdue']);

        // Reports
        Route::get('/reports/monthly', [ReportController::class, 'monthlyReport']);
        Route::get('/reports/village', [ReportController::class, 'villageReport']);
    });
});

// Protected API routes (require village system authentication)
Route::middleware(['auth:sanctum', 'validate.village.token'])->group(function () {

    // Administrative endpoints
    Route::prefix('admin')->group(function () {

        // Customer management
        Route::apiResource('customers', CustomerController::class);

        // Billing management
        Route::apiResource('bills', BillController::class);
        Route::post('bills/{bill}/pay', [BillController::class, 'markAsPaid']);
        Route::post('bills/bulk/generate', [BillController::class, 'bulkGenerate']);

        // Reports
        Route::get('reports/dashboard', [ReportController::class, 'dashboard']);
        Route::get('reports/collections', [ReportController::class, 'collections']);
        Route::get('reports/export/{type}', [ReportController::class, 'export']);
    });
});

// Webhook endpoints for village system integration
Route::prefix('webhooks')->middleware(['throttle:30,1'])->group(function () {

    Route::post('village/updated', function (Request $request) {
        // Handle village data updates from main system
        $villageData = $request->validate([
            'village_id' => 'required|string',
            'action' => 'required|in:updated,deleted,activated,deactivated',
            'data' => 'sometimes|array',
        ]);

        // Clear relevant caches
        app(\App\Services\VillageApiService::class)->clearVillageCache();

        return response()->json(['status' => 'processed']);
    });

    Route::post('user/updated', function (Request $request) {
        // Handle user updates from main system for SSO
        $userData = $request->validate([
            'user_id' => 'required|string',
            'action' => 'required|in:updated,deleted,role_changed',
            'data' => 'sometimes|array',
        ]);

        // Process user updates if needed
        return response()->json(['status' => 'processed']);
    });
});
