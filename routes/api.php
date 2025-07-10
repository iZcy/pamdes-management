<?php
// routes/api.php - Updated for independent system

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\ReportController;

/*
|--------------------------------------------------------------------------
| API Routes - Independent PAMDes System
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
            'mode' => 'independent',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
        ]);
    });

    // Village endpoints - now works with local data only
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

    // Local village management
    Route::prefix('villages')->group(function () {
        Route::get('/', function () {
            $villages = \App\Models\Village::active()->get();
            return response()->json([
                'success' => true,
                'data' => $villages->map(function ($village) {
                    return [
                        'id' => $village->id,
                        'name' => $village->name,
                        'slug' => $village->slug,
                        'is_active' => $village->is_active,
                    ];
                }),
            ]);
        });

        Route::get('/{village_id}', function ($villageId) {
            $village = \App\Models\Village::find($villageId);
            if (!$village) {
                return response()->json(['error' => 'Village not found'], 404);
            }

            return response()->json([
                'success' => true,
                'data' => app(\App\Services\VillageService::class)->getVillageById($villageId),
            ]);
        });
    });
});

// Protected API routes (require authentication)
Route::middleware(['auth:sanctum'])->group(function () {

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

        // Village management (local)
        Route::prefix('villages')->group(function () {
            Route::get('/', function () {
                $villages = \App\Models\Village::with(['customers', 'billingPeriods'])->get();
                return response()->json([
                    'success' => true,
                    'data' => $villages,
                ]);
            });

            Route::post('/', function (Request $request) {
                $request->validate([
                    'name' => 'required|string|max:255',
                    'slug' => 'required|string|max:255|unique:villages,slug',
                    'description' => 'nullable|string',
                    'phone_number' => 'nullable|string',
                    'email' => 'nullable|email',
                    'address' => 'nullable|string',
                ]);

                $villageData = array_merge($request->all(), [
                    'id' => \Illuminate\Support\Str::uuid()->toString(),
                    'is_active' => true,
                    'established_at' => now(),
                ]);

                $village = app(\App\Services\VillageService::class)->createOrUpdateVillage($villageData);

                return response()->json([
                    'success' => true,
                    'message' => 'Village created successfully',
                    'data' => $village,
                ], 201);
            });

            Route::put('/{village_id}', function (Request $request, $villageId) {
                $village = \App\Models\Village::find($villageId);
                if (!$village) {
                    return response()->json(['error' => 'Village not found'], 404);
                }

                $request->validate([
                    'name' => 'sometimes|string|max:255',
                    'slug' => 'sometimes|string|max:255|unique:villages,slug,' . $villageId,
                    'description' => 'nullable|string',
                    'phone_number' => 'nullable|string',
                    'email' => 'nullable|email',
                    'address' => 'nullable|string',
                    'is_active' => 'sometimes|boolean',
                ]);

                $villageData = array_merge(['id' => $villageId], $request->all());
                $updatedVillage = app(\App\Services\VillageService::class)->createOrUpdateVillage($villageData);

                return response()->json([
                    'success' => true,
                    'message' => 'Village updated successfully',
                    'data' => $updatedVillage,
                ]);
            });

            Route::patch('/{village_id}/status', function (Request $request, $villageId) {
                $request->validate([
                    'is_active' => 'required|boolean',
                ]);

                $success = app(\App\Services\VillageService::class)->setVillageStatus($villageId, $request->is_active);

                if (!$success) {
                    return response()->json(['error' => 'Village not found'], 404);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Village status updated successfully',
                ]);
            });
        });
    });
});
