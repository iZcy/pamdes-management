<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TripayCallbackController extends Controller
{
    /**
     * Handle Tripay payment callback
     */
    public function handle(Request $request)
    {
        try {
            // Validate callback signature
            $signature = $request->header('X-Callback-Signature');
            $privateKey = config('tripay.private_key');
            
            $expectedSignature = hash_hmac('sha256', $request->getContent(), $privateKey);
            
            if ($signature !== $expectedSignature) {
                Log::warning('Invalid Tripay callback signature', [
                    'provided' => $signature,
                    'expected' => $expectedSignature
                ]);
                return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
            }

            $data = $request->json()->all();
            $tripayReference = $data['reference'] ?? null;  // Tripay's internal reference
            $merchantRef = $data['merchant_ref'] ?? null;   // Our merchant reference
            $status = $data['status'] ?? null;
            $event = $data['event'] ?? null;

            if (!$tripayReference && !$merchantRef) {
                Log::error('Tripay callback missing both reference and merchant_ref', $data);
                return response()->json(['success' => false, 'message' => 'Missing reference'], 400);
            }

            // Find payment by transaction reference - try both Tripay reference and merchant reference
            $payment = null;
            
            // First try to find by Tripay reference (for single payments)
            if ($tripayReference) {
                $payment = Payment::where('transaction_ref', $tripayReference)->first();
            }
            
            // If not found, try merchant reference (for bundle payments and some single payments)
            if (!$payment && $merchantRef) {
                $payment = Payment::where('transaction_ref', $merchantRef)->first();
            }
            
            if (!$payment) {
                Log::warning('Payment not found for Tripay callback', [
                    'tripay_reference' => $tripayReference,
                    'merchant_ref' => $merchantRef,
                    'status' => $status,
                    'event' => $event
                ]);
                return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
            }

            Log::info('Tripay callback received', [
                'payment_id' => $payment->payment_id,
                'tripay_reference' => $tripayReference,
                'merchant_ref' => $merchantRef,
                'status' => $status,
                'event' => $event,
                'current_payment_status' => $payment->status
            ]);

            // Handle payment status updates
            switch ($status) {
                case 'PAID':
                    if ($payment->isPending()) {
                        $payment->completeBillPayments();
                        Log::info('Payment completed via Tripay callback', [
                            'payment_id' => $payment->payment_id,
                            'tripay_reference' => $tripayReference,
                            'merchant_ref' => $merchantRef
                        ]);
                    }
                    break;

                case 'EXPIRED':
                case 'FAILED':
                case 'CANCELLED':
                    if ($payment->isPending()) {
                        $payment->expirePayment();
                        Log::info('Payment expired/failed via Tripay callback', [
                            'payment_id' => $payment->payment_id,
                            'tripay_reference' => $tripayReference,
                            'merchant_ref' => $merchantRef,
                            'status' => $status
                        ]);
                    }
                    break;

                case 'PENDING':
                    // Payment is still pending, no action needed
                    break;

                default:
                    Log::warning('Unknown payment status in Tripay callback', [
                        'payment_id' => $payment->payment_id,
                        'tripay_reference' => $tripayReference,
                        'merchant_ref' => $merchantRef,
                        'status' => $status
                    ]);
            }

            // Update payment with latest Tripay data
            $payment->update([
                'tripay_data' => array_merge($payment->tripay_data ?? [], $data)
            ]);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Error processing Tripay callback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['success' => false, 'message' => 'Internal server error'], 500);
        }
    }
}