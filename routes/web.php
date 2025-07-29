<?php
// routes/web.php - Added bill receipt routes

use Illuminate\Support\Facades\Route;
use App\Models\Customer;
use App\Models\User;
use App\Models\Bill;
use App\Http\Controllers\TripayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

// Public Tripay callback routes (no CSRF protection, no village context required)
Route::withoutMiddleware(['web'])->prefix('tripay')->group(function () {
    // Webhook callback from Tripay - handles both single and bundle payments
    Route::post('/callback', [App\Http\Controllers\TripayCallbackController::class, 'handle'])
        ->name('tripay.callback');

    // Bundle payment callback (legacy route - keeping for compatibility)
    Route::post('/callback/bundle/{village}', [App\Http\Controllers\BundlePaymentController::class, 'handleCallback'])
        ->name('tripay.callback.bundle');
});

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


            // Get pending transactions for this customer (bills with pending payments) - after status update
            $pendingTransactions = \App\Models\Bill::where('customer_id', $customer->customer_id)
                ->where('status', 'unpaid')
                ->whereHas('payments', function ($q) {
                    $q->where('status', 'pending');
                })
                ->get();

            // Log debugging info before cleanup
            Log::info('Pending transactions found (before cleanup)', [
                'customer_id' => $customer->customer_id,
                'pending_transactions_count' => $pendingTransactions->count(),
                'transaction_refs' => $pendingTransactions->map(fn($bill) => $bill->getPendingPayment()?->transaction_ref)->filter()->unique()->values()->toArray()
            ]);

            // Clean up expired payments (older than 7 days)
            $expiredPayments = \App\Models\Payment::where('status', 'pending')
                ->where('updated_at', '<', now()->subDays(7))
                ->whereHas('bills', function ($q) use ($customer) {
                    $q->where('customer_id', $customer->customer_id);
                });

            $expiredCount = $expiredPayments->count();
            $expiredPayments->update(['status' => 'expired']);

            Log::info('Payment cleanup performed', [
                'customer_id' => $customer->customer_id,
                'expired_payments_cleared' => $expiredCount,
            ]);

            // Re-fetch pending transactions after cleanup
            $pendingTransactionsAfterCleanup = \App\Models\Bill::where('customer_id', $customer->customer_id)
                ->where('status', 'unpaid')
                ->whereHas('payments', function ($q) {
                    $q->where('status', 'pending');
                })
                ->get();

            Log::info('Valid pending transactions after cleanup', [
                'customer_id' => $customer->customer_id,
                'valid_pending_transactions_count' => $pendingTransactionsAfterCleanup->count(),
                'transaction_refs' => $pendingTransactionsAfterCleanup->map(fn($bill) => $bill->getPendingPayment()?->transaction_ref)->filter()->unique()->values()->toArray()
            ]);

            // Get unpaid bills (exclude bills already in pending transactions)
            $billsQuery = $customer->bills()
                ->where('status', 'unpaid');

            // Exclude bills that are already in pending transactions
            $billsQuery->whereDoesntHave('payments', function ($q) {
                $q->where('status', 'pending');
            });

            $bills = $billsQuery->with(['waterUsage.billingPeriod'])
                ->orderBy('due_date', 'asc')
                ->get();

            Log::info('Final bills for selection', [
                'bills_count' => $bills->count(),
                'bills' => $bills->map(function ($bill) {
                    return [
                        'bill_id' => $bill->bill_id,
                        'status' => $bill->status,
                        'period' => $bill->waterUsage->billingPeriod->period_name,
                        'amount' => $bill->total_amount
                    ];
                })
            ]);

            // Group pending transactions by payment to identify bundles vs individual bills
            $pendingBills = collect();
            $pendingBundles = collect();

            $paymentGroups = $pendingTransactionsAfterCleanup->groupBy(function ($bill) {
                return $bill->getPendingPayment()?->payment_id;
            })->filter();

            foreach ($paymentGroups as $paymentId => $billsInPayment) {
                $payment = $billsInPayment->first()->getPendingPayment();
                if ($billsInPayment->count() > 1) {
                    // This is a bundle (multiple bills in same payment)
                    // Create a bundle object for the template
                    $bundleData = (object) [
                        'payment_id' => $paymentId,
                        'transaction_ref' => $payment?->transaction_ref,
                        'bills' => $billsInPayment,
                        'bill_count' => $billsInPayment->count(),
                        'total_amount' => $billsInPayment->sum('total_amount'),
                        'first_bill' => $billsInPayment->first(),
                        'customer' => $billsInPayment->first()->waterUsage->customer ?? $customer
                    ];
                    $pendingBundles->push($bundleData);
                } else {
                    // This is an individual bill
                    $pendingBills = $pendingBills->concat($billsInPayment);
                }
            }

            Log::info('Pending transactions organized', [
                'customer_id' => $customer->customer_id,
                'individual_pending_bills' => $pendingBills->count(),
                'pending_bundle_bills' => $pendingBundles->count(),
                'payment_groups' => count($paymentGroups)
            ]);

            // Get paid bills - separate individual and bundle payments
            $allPaidBills = $customer->bills()
                ->paid()
                ->with(['waterUsage.billingPeriod', 'payments'])
                ->orderBy('payment_date', 'desc')
                ->get();

            // Separate individual paid bills from bundle payments
            $paidBills = collect();
            $paidBundles = collect();

            $paidPaymentGroups = $allPaidBills->groupBy(function ($bill) {
                return $bill->payments->where('status', 'completed')->first()?->payment_id;
            })->filter();

            foreach ($paidPaymentGroups as $paymentId => $billsInPayment) {
                $payment = $billsInPayment->first()->payments->where('status', 'completed')->first();
                if ($billsInPayment->count() > 1) {
                    // This was a bundle payment - create bundle object
                    $bundleData = (object) [
                        'payment_id' => $paymentId,
                        'transaction_ref' => $payment?->transaction_ref,
                        'bills' => $billsInPayment,
                        'bill_count' => $billsInPayment->count(),
                        'total_amount' => $billsInPayment->sum('total_amount'),
                        'first_bill' => $billsInPayment->first(),
                        'payment_date' => $billsInPayment->first()->payment_date,
                        'customer' => $billsInPayment->first()->waterUsage->customer ?? $customer
                    ];
                    $paidBundles->push($bundleData);
                } else {
                    // This was an individual payment
                    $paidBills = $paidBills->concat($billsInPayment);
                }
            }

            // Limit to 10 most recent
            $paidBills = $paidBills->take(10);
            $paidBundles = $paidBundles->take(10);

            // Get total count of all unpaid bills (including pending ones) for the "Total Tagihan" display
            $totalUnpaidBills = $customer->bills()
                ->where('status', 'unpaid')
                ->count();

            // Get village model for logo usage
            $village = config('pamdes.current_village');
            $villageModel = \App\Models\Village::find($village['id']);
            
            return view('customer-portal.bills', compact('customer', 'bills', 'pendingBills', 'pendingBundles', 'paidBills', 'paidBundles', 'totalUnpaidBills', 'village', 'villageModel'));
        })->name('bills');
    });

    // Bundle Payment Routes - Only bulk operations allowed
    Route::prefix('bundle-payment')->name('bundle.payment.')->group(function () {
        // Show bundle payment form (email selection) - POST route from bills page (requires bill selection)
        Route::post('/form/{customer_code}', [App\Http\Controllers\BundlePaymentController::class, 'showPaymentForm'])
            ->name('form');

        // Create bundle payment and process (accepts single or multiple bills)
        Route::post('/create/{customer_code}', [App\Http\Controllers\BundlePaymentController::class, 'create'])
            ->name('create');

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
                'payments'
            ]);

            return view('receipts.bill', compact('bill'));
        })->name('bill');

        // Bundle receipt - accessible by anyone with the right transaction reference and customer code
        Route::get('/bundle/{bundle_reference}/{customer_code}', function ($bundleReference, $customerCode) {
            // Find the customer
            $customer = Customer::where('customer_code', $customerCode)->firstOrFail();

            // Find bills with this transaction_ref (bundle payment)
            $bundleBills = \App\Models\Bill::where('customer_id', $customer->customer_id)
                ->where('status', 'paid')
                ->whereHas('payments', function ($q) use ($bundleReference) {
                    $q->where('transaction_ref', $bundleReference)->where('status', 'completed');
                })
                ->with([
                    'waterUsage.customer.village',
                    'waterUsage.billingPeriod',
                    'payments'
                ])
                ->get();

            if ($bundleBills->isEmpty() || $bundleBills->count() < 2) {
                abort(404, 'Bundle payment not found');
            }

            // Verify the bundle is for the current village context
            $villageId = config('pamdes.current_village_id');
            if ($villageId && $bundleBills->first()->waterUsage->customer->village_id !== $villageId) {
                abort(404, 'Bundle payment not found');
            }

            // Create bundle object for template compatibility
            $bundlePayment = (object) [
                'transaction_ref' => $bundleReference,
                'bills' => $bundleBills,
                'bill_count' => $bundleBills->count(),
                'total_amount' => $bundleBills->sum('total_amount'),
                'customer' => $bundleBills->first()->waterUsage->customer,
                'payment_date' => $bundleBills->first()->payment_date,
                'village' => $bundleBills->first()->waterUsage->customer->village
            ];

            return view('receipts.bundle', compact('bundlePayment', 'bundleBills'));
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

    // Tripay Payment Routes - Use village context from subdomain
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
        
        // Check payment status by reference (AJAX)
        Route::get('/payment/status/{reference}', [TripayController::class, 'checkStatusByReference'])
            ->name('tripay.status.reference');

        // Continue payment
        Route::get('/payment/continue', [TripayController::class, 'continuePayment'])
            ->name('tripay.continue');
    });

    // Bundle payment form route
    Route::get('/bundle/{paymentId}', [TripayController::class, 'showBundlePaymentForm'])
        ->name('tripay.bundle.form');

    // Return URL after payment (needs village context for proper redirect)
    Route::get('/tripay/return', [TripayController::class, 'handleReturn'])
        ->name('tripay.return');


    Route::fallback(function () {
        if (!config('pamdes.is_super_admin_domain')) {
            return redirect()->route('portal.index');
        }

        return redirect(filament()->getLoginUrl());
    });
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
        // Load bills with relationships - get first bill for single payment receipt
        $bill = $payment->bills()->with([
            'waterUsage.customer.village',
            'waterUsage.billingPeriod',
            'payments.collector'
        ])->firstOrFail();

        return view('receipts.bill', compact('bill'));
    })->name('payment.receipt');

    // Bill receipt/invoice (admin access)
    Route::get('bills/{bill}/receipt', function (Bill $bill) {
        // Load relationships
        $bill->load([
            'waterUsage.customer.village',
            'waterUsage.billingPeriod',
            'payments'
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
