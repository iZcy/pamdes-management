<?php
// routes/web.php - Added bill receipt routes

use Illuminate\Support\Facades\Route;
use App\Models\Customer;
use App\Models\User;
use App\Models\Bill;
use App\Http\Controllers\TripayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

// Apply village context middleware to all routes
Route::middleware(['village.context'])->group(function () {
    Route::get('/', function () {
        if (!config('pamdes.is_super_admin_domain')) {
            return redirect()->route('portal.index');
        }

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
                    'customer_id' => $customer->customer_id,
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
                ->with('village')
                ->firstOrFail();

            // Get unpaid bills
            $bills = $customer->bills()
                ->whereIn('status', ['unpaid', 'overdue', 'pending'])
                ->with(['waterUsage.billingPeriod'])
                ->orderBy('due_date', 'asc')
                ->get();

            // Get paid bills (last 10 for history)
            $paidBills = $customer->bills()
                ->paid()
                ->with(['waterUsage.billingPeriod', 'latestPayment'])
                ->orderBy('payment_date', 'desc')
                ->limit(10)
                ->get();

            // Get paid bundle payments (last 10 for history)
            $paidBundles = \App\Models\BundlePayment::where('customer_id', $customer->customer_id)
                ->where('status', 'paid')
                ->with(['bills.waterUsage.billingPeriod'])
                ->orderBy('paid_at', 'desc')
                ->limit(10)
                ->get();

            return view('customer-portal.bills', compact('customer', 'bills', 'paidBills', 'paidBundles'));
        })->name('bills');
    });

    // Bundle Payment Routes
    Route::prefix('bundle-payment')->name('bundle.payment.')->group(function () {
        // Show bundle payment form (email selection) 
        Route::post('/form/{customer_code}', [App\Http\Controllers\BundlePaymentController::class, 'showPaymentForm'])
            ->name('form');
        
        // Create bundle payment and process
        Route::post('/create/{customer_code}', [App\Http\Controllers\BundlePaymentController::class, 'create'])
            ->name('create');
        
        // Process bundle payment directly (create bundle + process payment)
        Route::post('/process-direct/{customer_code}', [App\Http\Controllers\BundlePaymentController::class, 'processDirectly'])
            ->name('process.direct');
        
        // Existing bundle payment routes (for continuation)
        Route::get('/payment/{customer_code}/{bundle_reference}', [App\Http\Controllers\BundlePaymentController::class, 'showForm'])
            ->name('payment.form');
        
        Route::post('/process/{customer_code}/{bundle_reference}', [App\Http\Controllers\BundlePaymentController::class, 'processPayment'])
            ->name('process');
        
        Route::get('/status/{customer_code}/{bundle_reference}', [App\Http\Controllers\BundlePaymentController::class, 'checkStatus'])
            ->name('status');
    });

    // Public Bill Receipt Routes (no authentication required)
    Route::prefix('receipt')->name('receipt.')->group(function () {
        // Bill receipt - accessible by anyone with the right bill ID and customer code
        Route::get('/bill/{bill}/{customer_code}', function (Bill $bill, $customerCode) {
            // Verify the bill belongs to the customer
            if ($bill->waterUsage->customer->customer_code !== $customerCode) {
                abort(404, 'Bill not found');
            }

            // Verify the bill is for the current village context
            $villageId = config('pamdes.current_village_id');
            if ($villageId && $bill->waterUsage->customer->village_id !== $villageId) {
                abort(404, 'Bill not found');
            }

            // Load relationships needed for the receipt
            $bill->load([
                'waterUsage.customer.village',
                'waterUsage.billingPeriod',
                'latestPayment.collector'
            ]);

            return view('receipts.bill', compact('bill'));
        })->name('bill');

        // Bundle receipt - accessible by anyone with the right bundle reference and customer code
        Route::get('/bundle/{bundle_reference}/{customer_code}', function ($bundleReference, $customerCode) {
            // Find the customer
            $customer = Customer::where('customer_code', $customerCode)->firstOrFail();
            
            // Find the bundle payment
            $bundlePayment = \App\Models\BundlePayment::where('bundle_reference', $bundleReference)
                ->where('customer_id', $customer->customer_id)
                ->where('status', 'paid')
                ->with([
                    'customer.village',
                    'bills.waterUsage.billingPeriod'
                ])
                ->firstOrFail();

            // Verify the bundle is for the current village context
            $villageId = config('pamdes.current_village_id');
            if ($villageId && $bundlePayment->customer->village_id !== $villageId) {
                abort(404, 'Bundle payment not found');
            }

            return view('receipts.bundle', compact('bundlePayment'));
        })->name('bundle');

        // Multiple bills invoice - generate invoice for selected bills
        Route::post('/invoice/{customer_code}', function ($customerCode, Request $request) {
            $validator = Validator::make($request->all(), [
                'bill_ids' => 'required|array|min:1',
                'bill_ids.*' => 'required|exists:bills,bill_id',
            ]);

            if ($validator->fails()) {
                abort(400, 'Invalid bill selection');
            }

            // Find the customer
            $customer = Customer::where('customer_code', $customerCode)->firstOrFail();
            
            // Get bills and validate they belong to the customer
            $bills = Bill::whereIn('bill_id', $request->bill_ids)
                ->whereHas('waterUsage', function ($query) use ($customer) {
                    $query->where('customer_id', $customer->customer_id);
                })
                ->with([
                    'waterUsage.customer.village',
                    'waterUsage.billingPeriod'
                ])
                ->get();

            if ($bills->isEmpty()) {
                abort(404, 'No valid bills found');
            }

            if ($bills->count() !== count($request->bill_ids)) {
                abort(400, 'Some bills are invalid or not found');
            }

            // Verify the bills are for the current village context
            $villageId = config('pamdes.current_village_id');
            if ($villageId && $bills->first()->waterUsage->customer->village_id !== $villageId) {
                abort(404, 'Bills not found');
            }

            return view('receipts.multiple-bills', compact('bills', 'customer'));
        })->name('invoice.multiple');
    });

    // Tripay Payment Routes - Village-specific
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

            // Continue payment
            Route::get('/payment/continue', [TripayController::class, 'continuePayment'])
                ->name('tripay.continue');
        });
    });

    Route::fallback(function () {
        if (!config('pamdes.is_super_admin_domain')) {
            return redirect()->route('portal.index');
        }

        return redirect(filament()->getLoginUrl());
    });
});

// Public Tripay callback routes (no authentication or village context required)
Route::prefix('tripay')->group(function () {
    // Webhook callback from Tripay
    Route::post('/callback', [TripayController::class, 'handleCallback'])
        ->name('tripay.callback');

    // Bundle payment callback
    Route::post('/callback/bundle/{village}', [App\Http\Controllers\BundlePaymentController::class, 'handleCallback'])
        ->name('tripay.callback.bundle');

    // Return URL after payment
    Route::get('/return', [TripayController::class, 'handleReturn'])
        ->name('tripay.return');
});

// Operator meter reading routes (separate from auth middleware group)
Route::middleware([App\Http\Middleware\RequireOperator::class])->prefix('admin/meter')->group(function () {
    Route::get('/read', [App\Http\Controllers\MeterReadingController::class, 'index'])
        ->name('meter.read');
    Route::post('/read/submit', [App\Http\Controllers\MeterReadingController::class, 'submit'])
        ->name('meter.read.submit');
    Route::post('/verify-customer', [App\Http\Controllers\MeterReadingController::class, 'verifyCustomer'])
        ->name('meter.verify.customer');
    Route::post('/ocr', [App\Http\Controllers\MeterReadingController::class, 'processOCR'])
        ->name('meter.ocr');
});

// Admin routes (protected by auth)
Route::middleware(['auth'])->prefix('admin')->group(function () {
    // Payment receipt (existing)
    Route::get('payments/{payment}/receipt', function (\App\Models\Payment $payment) {
        // Load bill
        $bill = $payment->bill()->with([
            'waterUsage.customer.village',
            'waterUsage.billingPeriod',
            'latestPayment.collector'
        ])->firstOrFail();

        return view('receipts.bill', compact('bill'));
    })->name('payment.receipt');

    // Bill receipt/invoice (admin access)
    Route::get('bills/{bill}/receipt', function (Bill $bill) {
        // Load relationships
        $bill->load([
            'waterUsage.customer.village',
            'waterUsage.billingPeriod',
            'latestPayment.collector'
        ]);

        return view('receipts.bill', compact('bill'));
    })->name('bill.receipt');

    // Legacy route for backward compatibility
    Route::get('bills/{bill}/invoice', function (Bill $bill) {
        return redirect()->route('bill.receipt', $bill);
    })->name('bill.invoice');
});

// Export download routes (protected by auth)
Route::middleware(['auth'])->prefix('admin')->group(function () {
    // Export download route
    Route::get('/exports/{filename}', function ($filename) {
        // Validate filename to prevent directory traversal
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
            abort(404, 'File not found');
        }

        $path = "exports/{$filename}";

        // Check if file exists in public storage
        if (!Storage::disk('public')->exists($path)) {
            abort(404, 'Export file not found or has expired');
        }

        // Get file contents
        $fileContent = Storage::disk('public')->get($path);
        $mimeType = Storage::disk('public')->mimeType($path);

        // Determine proper content type
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        switch ($extension) {
            case 'pdf':
                $contentType = 'application/pdf';
                break;
            case 'csv':
                $contentType = 'text/csv';
                break;
            default:
                $contentType = $mimeType ?: 'application/octet-stream';
        }

        // Log the download
        Log::info('Export file downloaded', [
            'filename' => $filename,
            'user_id' => Auth::id(),
            'ip' => request()->ip(),
        ]);

        // Return file download response
        return response($fileContent)
            ->header('Content-Type', $contentType)
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    })->name('export.download');

    // Export cleanup route (optional - to clean old exports)
    Route::delete('/exports/{filename}', function ($filename) {
        // Validate filename
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
            abort(404, 'File not found');
        }

        $path = "exports/{$filename}";

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            return response()->json(['message' => 'File deleted successfully']);
        }

        return response()->json(['message' => 'File not found'], 404);
    })->name('export.delete');
});

// Cleanup old exports command (you can also add this to a scheduled task)
Route::middleware(['auth'])->get('/admin/exports/cleanup', function () {
    $user = User::find(Auth::id());
    if (!$user || !$user->isSuperAdmin()) {
        abort(403, 'Unauthorized');
    }

    $deleted = 0;
    $files = Storage::disk('public')->files('exports');
    $oldDate = now()->subDays(7); // Delete files older than 7 days

    foreach ($files as $file) {
        $lastModified = Storage::disk('public')->lastModified($file);
        if ($lastModified < $oldDate->timestamp) {
            Storage::disk('public')->delete($file);
            $deleted++;
        }
    }

    return response()->json([
        'message' => "Cleaned up {$deleted} old export files",
        'deleted_count' => $deleted
    ]);
})->name('export.cleanup');

// Health check and debug routes
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'village' => config('pamdes.current_village.name', 'Unknown'),
        'host' => request()->getHost(),
        'system' => 'PAMDes Management System',
    ]);
});
