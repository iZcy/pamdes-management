<?php
// app/Services/TripayService.php - Complete and fixed implementation

namespace App\Services;

use App\Models\Variable;
use App\Models\Bill;
use App\Models\Village;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TripayService
{
    private $apiKey;
    private $privateKey;
    private $merchantCode;
    private $baseUrl;
    private $isProduction;
    private $village;
    private $timeoutMinutes;

    public function __construct($village = null)
    {
        $this->village = $village;
        $this->loadConfiguration();
    }

    private function loadConfiguration()
    {
        // Get village-specific variables
        if ($this->village) {
            $variables = Variable::where('village_id', $this->village->id)->first();
        } else {
            // Fallback to current village context
            $villageId = config('pamdes.current_village_id');
            $variables = $villageId ? Variable::where('village_id', $villageId)->first() : null;
        }

        if (!$variables) {
            throw new \Exception('Tripay configuration not found for this village');
        }

        $this->isProduction = $variables->tripay_is_production;
        $this->timeoutMinutes = $variables->tripay_timeout_minutes ?? 15;

        // Set base URL
        $this->baseUrl = $this->isProduction
            ? 'https://tripay.co.id/api'
            : 'https://tripay.co.id/api-sandbox';

        // Set credentials
        if ($variables->tripay_use_main) {
            // Use main/global config from environment
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
            // Use village-specific credentials (already decrypted by Variable model)
            if ($this->isProduction) {
                $this->apiKey = $variables->tripay_api_key_prod;
                $this->privateKey = $variables->tripay_private_key_prod;
                $this->merchantCode = $variables->tripay_merchant_code_prod;
            } else {
                $this->apiKey = $variables->tripay_api_key_dev;
                $this->privateKey = $variables->tripay_private_key_dev;
                $this->merchantCode = $variables->tripay_merchant_code_dev;
            }
        }

        // Validate that we have all required credentials
        if (!$this->apiKey || !$this->privateKey || !$this->merchantCode) {
            throw new \Exception('Incomplete Tripay configuration. Please check API key, private key, and merchant code.');
        }
    }

    /**
     * Create QRIS payment for bill
     */
    public function createPayment(Bill $bill, array $customerData)
    {
        try {
            // Generate unique merchant reference
            $village = Village::find($bill->waterUsage->customer->village_id);
            if (!$village) {
                throw new \Exception('Village not found for this bill');
            }
            $villageSlug = $village->slug;
            $villageCode = strtoupper($villageSlug); // Use village slug as code
            $yearMonth = $bill->waterUsage->billingPeriod->created_at->format('Ym'); // e.g., "202401" for January 2024
            $merchantRef = 'PAMDES-' . $villageCode . '-' . $yearMonth . '-' . $bill->bill_id . '-' . time();

            // Calculate timeout
            $timeout = Carbon::now()->addMinutes($this->timeoutMinutes)->timestamp;

            // Prepare order items
            $orderItems = [[
                "sku" => "BILL-{$bill->bill_id}",
                "name" => "Pembayaran Tagihan Air " . ($bill->waterUsage->billingPeriod->period_name ?? 'Bulan Ini'),
                "price" => (int) $bill->total_amount,
                "quantity" => 1,
            ]];

            // Generate signature
            $signature = hash_hmac('sha256', $this->merchantCode . $merchantRef . (int) $bill->total_amount, $this->privateKey);

            // Prepare payload
            $payload = [
                "method" => "QRIS",
                "merchant_ref" => $merchantRef,
                "amount" => (int) $bill->total_amount,
                "customer_name" => $customerData['name'],
                "customer_email" => $customerData['email'],
                "customer_phone" => $customerData['phone'] ?? '',
                "order_items" => $orderItems,
                "return_url" => route('tripay.return'),
                "expired_time" => $timeout,
                "signature" => $signature,
            ];

            Log::info('Creating Tripay payment for bill', [
                'bill_id' => $bill->bill_id,
                'merchant_ref' => $merchantRef,
                'amount' => $bill->total_amount,
                'village_id' => $this->village?->id,
                'is_production' => $this->isProduction,
            ]);

            // Send request to Tripay
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->baseUrl . '/transaction/create', $payload);

            if (!$response->successful()) {
                $errorMessage = $response->json()['message'] ?? 'Unknown error';
                Log::error('Tripay payment creation failed', [
                    'error' => $errorMessage,
                    'response' => $response->body(),
                    'status' => $response->status(),
                ]);
                throw new \Exception("Tripay payment creation failed: " . $errorMessage);
            }

            $responseData = $response->json()['data'];

            // Update bill with Tripay reference
            $bill->update([
                'bill_ref' => $merchantRef,
                'status' => 'pending'
            ]);

            Log::info('Tripay payment created successfully', [
                'bill_id' => $bill->bill_id,
                'merchant_ref' => $merchantRef,
                'tripay_reference' => $responseData['reference'],
                'checkout_url' => $responseData['checkout_url'],
            ]);

            return [
                'success' => true,
                'data' => $responseData,
                'merchant_ref' => $merchantRef,
                'timeout' => Carbon::createFromTimestamp($timeout),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create Tripay payment', [
                'bill_id' => $bill->bill_id,
                'error' => $e->getMessage(),
                'village_id' => $this->village?->id,
            ]);
            throw $e;
        }
    }

    /**
     * Continue payment /checkout/bill_ref
     */
    public function continuePayment($reference)
    {
        if (!$reference) {
            throw new \Exception('Payment reference is required to continue payment');
        }

        // clear /api from baseUrl into ''
        $cleanUrl = rtrim($this->baseUrl, '/api');

        return [
            'success' => true,
            'checkout_url' => $cleanUrl . "/checkout/{$reference}",
            'message' => 'Redirecting to Tripay payment page',
        ];
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
                    'payment_date' => now(),
                ]);

                // Create payment record
                $bill->payments()->create([
                    'payment_date' => now(),
                    'amount_paid' => $bill->total_amount,
                    'change_given' => 0,
                    'payment_method' => 'qris',
                    'payment_reference' => $callbackData['reference'] ?? null,
                    'collector_id' => null, // System payment
                    'notes' => 'Pembayaran QRIS melalui Tripay',
                ]);

                Log::info('Bill payment completed via Tripay', [
                    'bill_id' => $bill->bill_id,
                    'merchant_ref' => $merchantRef,
                    'amount' => $bill->total_amount,
                ]);
                break;

            case 'UNPAID':
                $bill->update(['status' => 'unpaid']);
                break;

            case 'EXPIRED':
            case 'REFUND':
            case 'FAILED':
                $bill->update(['status' => 'unpaid', 'bill_ref' => null]);

                Log::info('Bill payment failed/expired via Tripay', [
                    'bill_id' => $bill->bill_id,
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

    /**
     * Test connection to Tripay
     */
    public function testConnection()
    {
        try {
            $channels = $this->getPaymentChannels();
            return [
                'success' => true,
                'message' => 'Connection successful',
                'channels_count' => count($channels),
                'is_production' => $this->isProduction,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'is_production' => $this->isProduction,
            ];
        }
    }
}
