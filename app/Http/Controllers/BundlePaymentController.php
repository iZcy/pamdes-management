<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\BundlePayment;
use App\Models\Customer;
use App\Models\Village;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BundlePaymentController extends Controller
{
    /**
     * Show bundle payment form (email selection) - like single payment form
     */
    public function showPaymentForm(Request $request, $customerCode)
    {
        $validator = Validator::make($request->all(), [
            'bill_ids' => 'required|array|min:1',
            'bill_ids.*' => 'required|exists:bills,bill_id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Data tagihan tidak valid.');
        }

        try {
            // Find customer
            $customer = Customer::where('customer_code', $customerCode)->firstOrFail();
            $village = $customer->village;
            
            // Get bills and validate they belong to the customer
            $bills = Bill::whereIn('bill_id', $request->bill_ids)
                ->whereHas('waterUsage', function ($query) use ($customer) {
                    $query->where('customer_id', $customer->customer_id);
                })
                ->whereIn('status', ['unpaid', 'overdue']) // Exclude pending bills
                ->with(['waterUsage.billingPeriod'])
                ->get();

            if ($bills->isEmpty()) {
                return redirect()->back()->with('error', 'Tidak ada tagihan yang valid untuk dibayar.');
            }

            if ($bills->count() !== count($request->bill_ids)) {
                // Check if some bills are pending
                $pendingBills = Bill::whereIn('bill_id', $request->bill_ids)
                    ->whereHas('waterUsage', function ($query) use ($customer) {
                        $query->where('customer_id', $customer->customer_id);
                    })
                    ->where('status', 'pending')
                    ->count();
                
                if ($pendingBills > 0) {
                    return redirect()->back()->with('error', 'Beberapa tagihan sedang dalam proses pembayaran. Mohon tunggu konfirmasi atau cek kembali dalam beberapa saat.');
                }
                
                return redirect()->back()->with('error', 'Beberapa tagihan tidak valid atau sudah dibayar.');
            }

            // Calculate bundle total with corrected fees
            $totalWaterCharge = $bills->sum('water_charge');
            $totalMaintenanceFee = $bills->sum('maintenance_fee');
            $totalAdminFee = $bills->sum('admin_fee'); // Accumulative admin fee
            $totalAmount = $totalWaterCharge + $totalAdminFee + $totalMaintenanceFee;

            // Create bundle payment object for display (not saved yet)
            $bundlePayment = (object) [
                'bills' => $bills,
                'bill_count' => $bills->count(),
                'total_amount' => $totalAmount,
                'customer' => $customer,
                'bill_ids' => $request->bill_ids,
                'status' => 'form'
            ];

            return view('bundle-payment.form', compact('customer', 'village', 'bundlePayment'));

        } catch (\Exception $e) {
            Log::error('Bundle payment form error', [
                'customer_code' => $customerCode,
                'bill_ids' => $request->bill_ids,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->with('error', 'Gagal memuat form pembayaran. Silakan coba lagi.');
        }
    }

    /**
     * Create a new bundle payment
     */
    public function create(Request $request, $customerCode)
    {
        $validator = Validator::make($request->all(), [
            'bill_ids' => 'required|array|min:1',
            'bill_ids.*' => 'required|exists:bills,bill_id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Data tagihan tidak valid.');
        }

        try {
            // Find customer
            $customer = Customer::where('customer_code', $customerCode)->firstOrFail();
            
            // Get bills and validate they belong to the customer
            $bills = Bill::whereIn('bill_id', $request->bill_ids)
                ->whereHas('waterUsage', function ($query) use ($customer) {
                    $query->where('customer_id', $customer->customer_id);
                })
                ->whereIn('status', ['unpaid', 'overdue']) // Exclude pending bills
                ->get();

            if ($bills->isEmpty()) {
                return redirect()->back()->with('error', 'Tidak ada tagihan yang valid untuk dibayar.');
            }

            if ($bills->count() !== count($request->bill_ids)) {
                // Check if some bills are pending
                $pendingBills = Bill::whereIn('bill_id', $request->bill_ids)
                    ->whereHas('waterUsage', function ($query) use ($customer) {
                        $query->where('customer_id', $customer->customer_id);
                    })
                    ->where('status', 'pending')
                    ->count();
                
                if ($pendingBills > 0) {
                    return redirect()->back()->with('error', 'Beberapa tagihan sedang dalam proses pembayaran. Mohon tunggu konfirmasi atau cek kembali dalam beberapa saat.');
                }
                
                return redirect()->back()->with('error', 'Beberapa tagihan tidak valid atau sudah dibayar.');
            }

            DB::beginTransaction();

            // Calculate bundle total amount with corrected fees
            // For bundle payment: accumulative maintenance fee, accumulative admin fee  
            $totalWaterCharge = $bills->sum('water_charge');
            $totalMaintenanceFee = $bills->sum('maintenance_fee'); // Accumulative
            $totalAdminFee = $bills->sum('admin_fee'); // Accumulative
            
            $correctedTotalAmount = $totalWaterCharge + $totalAdminFee + $totalMaintenanceFee;

            // Create bundle payment with corrected total
            $bundlePayment = BundlePayment::create([
                'bundle_reference' => BundlePayment::generateBundleReference(),
                'customer_id' => $customer->customer_id,
                'total_amount' => $correctedTotalAmount,
                'bill_count' => $bills->count(),
                'status' => 'pending',
                'payment_method' => 'qris',
                'expires_at' => now()->addHours(1), // 1 hour expiration
            ]);

            // Attach bills to bundle payment
            foreach ($bills as $bill) {
                $bundlePayment->bundlePaymentBills()->create([
                    'bill_id' => $bill->bill_id,
                    'bill_amount' => $bill->total_amount,
                ]);

                // Update bill status to pending
                $bill->update(['status' => 'pending']);
            }

            DB::commit();

            // Redirect to bundle payment form
            return redirect()->route('bundle.payment.form', [
                'customer_code' => $customerCode,
                'bundle_reference' => $bundlePayment->bundle_reference
            ])->with('success', 'Pembayaran bundel berhasil dibuat.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bundle payment creation failed', [
                'customer_code' => $customerCode,
                'bill_ids' => $request->bill_ids,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->with('error', 'Gagal membuat pembayaran bundel. Silakan coba lagi.');
        }
    }

    /**
     * Show bundle payment form
     */
    public function showForm($customerCode, $bundleReference)
    {
        try {
            $customer = Customer::where('customer_code', $customerCode)->firstOrFail();
            $village = $customer->village;

            $bundlePayment = BundlePayment::where('bundle_reference', $bundleReference)
                ->where('customer_id', $customer->customer_id)
                ->with(['bills.waterUsage.billingPeriod'])
                ->firstOrFail();

            // Check if bundle payment is still valid
            if ($bundlePayment->is_expired) {
                $bundlePayment->markAsExpired();
                return redirect()->route('portal.bills', $customerCode)
                    ->with('error', 'Pembayaran bundel telah kedaluwarsa. Silakan buat pembayaran baru.');
            }

            if ($bundlePayment->status === 'paid') {
                return redirect()->route('portal.bills', $customerCode)
                    ->with('success', 'Pembayaran bundel sudah lunas.');
            }

            return view('bundle-payment.form', compact('customer', 'village', 'bundlePayment'));

        } catch (\Exception $e) {
            Log::error('Bundle payment form error', [
                'customer_code' => $customerCode,
                'bundle_reference' => $bundleReference,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('portal.bills', $customerCode)
                ->with('error', 'Pembayaran bundel tidak ditemukan.');
        }
    }

    /**
     * Process bundle payment with Tripay
     */
    public function processPayment(Request $request, $customerCode, $bundleReference)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $customer = Customer::where('customer_code', $customerCode)->firstOrFail();
            $village = $customer->village;

            $bundlePayment = BundlePayment::where('bundle_reference', $bundleReference)
                ->where('customer_id', $customer->customer_id)
                ->firstOrFail();

            if (!$bundlePayment->canBePaid()) {
                return redirect()->route('portal.bills', $customerCode)
                    ->with('error', 'Pembayaran bundel tidak dapat diproses.');
            }

            // Process with Tripay (similar to single bill payment)
            $tripayService = new \App\Services\TripayService($village);

            // Calculate corrected order items for display
            $correctedOrderItems = [];
            
            // Add water charges as individual items
            foreach ($bundlePayment->bills as $bill) {
                $correctedOrderItems[] = [
                    'name' => "Tagihan Air - {$bill->waterUsage->billingPeriod->period_name}",
                    'price' => $bill->water_charge,
                    'quantity' => 1,
                ];
            }
            
            // Add accumulative admin fee
            $totalAdminFee = $bundlePayment->bills->sum('admin_fee');
            if ($totalAdminFee > 0) {
                $correctedOrderItems[] = [
                    'name' => "Biaya Admin (Total)",
                    'price' => $totalAdminFee,
                    'quantity' => 1,
                ];
            }
            
            // Add accumulative maintenance fee
            $totalMaintenanceFee = $bundlePayment->bills->sum('maintenance_fee');
            if ($totalMaintenanceFee > 0) {
                $correctedOrderItems[] = [
                    'name' => "Biaya Pemeliharaan (Total)",
                    'price' => $totalMaintenanceFee,
                    'quantity' => 1,
                ];
            }

            $paymentData = [
                'method' => 'QRIS',
                'merchant_ref' => $bundlePayment->bundle_reference,
                'amount' => $bundlePayment->total_amount,
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'order_items' => $correctedOrderItems,
                'return_url' => route('portal.bills', $customerCode),
                'expired_time' => (time() + (1 * 60 * 60)), // 1 hour
            ];

            $tripayResponse = $tripayService->createTransaction($village, $paymentData);

            if ($tripayResponse['success']) {
                // Update bundle payment with Tripay data
                $bundlePayment->update([
                    'payment_reference' => $tripayResponse['data']['reference'],
                    'tripay_data' => $tripayResponse['data'],
                    'expires_at' => now()->addHours(1),
                ]);

                // Redirect to Tripay checkout
                return redirect()->away($tripayResponse['data']['checkout_url']);
            } else {
                return redirect()->back()
                    ->with('error', 'Gagal membuat pembayaran: ' . $tripayResponse['message'])
                    ->withInput();
            }

        } catch (\Exception $e) {
            Log::error('Bundle payment processing failed', [
                'customer_code' => $customerCode,
                'bundle_reference' => $bundleReference,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal memproses pembayaran. Silakan coba lagi.')
                ->withInput();
        }
    }

    /**
     * Check bundle payment status
     */
    public function checkStatus($customerCode, $bundleReference)
    {
        try {
            $customer = Customer::where('customer_code', $customerCode)->firstOrFail();
            
            $bundlePayment = BundlePayment::where('bundle_reference', $bundleReference)
                ->where('customer_id', $customer->customer_id)
                ->firstOrFail();

            // If already paid, return success
            if ($bundlePayment->status === 'paid') {
                return response()->json([
                    'success' => true,
                    'status' => 'paid',
                    'message' => 'Pembayaran berhasil'
                ]);
            }

            // Check with Tripay if pending
            if ($bundlePayment->status === 'pending' && $bundlePayment->payment_reference) {
                $village = $customer->village;
                $tripayService = new \App\Services\TripayService($village);
                
                $statusResponse = $tripayService->getTransactionStatus($village, $bundlePayment->payment_reference);
                
                if ($statusResponse['success']) {
                    $tripayStatus = $statusResponse['data']['status'];
                    
                    if ($tripayStatus === 'PAID') {
                        $bundlePayment->markAsPaid();
                        
                        return response()->json([
                            'success' => true,
                            'status' => 'paid',
                            'message' => 'Pembayaran berhasil'
                        ]);
                    } elseif (in_array($tripayStatus, ['EXPIRED', 'FAILED'])) {
                        $bundlePayment->markAsFailed();
                        
                        return response()->json([
                            'success' => false,
                            'status' => strtolower($tripayStatus),
                            'message' => 'Pembayaran gagal atau kedaluwarsa'
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => false,
                'status' => $bundlePayment->status,
                'message' => 'Pembayaran masih dalam proses'
            ]);

        } catch (\Exception $e) {
            Log::error('Bundle payment status check failed', [
                'customer_code' => $customerCode,
                'bundle_reference' => $bundleReference,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Gagal mengecek status pembayaran'
            ]);
        }
    }

    /**
     * Process bundle payment directly (create bundle + process payment in one step)
     */
    public function processDirectly(Request $request, $customerCode)
    {
        $validator = Validator::make($request->all(), [
            'bill_ids' => 'required|array|min:1',
            'bill_ids.*' => 'required|exists:bills,bill_id',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid: ' . $validator->errors()->first()
                ], 400);
            }
            
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Data tidak valid.');
        }

        try {
            // Find customer
            $customer = Customer::where('customer_code', $customerCode)->firstOrFail();
            $village = $customer->village;
            
            // Get bills and validate they belong to the customer
            $bills = Bill::whereIn('bill_id', $request->bill_ids)
                ->whereHas('waterUsage', function ($query) use ($customer) {
                    $query->where('customer_id', $customer->customer_id);
                })
                ->whereIn('status', ['unpaid', 'overdue']) // Exclude pending bills
                ->with(['waterUsage.billingPeriod'])
                ->get();

            if ($bills->isEmpty()) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tidak ada tagihan yang valid untuk dibayar.'
                    ], 400);
                }
                return redirect()->back()->with('error', 'Tidak ada tagihan yang valid untuk dibayar.');
            }

            if ($bills->count() !== count($request->bill_ids)) {
                // Check if some bills are pending
                $pendingBills = Bill::whereIn('bill_id', $request->bill_ids)
                    ->whereHas('waterUsage', function ($query) use ($customer) {
                        $query->where('customer_id', $customer->customer_id);
                    })
                    ->where('status', 'pending')
                    ->count();
                
                $errorMessage = $pendingBills > 0 
                    ? 'Beberapa tagihan sedang dalam proses pembayaran. Mohon tunggu konfirmasi atau cek kembali dalam beberapa saat.'
                    : 'Beberapa tagihan tidak valid atau sudah dibayar.';
                
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMessage
                    ], 400);
                }
                
                return redirect()->back()->with('error', $errorMessage);
            }

            DB::beginTransaction();

            // Calculate bundle total amount with corrected fees
            $totalWaterCharge = $bills->sum('water_charge');
            $totalMaintenanceFee = $bills->sum('maintenance_fee'); // Accumulative
            $totalAdminFee = $bills->sum('admin_fee'); // Accumulative
            
            $correctedTotalAmount = $totalWaterCharge + $totalAdminFee + $totalMaintenanceFee;

            // Create bundle payment with corrected total
            $bundlePayment = BundlePayment::create([
                'bundle_reference' => BundlePayment::generateBundleReference(),
                'customer_id' => $customer->customer_id,
                'total_amount' => $correctedTotalAmount,
                'bill_count' => $bills->count(),
                'status' => 'pending',
                'payment_method' => 'qris',
                'expires_at' => now()->addHours(1), // 1 hour expiration
            ]);

            // Attach bills to bundle payment
            foreach ($bills as $bill) {
                $bundlePayment->bundlePaymentBills()->create([
                    'bill_id' => $bill->bill_id,
                    'bill_amount' => $bill->total_amount,
                ]);

                // Update bill status to pending
                $bill->update(['status' => 'pending']);
            }

            // Process with Tripay immediately
            $tripayService = new \App\Services\TripayService($village);

            // Calculate corrected order items for display
            $correctedOrderItems = [];
            
            // Add water charges as individual items
            foreach ($bills as $bill) {
                $correctedOrderItems[] = [
                    'name' => "Tagihan Air - {$bill->waterUsage->billingPeriod->period_name}",
                    'price' => $bill->water_charge,
                    'quantity' => 1,
                ];
            }
            
            // Add accumulative admin fee
            $totalAdminFee = $bills->sum('admin_fee');
            if ($totalAdminFee > 0) {
                $correctedOrderItems[] = [
                    'name' => "Biaya Admin (Total)",
                    'price' => $totalAdminFee,
                    'quantity' => 1,
                ];
            }
            
            // Add accumulative maintenance fee
            $totalMaintenanceFee = $bills->sum('maintenance_fee');
            if ($totalMaintenanceFee > 0) {
                $correctedOrderItems[] = [
                    'name' => "Biaya Pemeliharaan (Total)",
                    'price' => $totalMaintenanceFee,
                    'quantity' => 1,
                ];
            }

            $paymentData = [
                'method' => 'QRIS',
                'merchant_ref' => $bundlePayment->bundle_reference,
                'amount' => $bundlePayment->total_amount,
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'order_items' => $correctedOrderItems,
                'return_url' => route('portal.bills', $customerCode),
                'expired_time' => (time() + (1 * 60 * 60)), // 1 hour
            ];

            $tripayResponse = $tripayService->createTransaction($village, $paymentData);

            if ($tripayResponse['success']) {
                // Update bundle payment with Tripay data
                $bundlePayment->update([
                    'payment_reference' => $tripayResponse['data']['reference'],
                    'tripay_data' => $tripayResponse['data'],
                    'expires_at' => now()->addHours(1),
                ]);

                DB::commit();

                // Check if it's an AJAX request
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'checkout_url' => $tripayResponse['data']['checkout_url']
                    ]);
                }

                // Redirect to Tripay checkout
                return redirect()->away($tripayResponse['data']['checkout_url']);
            } else {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal membuat pembayaran: ' . $tripayResponse['message']
                ], 400);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bundle payment direct processing failed', [
                'customer_code' => $customerCode,
                'bill_ids' => $request->bill_ids,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pembayaran. Silakan coba lagi.'
            ], 500);
        }
    }

    /**
     * Handle Tripay callback for bundle payments
     */
    public function handleCallback(Request $request, Village $village)
    {
        try {
            $tripayService = new \App\Services\TripayService($village);
            
            // Validate callback
            if (!$tripayService->validateCallback($village, $request)) {
                return response('Invalid callback', 400);
            }

            $callbackData = $request->all();
            $reference = $callbackData['reference'];
            $status = $callbackData['status'];

            // Find bundle payment by reference
            $bundlePayment = BundlePayment::where('payment_reference', $reference)->first();
            
            if (!$bundlePayment) {
                Log::warning('Bundle payment not found for callback', ['reference' => $reference]);
                return response('Bundle payment not found', 404);
            }

            // Update bundle payment status based on callback
            switch ($status) {
                case 'PAID':
                    if ($bundlePayment->status !== 'paid') {
                        $bundlePayment->markAsPaid();
                        Log::info('Bundle payment marked as paid via callback', [
                            'bundle_reference' => $bundlePayment->bundle_reference,
                            'reference' => $reference
                        ]);
                    }
                    break;

                case 'EXPIRED':
                case 'FAILED':
                    if ($bundlePayment->status === 'pending') {
                        $bundlePayment->markAsFailed();
                        
                        // Reset bill statuses back to unpaid/overdue
                        foreach ($bundlePayment->bills as $bill) {
                            $newStatus = $bill->is_overdue ? 'overdue' : 'unpaid';
                            $bill->update(['status' => $newStatus]);
                        }
                        
                        Log::info('Bundle payment marked as failed via callback', [
                            'bundle_reference' => $bundlePayment->bundle_reference,
                            'reference' => $reference,
                            'status' => $status
                        ]);
                    }
                    break;
            }

            return response('OK');

        } catch (\Exception $e) {
            Log::error('Bundle payment callback error', [
                'village' => $village->slug,
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response('Callback processing failed', 500);
        }
    }
}