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
                return response()->json(['message' => 'Invalid signature'], 400);
            }

            $data = $request->json()->all();
            $reference = $data['reference'] ?? null;
            $status = $data['status'] ?? null;
            $event = $data['event'] ?? null;

            if (!$reference) {
                Log::error('Tripay callback missing reference', $data);
                return response()->json(['message' => 'Missing reference'], 400);
            }

            // Find payment by transaction reference
            $payment = Payment::where('transaction_ref', $reference)->first();
            
            if (!$payment) {
                Log::warning('Payment not found for Tripay callback', [
                    'reference' => $reference,
                    'status' => $status,
                    'event' => $event
                ]);
                return response()->json(['message' => 'Payment not found'], 404);
            }

            Log::info('Tripay callback received', [
                'payment_id' => $payment->payment_id,
                'reference' => $reference,
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
                            'reference' => $reference
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
                            'reference' => $reference,
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
                        'reference' => $reference,
                        'status' => $status
                    ]);
            }

            // Update payment with latest Tripay data
            $payment->update([
                'tripay_data' => array_merge($payment->tripay_data ?? [], $data)
            ]);

            return response()->json(['message' => 'Callback processed successfully']);

        } catch (\Exception $e) {
            Log::error('Error processing Tripay callback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['message' => 'Internal server error'], 500);
        }
    }
}