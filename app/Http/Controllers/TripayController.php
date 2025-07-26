<?php
// app/Http/Controllers/TripayController.php - Complete implementation

namespace App\Http\Controllers;

use App\Models\Bill;
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
    public function showPaymentForm($villageSlug, $billId)
    {
        try {
            $village = Village::where('slug', $villageSlug)->firstOrFail();
            $bill = Bill::where('bill_id', $billId)
                ->whereHas('waterUsage.customer', function ($q) use ($village) {
                    $q->where('village_id', $village->id);
                })
                ->where('status', '!=', 'paid')
                ->firstOrFail();

            return view('tripay.payment-form', compact('village', 'bill'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Tagihan tidak ditemukan atau sudah dibayar');
        }
    }

    /**
     * Create QRIS payment for a bill with proper transaction handling
     */
    public function createPayment(Request $request, $villageSlug, $billId)
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

            // Find village and bill
            $village = Village::where('slug', $villageSlug)->firstOrFail();
            $bill = Bill::where('bill_id', $billId)
                ->whereHas('waterUsage.customer', function ($q) use ($village) {
                    $q->where('village_id', $village->id);
                })
                ->where('status', '!=', 'paid')
                ->firstOrFail();

            // Check if bill already has a pending payment
            if ($bill->status === 'pending' && $bill->bill_ref) {
                return back()->with('error', 'Tagihan ini sudah memiliki pembayaran yang sedang diproses. Silakan selesaikan pembayaran tersebut terlebih dahulu.');
            }

            // Use database transaction to ensure atomicity
            return DB::transaction(function () use ($bill, $village, $request, $villageSlug, $billId) {
                
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

                // Save bill reference and update status only after Tripay success
                $bill->bill_ref = $paymentResult['data']['reference'] ?? null;
                $bill->status = 'pending';
                $bill->save();

                Log::info('Single payment created successfully', [
                    'bill_id' => $bill->bill_id,
                    'village_slug' => $villageSlug,
                    'total_amount' => $bill->total_amount,
                    'tripay_reference' => $bill->bill_ref,
                ]);

                // Redirect to Tripay payment page
                return redirect($paymentResult['data']['checkout_url']);
            });

        } catch (\Exception $e) {
            Log::error('Failed to create Tripay payment', [
                'village_slug' => $villageSlug,
                'bill_id' => $billId,
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
     * Handle continue from Tripay payment page
     */
    public function continuePayment(Request $request, $villageSlug, $billId)
    {
        try {
            // Find village and bill
            $village = Village::where('slug', $villageSlug)->firstOrFail();
            $bill = Bill::where('bill_id', $billId)
                ->whereHas('waterUsage.customer', function ($q) use ($village) {
                    $q->where('village_id', $village->id);
                })
                ->firstOrFail();

            // Initialize Tripay service
            $tripayService = new TripayService($village);

            // Check if the bill has a payment reference
            if (!$bill->bill_ref) {
                return redirect()->back()->with('error', 'Referensi pembayaran tidak ditemukan untuk tagihan ini.');
            }

            // Continue payment process
            $paymentResult = $tripayService->continuePayment($bill->bill_ref);

            if ($paymentResult['success']) {
                // Redirect to Tripay payment page
                return redirect($paymentResult['checkout_url']);
            } else {
                return redirect()->back()->with('error', 'Gagal melanjutkan pembayaran: ' . $paymentResult['message']);
            }
        } catch (\Exception $e) {
            Log::error('Failed to continue payment', [
                'village_slug' => $villageSlug,
                'bill_id' => $billId,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Gagal melanjutkan pembayaran: ' . $e->getMessage());
        }
    }

    /**
     * Check payment status
     */
    public function checkStatus(Request $request, $villageSlug, $billId)
    {
        try {
            $village = Village::where('slug', $villageSlug)->firstOrFail();
            $bill = Bill::where('bill_id', $billId)
                ->whereHas('waterUsage.customer', function ($q) use ($village) {
                    $q->where('village_id', $village->id);
                })
                ->firstOrFail();

            if (!$bill->bill_ref) {
                // Change bill status to unpaid if no payment reference found
                $bill->status = 'unpaid';
                $bill->save();

                return response()->json([
                    'success' => false,
                    'message' => 'Referensi pembayaran tidak ditemukan'
                ]);
            }

            // Initialize Tripay service
            $tripayService = new TripayService($village);

            // Check payment status
            $statusResult = $tripayService->checkTransactionStatus($bill->bill_ref);
            $retrieveSignature = $tripayService->generateSignature($bill->bill_ref, $statusResult['amount'] ?? null);

            if ($statusResult['status']) {
                // Update bill status based on payment status
                $finalBill = $tripayService->processCallback([
                    'merchant_ref' => $bill->bill_ref,
                    'status' => $statusResult['status'],
                    'signature' => $retrieveSignature ?? null,
                    'amount' => $statusResult['amount'] ?? null,
                    'data' => $statusResult['data'] ?? [],
                ]);

                return response()->json([
                    'success' => true,
                    'status' => $finalBill->status,
                    'message' => 'Status pembayaran berhasil diperiksa'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memeriksa status pembayaran'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to check payment status', [
                'village_slug' => $villageSlug,
                'bill_id' => $billId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memeriksa status pembayaran: ' . $e->getMessage()
            ], 500);
        }
    }
}
