<?php
// app/Http/Controllers/TripayController.php - Complete implementation

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\Village;
use App\Services\TripayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TripayController extends Controller
{
    /**
     * Show payment form
     */
    public function showPaymentForm($bill)
    {
        try {
            // Get village from current context (subdomain)
            $villageId = config('pamdes.current_village_id');
            $village = Village::where('id', $villageId)->with('variables')->firstOrFail();
            $bill = Bill::where('bill_id', $bill)
                ->whereHas('waterUsage.customer', function ($q) use ($village) {
                    $q->where('village_id', $village->id);
                })
                ->firstOrFail();

            // Check if bill has pending payment
            $pendingPayment = $bill->getPendingPayment();
            $hasPendingPayment = $pendingPayment !== null;

            return view('tripay.payment-form', compact('village', 'bill', 'hasPendingPayment', 'pendingPayment'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Tagihan tidak ditemukan atau sudah dibayar ' . $e->getMessage());
        }
    }

    /**
     * Create QRIS payment for a bill with proper transaction handling
     */
    public function createPayment(Request $request, $bill)
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'customer_name' => 'required|string|max:255',
                'customer_email' => 'required|email',
                'customer_phone' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            // Get village from current context and find bill
            $villageId = config('pamdes.current_village_id');
            $village = Village::where('id', $villageId)->firstOrFail();
            $bill = Bill::where('bill_id', $bill)
                ->whereHas('waterUsage.customer', function ($q) use ($village) {
                    $q->where('village_id', $village->id);
                })
                ->firstOrFail();

            // Check bill status and handle accordingly
            if ($bill->status === 'paid') {
                return back()->with('error', 'Tagihan ini sudah dibayar.');
            }

            if ($bill->status === 'pending' && $bill->bill_ref) {
                return back()->with('error', 'Tagihan ini sudah memiliki pembayaran yang sedang diproses. Silakan selesaikan pembayaran tersebut terlebih dahulu.');
            }

            // Use database transaction to ensure atomicity
            return DB::transaction(function () use ($bill, $village, $request) {

                // Initialize Tripay service
                $tripayService = new TripayService($village);

                // Customer data
                $customerData = [
                    'name' => $request->customer_name,
                    'email' => $request->customer_email,
                    'phone' => $request->customer_phone,
                ];

                // Create payment with Tripay
                $paymentResult = $tripayService->createPayment($bill, $customerData, $request->query('return'));

                // Only proceed if Tripay payment creation was successful
                if (!$paymentResult['success']) {
                    // This will trigger transaction rollback
                    throw new \Exception('Tripay payment creation failed: ' . ($paymentResult['message'] ?? 'Unknown error'));
                }

                // Create payment record with merchant reference as transaction_ref for consistency
                $merchantRef = $paymentResult['data']['merchant_ref'] ?? null;
                $payment = Payment::createPayment([$bill->bill_id], [
                    'payment_method' => 'qris',
                    'transaction_ref' => $merchantRef,
                    'tripay_data' => $paymentResult,
                    'collector_id' => null,
                    'notes' => 'Single payment via Tripay'
                ]);

                Log::info('Single payment created successfully', [
                    'bill_id' => $bill->bill_id,
                    'village_id' => $village->id,
                    'total_amount' => $bill->total_amount,
                    'tripay_reference' => $bill->bill_ref,
                ]);

                // Redirect to Tripay payment page
                return redirect($paymentResult['data']['checkout_url']);
            });
        } catch (\Exception $e) {
            Log::error('Failed to create Tripay payment', [
                'bill_id' => $bill,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'Gagal membuat pembayaran: ' . $e->getMessage());
        }
    }

    /**
     * Handle Tripay callback (webhook)
     */
    public function handleCallback(Request $request)
    {
        $data = $request->all();

        Log::info('Tripay callback received', [
            'merchant_ref' => $data['merchant_ref'] ?? 'unknown',
            'status' => $data['status'] ?? 'unknown',
            'data' => $data,
        ]);

        try {
            // Find the village from the bill reference
            $merchantRef = $data['merchant_ref'] ?? null;
            if (!$merchantRef) {
                return response()->json(['error' => 'Data callback tidak valid'], 400);
            }

            // Extract bill ID from merchant reference (format: BILL-{id}-{timestamp})
            if (preg_match('/^BILL-(\d+)-\d+$/', $merchantRef, $matches)) {
                $billId = $matches[1];
                $bill = Bill::with('waterUsage.customer.village')->find($billId);

                if (!$bill) {
                    return response()->json(['error' => 'Tagihan tidak ditemukan'], 404);
                }

                $village = $bill->waterUsage->customer->village;
                if (!$village) {
                    return response()->json(['error' => 'Desa tidak ditemukan'], 404);
                }

                // Initialize Tripay service with village context
                $tripayService = new TripayService($village);

                // Process the callback
                $updatedBill = $tripayService->processCallback($data);

                Log::info('Tripay callback processed successfully', [
                    'bill_id' => $updatedBill->bill_id,
                    'status' => $updatedBill->status,
                ]);

                return response()->json(['message' => 'Callback berhasil diproses']);
            }

            return response()->json(['error' => 'Format referensi merchant tidak valid'], 400);
        } catch (\Exception $e) {
            Log::error('Failed to process Tripay callback', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return response()->json(['error' => 'Gagal memproses callback'], 500);
        }
    }

    /**
     * Handle return from Tripay payment page
     */
    public function handleReturn(Request $request)
    {
        try {
            // just return to current subdom /portal
            return redirect()->route('portal.index')->with('success', 'Pembayaran berhasil diselesaikan.');
        } catch (\Exception $e) {
            Log::error('Failed to handle Tripay return', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('portal.index')->with('error', 'Gagal menyelesaikan pembayaran: ' . $e->getMessage());
        }
    }

    /**
     * Handle continue payment - check status and redirect accordingly
     */
    public function continuePayment(Request $request, $bill)
    {
        try {
            // Get village from current context and find bill
            $villageId = config('pamdes.current_village_id');
            $village = Village::where('id', $villageId)->firstOrFail();
            $bill = Bill::where('bill_id', $bill)
                ->whereHas('waterUsage.customer', function ($q) use ($village) {
                    $q->where('village_id', $village->id);
                })
                ->firstOrFail();

            // Get pending payment for this bill
            $payment = $bill->getPendingPayment();

            if (!$payment || !$payment->transaction_ref) {
                return redirect()->back()->with('error', 'Tidak ada pembayaran yang sedang berlangsung untuk tagihan ini.');
            }

            // Initialize Tripay service
            $tripayService = new TripayService($village);

            // Check payment status first
            $statusResult = $tripayService->checkPaymentStatus($payment->transaction_ref);

            if ($statusResult['success']) {
                $status = $statusResult['data']['status'] ?? null;

                if ($status === 'PAID') {
                    // Payment completed - update payment and bills
                    $payment->completeBillPayments();
                    return redirect()->back()->with('success', 'Pembayaran telah berhasil diselesaikan.');
                } elseif (in_array($status, ['EXPIRED', 'FAILED', 'CANCELLED'])) {
                    // Payment failed/expired - mark as expired
                    $payment->expirePayment();
                    return redirect()->back()->with('warning', 'Pembayaran telah kedaluwarsa. Silakan buat pembayaran baru.');
                } elseif ($status === 'PENDING') {
                    // Still pending - construct checkout URL directly
                    $continueResult = $tripayService->continuePayment($payment->transaction_ref);
                    if ($continueResult['success']) {
                        return redirect($continueResult['checkout_url']);
                    } else {
                        return redirect()->back()->with('error', 'Gagal mendapatkan URL pembayaran.');
                    }
                }
            }

            return redirect()->back()->with('error', 'Gagal memeriksa status pembayaran.');
        } catch (\Exception $e) {
            Log::error('Failed to continue payment', [
                'bill_id' => $bill,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Gagal melanjutkan pembayaran: ' . $e->getMessage());
        }
    }

    /**
     * Check payment status
     */
    public function checkStatus(Request $request, $bill)
    {
        try {
            $villageId = config('pamdes.current_village_id');
            $village = Village::where('id', $villageId)->firstOrFail();
            $bill = Bill::where('bill_id', $bill)
                ->whereHas('waterUsage.customer', function ($q) use ($village) {
                    $q->where('village_id', $village->id);
                })
                ->firstOrFail();

            // Get pending payment for this bill
            $payment = $bill->getPendingPayment();

            if (!$payment || !$payment->transaction_ref) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada pembayaran yang sedang berlangsung'
                ]);
            }

            // Initialize Tripay service
            $tripayService = new TripayService($village);

            // Get Tripay reference from tripay_data for status checking
            $tripayReference = $payment->tripay_data['data']['reference'] ?? $payment->tripay_data['merchant_ref'] ?? null;

            if (!$tripayReference) {
                return response()->json([
                    'success' => false,
                    'message' => 'Referensi Tripay tidak ditemukan'
                ]);
            }

            // Check payment status using Tripay reference
            $statusResult = $tripayService->checkPaymentStatus($tripayReference);

            if ($statusResult['success']) {
                $status = $statusResult['data']['status'] ?? null;

                if ($status === 'PAID') {
                    // Payment completed - update payment and bills
                    $payment->completeBillPayments();

                    return response()->json([
                        'success' => true,
                        'status' => 'paid',
                        'message' => 'Pembayaran berhasil diselesaikan'
                    ]);
                } elseif (in_array($status, ['EXPIRED', 'FAILED', 'CANCELLED'])) {
                    // Payment failed/expired - mark as expired
                    $payment->expirePayment();

                    return response()->json([
                        'success' => true,
                        'status' => 'expired',
                        'message' => 'Pembayaran telah kedaluwarsa'
                    ]);
                } else {
                    // Still pending
                    return response()->json([
                        'success' => true,
                        'status' => 'pending',
                        'message' => 'Pembayaran masih dalam proses'
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal memeriksa status pembayaran'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check payment status', [
                'bill_id' => $bill,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memeriksa status pembayaran: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check payment status by reference directly
     */
    public function checkStatusByReference(Request $request, $bill, $reference)
    {
        try {
            // Get village from current context (subdomain)
            $villageId = config('pamdes.current_village_id');
            $village = Village::where('id', $villageId)->firstOrFail();
            
            // Initialize Tripay service
            $tripayService = new TripayService($village);

            // Check payment status using the reference directly
            Log::info('Checking payment status with reference', [
                'reference' => $reference,
                'village_id' => $village->id
            ]);
            $statusResult = $tripayService->checkPaymentStatus($reference);

            if ($statusResult['success']) {
                $status = $statusResult['data']['status'] ?? null;
                
                // Find the payment by reference to update it
                $payment = Payment::where('transaction_ref', $reference)->first();
                
                if ($payment && $status === 'PAID') {
                    // Payment completed - update payment and bills
                    $payment->completeBillPayments();
                    
                    return response()->json([
                        'success' => true,
                        'status' => 'paid',
                        'message' => 'Pembayaran berhasil diselesaikan'
                    ]);
                } elseif ($payment && in_array($status, ['EXPIRED', 'FAILED', 'CANCELLED'])) {
                    // Payment failed/expired - mark as expired
                    $payment->expirePayment();
                    
                    return response()->json([
                        'success' => true,
                        'status' => 'expired',
                        'message' => 'Pembayaran telah kedaluwarsa'
                    ]);
                } else {
                    // Still pending or no payment found
                    return response()->json([
                        'success' => true,
                        'status' => 'pending',
                        'message' => 'Pembayaran masih dalam proses'
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal memeriksa status pembayaran'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check payment status by reference', [
                'village_id' => $villageId ?? null,
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memeriksa status pembayaran: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show bundle payment form
     */
    public function showBundlePaymentForm($paymentId)
    {
        try {
            // Get village from current context (subdomain)
            $villageId = config('pamdes.current_village_id');
            $village = Village::where('id', $villageId)->with('variables')->firstOrFail();
            $payment = Payment::where('payment_id', $paymentId)
                ->whereHas('bills.waterUsage.customer', function ($q) use ($village) {
                    $q->where('village_id', $village->id);
                })
                ->with(['bills.waterUsage.customer', 'bills.waterUsage.billingPeriod'])
                ->firstOrFail();

            // Get bills associated with this payment
            $bills = $payment->bills;
            $customer = $bills->first()->waterUsage->customer;
            
            // Check if payment has pending status
            $hasPendingPayment = $payment->status === 'pending';
            $pendingPayment = $hasPendingPayment ? $payment : null;
            
            // Set flag for bundle payment form
            $showingBundleForm = true;
            $total_amount = $payment->total_amount;
            
            // Set action URL for bundle payment form (should not be used for existing payments)
            $action_url = '#'; // Placeholder since this is for existing payments, not new ones

            return view('tripay.payment-form', compact(
                'village', 
                'bills', 
                'customer', 
                'hasPendingPayment', 
                'pendingPayment',
                'showingBundleForm',
                'total_amount',
                'action_url'
            ));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Bundle payment tidak ditemukan: ' . $e->getMessage());
        }
    }
}
