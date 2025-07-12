<?php
// app/Http/Controllers/TripayController.php - Complete implementation

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Village;
use App\Services\TripayService;
use Illuminate\Http\Request;
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
     * Create QRIS payment for a bill
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

            // Initialize Tripay service
            $tripayService = new TripayService($village);

            // Customer data
            $customerData = [
                'name' => $request->customer_name,
                'email' => $request->customer_email,
                'phone' => $request->customer_phone,
            ];

            // Create payment
            $paymentResult = $tripayService->createPayment($bill, $customerData);
            // Save billref
            $bill->bill_ref = $paymentResult['data']['reference'];
            $bill->save();

            if ($paymentResult['success']) {
                // Redirect to Tripay payment page
                return redirect($paymentResult['data']['checkout_url']);
            } else {
                return back()->with('error', 'Failed to create payment');
            }
        } catch (\Exception $e) {
            Log::error('Failed to create Tripay payment', [
                'village_slug' => $villageSlug,
                'bill_id' => $billId,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to create payment: ' . $e->getMessage());
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
                return response()->json(['error' => 'Invalid callback data'], 400);
            }

            // Extract bill ID from merchant reference (format: BILL-{id}-{timestamp})
            if (preg_match('/^BILL-(\d+)-\d+$/', $merchantRef, $matches)) {
                $billId = $matches[1];
                $bill = Bill::with('waterUsage.customer.village')->find($billId);

                if (!$bill) {
                    return response()->json(['error' => 'Bill not found'], 404);
                }

                $village = $bill->waterUsage->customer->village;
                if (!$village) {
                    return response()->json(['error' => 'Village not found'], 404);
                }

                // Initialize Tripay service with village context
                $tripayService = new TripayService($village);

                // Process the callback
                $updatedBill = $tripayService->processCallback($data);

                Log::info('Tripay callback processed successfully', [
                    'bill_id' => $updatedBill->bill_id,
                    'status' => $updatedBill->status,
                ]);

                return response()->json(['message' => 'Callback processed successfully']);
            }

            return response()->json(['error' => 'Invalid merchant reference format'], 400);
        } catch (\Exception $e) {
            Log::error('Failed to process Tripay callback', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return response()->json(['error' => 'Failed to process callback'], 500);
        }
    }

    /**
     * Handle return from Tripay payment page
     */
    public function handleReturn(Request $request)
    {
        try {
            // just return to current subdom /portal
            return redirect()->route('portal.index')->with('success', 'Payment completed successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to handle Tripay return', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('portal.index')->with('error', 'Failed to complete payment: ' . $e->getMessage());
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
                return redirect()->back()->with('error', 'Payment reference not found for this bill.');
            }

            // Continue payment process
            $paymentResult = $tripayService->continuePayment($bill->bill_ref);

            if ($paymentResult['success']) {
                // Redirect to Tripay payment page
                return redirect($paymentResult['checkout_url']);
            } else {
                return redirect()->back()->with('error', 'Failed to continue payment: ' . $paymentResult['message']);
            }
        } catch (\Exception $e) {
            Log::error('Failed to continue payment', [
                'village_slug' => $villageSlug,
                'bill_id' => $billId,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to continue payment: ' . $e->getMessage());
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

            // Change bill status to unpaid if no payment reference found
            $bill->status = 'unpaid';
            $bill->save();

            if (!$bill->bill_ref) {
                return response()->json([
                    'success' => false,
                    'message' => 'No payment reference found'
                ]);
            }

            // Initialize Tripay service
            $tripayService = new TripayService($village);

            // Check payment status
            $statusResult = $tripayService->checkTransactionStatus($bill->bill_ref);
            if ($statusResult['success']) {
                // Update bill status based on Tripay response
                $bill->status = $statusResult['data']['status'];
                $bill->save();

                return response()->json([
                    'success' => true,
                    'status' => $bill->status,
                    'message' => 'Payment status checked successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to check payment status'
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
                'message' => 'Failed to check payment status'
            ], 500);
        }
    }
}
