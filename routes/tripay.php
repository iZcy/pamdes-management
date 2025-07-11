<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TripayController;

/*
|--------------------------------------------------------------------------
| Tripay Payment Routes for Pamdes
|--------------------------------------------------------------------------
|
| Routes for handling Tripay QRIS payment integration with villages and bills
|
*/

// Public callback routes (no authentication required)
Route::prefix('tripay')->group(function () {
    // Webhook callback from Tripay
    Route::post('/callback', [TripayController::class, 'handleCallback'])
        ->name('tripay.callback');

    // Return URL after payment
    Route::get('/return', [TripayController::class, 'handleReturn'])
        ->name('tripay.return');
});

// Village-specific payment routes
Route::prefix('{village}')->group(function () {
    Route::prefix('bill/{bill}')->group(function () {
        // Show payment form
        Route::get('/payment', [TripayController::class, 'showPaymentForm'])
            ->name('tripay.form');

        // Create payment
        Route::post('/payment/create', [TripayController::class, 'createPayment'])
            ->name('tripay.create');

        // Check payment status (AJAX)
        Route::get('/payment/status', [TripayController::class, 'checkStatus'])
            ->name('tripay.status');
    });
});

// You can add these routes to your existing web.php file by including:
