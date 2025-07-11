<?php

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
            $bill = Bill::where('id', $billId)
                ->where('village_id', $village->id)
                ->where('status', 'unpaid')
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
                $bill = Bill::find($billId);

                if (!$bill) {
                    return response()->json(['error' => 'Bill not found'], 404);
                }

                $village = Village::find($bill->village_id);
                if (!$village) {
                    return response()->json(['error' => 'Village not found'], 404);
                }

                // Initialize Tripay service with village context
                $tripayService = new TripayService($village);

                // Process the callback
                $updatedBill = $tripayService->processCallback($data);

                Log::info('Tripay callback processed successfully', [
                    'bill_id' => $updatedBill->id,
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
        $merchantRef = $request->get('tripay_merchant_ref');

        Log::info('Tripay return received', [
            'merchant_ref' => $merchantRef,
            'data' => $request->all(),
        ]);

        try {
            if (!$merchantRef) {
                return redirect()->route('home')->with('error', 'Invalid payment return');
            }

            // Extract bill ID from merchant reference
            if (preg_match('/^BILL-(\d+)-\d+$/', $merchantRef, $matches)) {
                $billId = $matches[1];
                $bill = Bill::find($billId);

                if (!$bill) {
                    return redirect()->route('home')->with('error', 'Bill not found');
                }

                $village = Village::find($bill->village_id);
                if (!$village) {
                    return redirect()->route('home')->with('error', 'Village not found');
                }

                // Redirect to bill detail or payment status page
                return redirect()->route('village.bill.show', [
                    'village' => $village->slug,
                    'bill' => $bill->id
                ])->with('success', 'Payment process completed. Please check your payment status.');
            }

            return redirect()->route('home')->with('error', 'Invalid payment reference');
        } catch (\Exception $e) {
            Log::error('Failed to handle Tripay return', [
                'error' => $e->getMessage(),
                'merchant_ref' => $merchantRef,
            ]);

            return redirect()->route('home')->with('error', 'Payment return processing failed');
        }
    }

    /**
     * Check payment status
     */
    public function checkStatus(Request $request, $villageSlug, $billId)
    {
        try {
            $village = Village::where('slug', $villageSlug)->firstOrFail();
            $bill = Bill::where('id', $billId)
                ->where('village_id', $village->id)
                ->firstOrFail();

            if (!$bill->bill_ref) {
                return response()->json([
                    'success' => false,
                    'message' => 'No payment reference found'
                ]);
            }

            // Initialize Tripay service
            $tripayService = new TripayService($village);

            // For checking status, we need the Tripay reference, not our merchant ref
            // This would require storing the Tripay reference in the bill record
            // For now, return the current bill status
            return response()->json([
                'success' => true,
                'data' => [
                    'bill_id' => $bill->id,
                    'status' => $bill->status,
                    'amount' => $bill->amount,
                    'paid_at' => $bill->paid_at,
                ]
            ]);
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

    /**
     * Show payment form
     */
    public function showPaymentForm($villageSlug, $billId)
    {
        try {
            $village = Village::where('slug', $villageSlug)->firstOrFail();
            $bill = Bill::where('id', $billId)
                ->where('village_id', $village->id)
                ->where('status', 'unpaid')
                ->firstOrFail();

            return view('tripay.payment-form', compact('village', 'bill'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Bill not found or already paid');
        }
    }
}
