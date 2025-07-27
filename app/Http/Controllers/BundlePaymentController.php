<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\TripayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BundlePaymentController extends Controller
{

    /**
     * Show bundle payment form (email selection) for selected bills
     */
    public function showPaymentForm(Request $request, $customerCode)
    {
        try {
            $validator = Validator::make($request->all(), [
                'bill_ids' => 'required|array|min:1', // Accept single or multiple bills
                'bill_ids.*' => 'exists:bills,bill_id',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', 'Data tagihan tidak valid');
            }

            $customer = Customer::where('customer_code', $customerCode)->firstOrFail();
            
            // Perform the same cleanup as the portal route
            $this->performBillCleanup($customer);

            // Note: Pending payments are handled in the frontend display
            // Users can choose to continue existing payments or create new ones
            // We don't automatically redirect to pending payments here
            
            // Get all bill categories for comprehensive view
            $billCategories = $this->getAllBillCategories($customer);

            // Verify bills belong to customer and are unpaid without pending payments
            $bills = Bill::whereIn('bill_id', $request->bill_ids)
                ->where('customer_id', $customer->customer_id)
                ->whereIn('status', ['unpaid', 'overdue'])
                ->whereDoesntHave('payments', function($q) {
                    $q->where('status', 'pending');
                }) // Ensure no pending payments
                ->with(['waterUsage.billingPeriod'])
                ->get();

            if ($bills->count() !== count($request->bill_ids)) {
                return redirect()->back()->with('error', 'Beberapa tagihan tidak valid, sudah dibayar, atau sedang dalam proses pembayaran');
            }

            $totalAmount = $bills->sum('total_amount');

            return view('tripay.payment-form', [
                'customer' => $customer,
                'bills' => $bills,
                'total_amount' => $totalAmount,
                'action_url' => route('bundle.payment.create', ['customer_code' => $customerCode]),
                'village' => $customer->village,
                
                // Additional bill categories for comprehensive view
                'availableUnpaidBills' => $billCategories['availableUnpaidBills'],
                'pendingIndividualBills' => $billCategories['pendingIndividualBills'],
                'pendingBundles' => $billCategories['pendingBundles'],
                'recentPaidBills' => $billCategories['recentPaidBills'],
                'recentPaidBundles' => $billCategories['recentPaidBundles'],
                
                // Context information
                'showingBundleForm' => true,
                'selectedBillIds' => $request->bill_ids,
                
                // Payment status information (for new bundle creation - no pending payment)
                'hasPendingPayment' => false,
                'pendingPayment' => null
            ]);
        } catch (\Exception $e) {
            Log::error('Bundle payment form error', [
                'customer_code' => $customerCode,
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return redirect()->back()->with('error', 'Terjadi kesalahan saat memproses permintaan');
        }
    }

    /**
     * Create bundle payment using bulk logic - matches frontend exactly
     */
    public function create(Request $request, $customerCode)
    {
        try {
            $validator = Validator::make($request->all(), [
                'bill_ids' => 'required|array|min:1', // Accept single or multiple bills
                'bill_ids.*' => 'exists:bills,bill_id',
                'customer_name' => 'required|string|max:255',
                'customer_email' => 'required|email',
                'customer_phone' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                if (request()->wantsJson() || request()->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $validator->errors()->first()
                    ], 422);
                }
                return back()->withErrors($validator)->withInput();
            }

            $customer = Customer::where('customer_code', $customerCode)->firstOrFail();

            // Validate bills for bundle payment - matches frontend logic exactly
            $bills = $this->validateBillsForBundle($customer, $request->bill_ids);
            
            if ($bills->isEmpty()) {
                if (request()->wantsJson() || request()->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tidak ada tagihan valid yang dapat dibundel'
                    ], 422);
                }
                return back()->with('error', 'Tidak ada tagihan valid yang dapat dibundel');
            }

            if ($bills->count() !== count($request->bill_ids)) {
                if (request()->wantsJson() || request()->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Beberapa tagihan tidak valid atau sudah dalam proses pembayaran'
                    ], 422);
                }
                return back()->with('error', 'Beberapa tagihan tidak valid atau sudah dalam proses pembayaran');
            }

            // Initialize Tripay service
            $tripayService = new TripayService($customer->village);

            // Generate transaction reference
            $transactionRef = 'TXN-' . strtoupper($customer->village->slug) . '-' . now()->format('YmdHis') . '-' . uniqid();

            // Create Tripay payment
            $customerData = [
                'name' => $request->customer_name,
                'email' => $request->customer_email,
                'phone' => $request->customer_phone,
            ];

            $totalAmount = $bills->sum('total_amount');
            $returnUrl = route('portal.bills', ['customer_code' => $customerCode]);
            
            // Create pending payment record first
            $payment = Payment::createPayment($bills->pluck('bill_id')->toArray(), [
                'payment_method' => 'qris',
                'transaction_ref' => $transactionRef,
                'collector_id' => null,
                'notes' => 'Bundle payment via Tripay'
            ]);

            $paymentResult = $tripayService->createBundlePayment($bills, $customerData, $transactionRef, $returnUrl);

            if (!$paymentResult['success']) {
                // Remove payment if Tripay creation failed
                $payment->delete();
                throw new \Exception('Tripay payment creation failed: ' . ($paymentResult['message'] ?? 'Unknown error'));
            }

            // Update payment with Tripay data and reference only
            $tripayReference = $paymentResult['tripay_reference'] ?? $paymentResult['data']['reference'] ?? $transactionRef;
            
            Log::info('Updating payment with references', [
                'tripay_reference' => $paymentResult['tripay_reference'] ?? 'not found',
                'data_reference' => $paymentResult['data']['reference'] ?? 'not found', 
                'merchant_ref' => $transactionRef,
                'final_ref' => $tripayReference
            ]);
            
            $payment->update([
                'transaction_ref' => $tripayReference,
                'tripay_data' => $paymentResult
            ]);

            Log::info('Bundle payment created successfully', [
                'customer_code' => $customerCode,
                'bill_count' => $bills->count(), // Logging only
                'total_amount' => $totalAmount,
                'transaction_ref' => $transactionRef,
                'tripay_reference' => $paymentResult['tripay_reference'] ?? null,
            ]);

            // Return JSON response for AJAX handling
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Bundle payment berhasil dibuat. Mengarahkan ke halaman pembayaran...',
                    'checkout_url' => $paymentResult['checkout_url'],
                    'transaction_ref' => $transactionRef,
                    'total_amount' => $totalAmount,
                    'bill_count' => $bills->count() // Logging only
                ]);
            }

            // For non-AJAX requests, redirect to checkout URL (fallback)
            return redirect($paymentResult['checkout_url']);
        } catch (\Exception $e) {
            Log::error('Bundle payment creation error', [
                'customer_code' => $customerCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return JSON response for AJAX handling
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal membuat pembayaran bundel: ' . $e->getMessage()
                ], 422);
            }

            return back()->with('error', 'Gagal membuat pembayaran bundel: ' . $e->getMessage());
        }
    }


    /**
     * Show existing bundle payment form
     */
    public function showForm($customerCode, $bundleReference)
    {
        try {
            $customer = Customer::where('customer_code', $customerCode)->firstOrFail();

            $bundleBill = Bill::where('bundle_reference', $bundleReference)
                ->where('customer_id', $customer->customer_id)
                ->where('status', 'pending')
                ->with(['bundledBills.waterUsage.billingPeriod'])
                ->firstOrFail();

            return redirect()->route('portal.bills', ['customer_code' => $customerCode])
                ->with('info', 'Bundle payment found: ' . $bundleReference);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Pembayaran bundel tidak ditemukan atau sudah dibayar');
        }
    }

    /**
     * Process bundle payment
     */
    public function processPayment(Request $request, $customerCode, $bundleReference)
    {
        try {
            $validator = Validator::make($request->all(), [
                'customer_name' => 'required|string|max:255',
                'customer_email' => 'required|email',
                'customer_phone' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            $customer = Customer::where('customer_code', $customerCode)->firstOrFail();

            $bundleBill = Bill::where('bundle_reference', $bundleReference)
                ->where('customer_id', $customer->customer_id)
                ->where('status', 'pending')
                ->firstOrFail();

            // If already has payment reference, continue existing payment
            if ($bundleBill->bill_ref) {
                $tripayService = new TripayService($customer->village);
                $paymentResult = $tripayService->continuePayment($bundleBill->bill_ref);

                if ($paymentResult['success']) {
                    return redirect($paymentResult['checkout_url']);
                }
            }

            // Create new payment
            $tripayService = new TripayService($customer->village);

            $customerData = [
                'name' => $request->customer_name,
                'email' => $request->customer_email,
                'phone' => $request->customer_phone,
            ];

            $paymentResult = $tripayService->createPayment($bundleBill, $customerData, $request->query('return'));

            $bundleBill->bill_ref = $paymentResult['data']['reference'];
            $bundleBill->save();

            if ($paymentResult['success']) {
                return redirect($paymentResult['data']['checkout_url']);
            } else {
                return back()->with('error', 'Gagal memproses pembayaran bundel');
            }
        } catch (\Exception $e) {
            Log::error('Bundle payment processing error', [
                'customer_code' => $customerCode,
                'bundle_reference' => $bundleReference,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal memproses pembayaran bundel: ' . $e->getMessage());
        }
    }

    /**
     * Check bundle payment status
     */
    public function checkStatus($customerCode, $bundleReference)
    {
        try {
            $customer = Customer::where('customer_code', $customerCode)->firstOrFail();

            $bundleBill = Bill::where('bundle_reference', $bundleReference)
                ->where('customer_id', $customer->customer_id)
                ->firstOrFail();

            if (!$bundleBill->bill_ref) {
                $bundleBill->status = 'unpaid';
                $bundleBill->save();

                return response()->json([
                    'success' => false,
                    'message' => 'Referensi pembayaran tidak ditemukan'
                ]);
            }

            $tripayService = new TripayService($customer->village);
            $statusResult = $tripayService->checkTransactionStatus($bundleBill->bill_ref);
            $retrieveSignature = $tripayService->generateSignature($bundleBill->bill_ref, $statusResult['amount'] ?? null);

            if ($statusResult['status']) {
                $finalBill = $tripayService->processCallback([
                    'merchant_ref' => $bundleBill->bill_ref,
                    'status' => $statusResult['status'],
                    'signature' => $retrieveSignature ?? null,
                    'amount' => $statusResult['amount'] ?? null,
                    'data' => $statusResult['data'] ?? [],
                ]);

                return response()->json([
                    'success' => true,
                    'status' => $finalBill->status,
                    'message' => 'Status pembayaran bundel berhasil diperiksa'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memeriksa status pembayaran bundel'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Bundle payment status check error', [
                'customer_code' => $customerCode,
                'bundle_reference' => $bundleReference,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memeriksa status pembayaran bundel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Tripay callback for bundle payments
     */
    public function handleCallback(Request $request, $village)
    {
        $data = $request->all();

        Log::info('Bundle payment callback received', [
            'merchant_ref' => $data['merchant_ref'] ?? 'unknown',
            'status' => $data['status'] ?? 'unknown',
            'village' => $village,
            'data' => $data,
        ]);

        try {
            $merchantRef = $data['merchant_ref'] ?? null;
            if (!$merchantRef) {
                return response()->json(['error' => 'Data callback tidak valid'], 400);
            }

            // Find bundle bill by merchant reference
            $bundleBill = Bill::where('bill_ref', $merchantRef)
                ->where('bill_count', '>', 1) // Ensure it's a bundle
                ->with(['customer.village', 'bundledBills'])
                ->first();

            if (!$bundleBill) {
                return response()->json(['error' => 'Pembayaran bundel tidak ditemukan'], 404);
            }

            $villageModel = $bundleBill->customer->village;
            if (!$villageModel || $villageModel->slug !== $village) {
                return response()->json(['error' => 'Desa tidak sesuai'], 400);
            }

            // Initialize Tripay service
            $tripayService = new TripayService($villageModel);

            // Process the callback
            $updatedBill = $tripayService->processCallback($data);

            Log::info('Bundle payment callback processed successfully', [
                'bundle_bill_id' => $updatedBill->bill_id,
                'bundle_reference' => $updatedBill->bundle_reference,
                'status' => $updatedBill->status,
            ]);

            return response()->json(['message' => 'Callback pembayaran bundel berhasil diproses']);
        } catch (\Exception $e) {
            Log::error('Failed to process bundle payment callback', [
                'error' => $e->getMessage(),
                'village' => $village,
                'data' => $data,
            ]);

            return response()->json(['error' => 'Gagal memproses callback pembayaran bundel'], 500);
        }
    }

    /**
     * Perform payment cleanup for a customer (same logic as portal)
     */
    private function performBillCleanup($customer)
    {
        // Clean up expired payments older than 7 days
        $expiredPayments = \App\Models\Payment::where('status', 'pending')
            ->where('updated_at', '<', now()->subDays(7))
            ->whereHas('bills', function($q) use ($customer) {
                $q->where('customer_id', $customer->customer_id);
            });

        $expiredPayments->update(['status' => 'expired']);

        // No additional cleanup needed in simplified architecture
    }

    /**
     * Get all bill categories for a customer
     */
    private function getAllBillCategories($customer)
    {
        // Simplified version for new architecture
        $availableUnpaidBills = $customer->bills()
            ->where('status', 'unpaid')
            ->whereDoesntHave('payments', function($q) {
                $q->where('status', 'pending');
            }) // Not in pending payment
            ->with(['waterUsage.billingPeriod'])
            ->orderBy('due_date', 'asc')
            ->get();

        $pendingBills = $customer->bills()
            ->where('status', 'unpaid')
            ->whereHas('payments', function($q) {
                $q->where('status', 'pending');
            }) // Has pending payment
            ->with(['waterUsage.billingPeriod'])
            ->orderBy('created_at', 'desc')
            ->get();

        $recentPaidBills = $customer->bills()
            ->where('status', 'paid')
            ->with(['waterUsage.billingPeriod'])
            ->orderBy('payment_date', 'desc')
            ->limit(10)
            ->get();

        return [
            'availableUnpaidBills' => $availableUnpaidBills,
            'pendingIndividualBills' => collect([]), // Empty for compatibility
            'pendingBundles' => $pendingBills,
            'recentPaidBills' => $recentPaidBills,
            'recentPaidBundles' => collect([]), // Empty for compatibility
            'billsInPendingBundles' => $pendingBills->pluck('bill_id')->toArray()
        ];
    }

    /**
     * Validate bills for bundle payment - matches frontend logic exactly
     * Bills must be: unpaid, same customer, no pending payments
     */
    private function validateBillsForBundle($customer, array $billIds)
    {
        return Bill::whereIn('bill_id', $billIds)
            ->where('customer_id', $customer->customer_id) // Same customer
            ->whereIn('status', ['unpaid', 'overdue']) // Not paid (matches frontend)
            ->whereDoesntHave('payments', function($q) {
                $q->where('status', 'pending');
            }) // No pending payments (matches frontend)
            ->get();
    }

    /**
     * Get available bills for bundling - matches frontend selection logic
     */
    public static function getAvailableBillsForBundle($customer)
    {
        return Bill::where('customer_id', $customer->customer_id)
            ->whereIn('status', ['unpaid', 'overdue']) // Available for payment
            ->whereDoesntHave('payments', function($q) {
                $q->where('status', 'pending');
            }) // Not in pending payment
            ->with(['waterUsage.billingPeriod']) // Include relationships
            ->orderBy('due_date', 'asc') // Oldest first
            ->get();
    }
}
