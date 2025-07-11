<?php

namespace App\Services;

use App\Models\Variable;
use App\Models\Bill;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

class TripayService
{
    private $apiKey;
    private $privateKey;
    private $merchantCode;
    private $baseUrl;
    private $isProduction;
    private $village;

    public function __construct($village = null)
    {
        $this->village = $village;
        $this->loadConfiguration();
    }

    private function loadConfiguration()
    {
        // Get village-specific variables or use main config
        $variables = $this->village ?
            Variable::where('village_id', $this->village->id)->first() :
            Variable::where('village_id', null)->first();

        if (!$variables) {
            throw new \Exception('Tripay configuration not found');
        }

        $this->isProduction = $variables->tripay_is_production;

        // Set base URL
        $this->baseUrl = $this->isProduction
            ? 'https://tripay.co.id/api'
            : 'https://tripay.co.id/api-sandbox';

        // Set credentials
        if ($variables->tripay_use_main) {
            // Use main/global config
            if ($this->isProduction) {
                $this->apiKey = config('tripay.api_key');
                $this->privateKey = config('tripay.private_key');
                $this->merchantCode = config('tripay.merchant_code');
            } else {
                $this->apiKey = config('tripay.api_key_sb');
                $this->privateKey = config('tripay.private_key_sb');
                $this->merchantCode = config('tripay.merchant_code_sb');
            }
        } else {
            // Use village-specific encrypted credentials
            if ($this->isProduction) {
                $this->apiKey = Crypt::decryptString($variables->tripay_api_key_prod);
                $this->privateKey = Crypt::decryptString($variables->tripay_private_key_prod);
                $this->merchantCode = Crypt::decryptString($variables->tripay_merchant_code_prod);
            } else {
                $this->apiKey = Crypt::decryptString($variables->tripay_api_key_dev);
                $this->privateKey = Crypt::decryptString($variables->tripay_private_key_dev);
                $this->merchantCode = Crypt::decryptString($variables->tripay_merchant_code_dev);
            }
        }
    }

    /**
     * Create QRIS payment for bill
     */
    public function createPayment(Bill $bill, $customerData)
    {
        try {
            // Generate unique merchant reference
            $merchantRef = 'BILL-' . $bill->id . '-' . time();

            // Calculate timeout
            $timeoutMinutes = $this->getTimeoutMinutes();
            $timeout = Carbon::now()->addMinutes($timeoutMinutes)->timestamp;

            // Generate signature
            $signature = hash_hmac('sha256', $this->merchantCode . $merchantRef . $bill->amount, $this->privateKey);

            // Prepare order items
            $orderItems = [[
                "sku" => "BILL-{$bill->id}",
                "name" => $bill->description ?: "Pembayaran Tagihan",
                "price" => (int) $bill->amount,
                "quantity" => 1,
            ]];

            // Prepare payload
            $payload = [
                "method" => "QRIS",
                "merchant_ref" => $merchantRef,
                "amount" => (int) $bill->amount,
                "customer_name" => $customerData['name'],
                "customer_email" => $customerData['email'],
                "customer_phone" => $customerData['phone'] ?? '',
                "order_items" => $orderItems,
                "return_url" => route('tripay.return'),
                "expired_time" => $timeout,
                "signature" => $signature,
            ];

            Log::info('Creating Tripay payment for bill', [
                'bill_id' => $bill->id,
                'merchant_ref' => $merchantRef,
                'amount' => $bill->amount,
            ]);

            // Send request to Tripay
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->baseUrl . '/transaction/create', $payload);

            if (!$response->successful()) {
                $errorMessage = $response->json()['message'] ?? 'Unknown error';
                throw new \Exception("Tripay payment creation failed: " . $errorMessage);
            }

            $responseData = $response->json()['data'];

            // Update bill with Tripay reference
            $bill->update([
                'bill_ref' => $merchantRef,
                'status' => 'pending'
            ]);

            Log::info('Tripay payment created successfully', [
                'bill_id' => $bill->id,
                'merchant_ref' => $merchantRef,
                'tripay_reference' => $responseData['reference'],
            ]);

            return [
                'success' => true,
                'data' => $responseData,
                'merchant_ref' => $merchantRef,
                'timeout' => Carbon::createFromTimestamp($timeout),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create Tripay payment', [
                'bill_id' => $bill->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Check transaction status
     */
    public function checkTransactionStatus($reference)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get($this->baseUrl . '/transaction/detail', [
                'reference' => $reference,
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to check transaction status: ' . $response->body());
            }

            return $response->json()['data'];
        } catch (\Exception $e) {
            Log::error('Failed to check Tripay transaction status', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Validate callback signature
     */
    public function validateCallbackSignature($callbackData)
    {
        $callbackSignature = $callbackData['signature'] ?? '';

        $rawCallbackData = $callbackData;
        unset($rawCallbackData['signature']);

        ksort($rawCallbackData);
        $callbackString = http_build_query($rawCallbackData);
        $calculatedSignature = hash_hmac('sha256', $callbackString, $this->privateKey);

        return hash_equals($calculatedSignature, $callbackSignature);
    }

    /**
     * Process payment callback
     */
    public function processCallback($callbackData)
    {
        if (!$this->validateCallbackSignature($callbackData)) {
            throw new \Exception('Invalid callback signature');
        }

        $merchantRef = $callbackData['merchant_ref'];
        $status = $callbackData['status'];

        // Find bill by merchant reference
        $bill = Bill::where('bill_ref', $merchantRef)->first();
        if (!$bill) {
            throw new \Exception('Bill not found for reference: ' . $merchantRef);
        }

        // Update bill status based on payment status
        switch ($status) {
            case 'PAID':
                $bill->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                Log::info('Bill payment completed', [
                    'bill_id' => $bill->id,
                    'merchant_ref' => $merchantRef,
                ]);
                break;

            case 'UNPAID':
                $bill->update(['status' => 'pending']);
                break;

            case 'EXPIRED':
            case 'REFUND':
            case 'FAILED':
                $bill->update(['status' => 'failed']);

                Log::info('Bill payment failed/expired', [
                    'bill_id' => $bill->id,
                    'merchant_ref' => $merchantRef,
                    'status' => $status,
                ]);
                break;
        }

        return $bill;
    }

    /**
     * Get payment channels
     */
    public function getPaymentChannels()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get($this->baseUrl . '/merchant/payment-channel');

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch payment channels');
            }

            // Filter only QRIS channels
            $channels = $response->json()['data'];
            return collect($channels)->filter(function ($channel) {
                return $channel['code'] === 'QRIS' && $channel['active'];
            })->values()->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to fetch payment channels', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function getTimeoutMinutes()
    {
        $variables = $this->village ?
            Variable::where('village_id', $this->village->id)->first() :
            Variable::where('village_id', null)->first();

        return $variables->tripay_timeout_minutes ?? 15;
    }
}
