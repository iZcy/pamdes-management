<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Customer;
use App\Models\WaterUsage;
use App\Models\BillingPeriod;
use App\Services\EnhancedOCRService;
use thiagoalessio\TesseractOCR\TesseractOCR;

class MeterReadingController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        // Get current village context for operator
        $villageId = $user->getCurrentVillageContext();
        
        if (!$villageId) {
            abort(403, 'No village access found for operator');
        }

        // Get active billing period
        $activePeriod = BillingPeriod::where('village_id', $villageId)
            ->where('status', 'active')
            ->first();

        if (!$activePeriod) {
            return view('meter.read', [
                'error' => 'No active billing period found. Please contact administrator.',
                'customers' => collect([]),
                'activePeriod' => null
            ]);
        }

        // Get customers that need meter reading for current period
        $customers = Customer::where('village_id', $villageId)
            ->where('status', 'active')
            ->whereDoesntHave('waterUsages', function ($query) use ($activePeriod) {
                $query->where('period_id', $activePeriod->period_id);
            })
            ->orderBy('customer_code')
            ->get();

        return view('meter.read', compact('customers', 'activePeriod'));
    }

    public function submit(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,customer_id',
            'current_reading' => 'required|numeric|min:0',
            'meter_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
            'notes' => 'nullable|string|max:500'
        ]);

        $user = Auth::user();
        $villageId = $user->getCurrentVillageContext();

        // Get customer and verify they belong to operator's village
        $customer = Customer::where('customer_id', $request->customer_id)
            ->where('village_id', $villageId)
            ->firstOrFail();

        // Get active billing period
        $activePeriod = BillingPeriod::where('village_id', $villageId)
            ->where('status', 'active')
            ->firstOrFail();

        // Check if reading already exists for this period
        $existingReading = WaterUsage::where('customer_id', $customer->customer_id)
            ->where('period_id', $activePeriod->period_id)
            ->first();

        if ($existingReading) {
            return response()->json([
                'success' => false,
                'message' => 'Meter reading already exists for this customer in current period'
            ], 400);
        }

        // Get previous reading for validation
        $previousReading = WaterUsage::where('customer_id', $customer->customer_id)
            ->orderBy('usage_date', 'desc')
            ->first();

        $currentReading = (float) $request->current_reading;
        $previousValue = $previousReading ? $previousReading->final_meter : 0;

        // Validate that current reading is not less than previous
        if ($currentReading < $previousValue) {
            return response()->json([
                'success' => false,
                'message' => "Current reading ({$currentReading}) cannot be less than previous reading ({$previousValue})"
            ], 400);
        }

        try {
            // Calculate usage
            $usage = $currentReading - $previousValue;

            // Create water usage record
            $waterUsage = WaterUsage::create([
                'customer_id' => $customer->customer_id,
                'period_id' => $activePeriod->period_id,
                'initial_meter' => $previousValue,
                'final_meter' => $currentReading,
                'total_usage_m3' => $usage,
                'usage_date' => now(),
                'reader_id' => $user->id,
                'notes' => $request->notes
            ]);

            Log::info('Meter reading submitted', [
                'customer_code' => $customer->customer_code,
                'usage_id' => $waterUsage->usage_id,
                'final_meter' => $currentReading,
                'total_usage_m3' => $usage,
                'reader_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Meter reading submitted successfully',
                'data' => [
                    'customer_code' => $customer->customer_code,
                    'customer_name' => $customer->name,
                    'previous_reading' => $previousValue,
                    'current_reading' => $currentReading,
                    'usage' => $usage,
                    'reading_date' => $waterUsage->usage_date->format('d/m/Y H:i')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error submitting meter reading', [
                'customer_id' => $customer->customer_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error submitting meter reading: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verifyCustomer(Request $request)
    {
        $request->validate([
            'customer_code' => 'required|string|max:20'
        ]);

        $user = Auth::user();
        $villageId = $user->getCurrentVillageContext();

        if (!$villageId) {
            return response()->json([
                'success' => false,
                'message' => 'No village access found for operator'
            ], 403);
        }

        // Find customer by code in operator's village
        $customer = Customer::where('customer_code', $request->customer_code)
            ->where('village_id', $villageId)
            ->where('status', 'active')
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found or inactive'
            ], 404);
        }

        // Get active billing period to check if reading already exists
        $activePeriod = BillingPeriod::where('village_id', $villageId)
            ->where('status', 'active')
            ->first();

        if ($activePeriod) {
            $existingReading = WaterUsage::where('customer_id', $customer->customer_id)
                ->where('period_id', $activePeriod->period_id)
                ->first();

            if ($existingReading) {
                return response()->json([
                    'success' => false,
                    'message' => 'Meter reading already exists for this customer in current period'
                ], 400);
            }
        }

        // Get previous usage data
        $previousUsage = WaterUsage::where('customer_id', $customer->customer_id)
            ->with('reader:id,name')
            ->orderBy('usage_date', 'desc')
            ->first();

        $previousUsageData = null;
        if ($previousUsage) {
            $previousUsageData = [
                'usage_date' => $previousUsage->usage_date->format('d/m/Y'),
                'final_meter' => $previousUsage->final_meter,
                'total_usage_m3' => $previousUsage->total_usage_m3,
                'reader_name' => $previousUsage->reader->name ?? 'Unknown'
            ];
        }

        return response()->json([
            'success' => true,
            'customer' => [
                'customer_id' => $customer->customer_id,
                'customer_code' => $customer->customer_code,
                'name' => $customer->name,
                'address' => $customer->address,
                'rt' => $customer->rt,
                'rw' => $customer->rw
            ],
            'previous_usage' => $previousUsageData
        ]);
    }

    public function processOCR(Request $request)
    {
        Log::info('OCR processOCR called', [
            'has_image' => $request->hasFile('image'),
            'request_files' => array_keys($request->allFiles()),
            'request_data' => $request->except(['image', '_token'])
        ]);

        $request->validate([
            'image' => 'required|file|max:5120'
        ]);

        try {
            $image = $request->file('image');
            
            if (!$image || !$image->isValid()) {
                throw new \Exception("Invalid or missing image file");
            }
            
            // Ensure temp directory exists with proper permissions
            $disk = Storage::disk('local');
            $tempDir = $disk->path('temp');
            if (!file_exists($tempDir)) {
                if (!mkdir($tempDir, 0777, true)) {
                    throw new \Exception("Failed to create temp directory: " . $tempDir);
                }
                // Ensure proper permissions after creation
                chmod($tempDir, 0777);
            }
            
            // Ensure directory is writable
            if (!is_writable($tempDir)) {
                chmod($tempDir, 0777);
                if (!is_writable($tempDir)) {
                    throw new \Exception("Temp directory is not writable: " . $tempDir);
                }
            }
            
            // Store the uploaded file with detailed debugging
            Log::info('Attempting to store file', [
                'image_valid' => $image->isValid(),
                'image_size' => $image->getSize(),
                'image_mime' => $image->getMimeType(),
                'image_name' => $image->getClientOriginalName(),
                'temp_dir' => $tempDir,
                'temp_dir_exists' => file_exists($tempDir),
                'temp_dir_writable' => is_writable($tempDir)
            ]);
            
            try {
                $tempPath = $image->store('temp', 'local');
                Log::info('File store attempt result', ['temp_path' => $tempPath]);
            } catch (\Exception $storeException) {
                Log::error('File store exception', [
                    'error' => $storeException->getMessage(),
                    'trace' => $storeException->getTraceAsString()
                ]);
                throw new \Exception("Failed to store uploaded file: " . $storeException->getMessage());
            }
            
            if (!$tempPath) {
                throw new \Exception("Failed to store uploaded file - store() returned false/null");
            }
            
            $fullPath = $disk->path($tempPath);
            
            // Verify file exists and is readable
            if (!file_exists($fullPath)) {
                throw new \Exception("Uploaded file not found at: " . $fullPath);
            }
            
            if (!is_readable($fullPath)) {
                throw new \Exception("Uploaded file is not readable: " . $fullPath);
            }
            
            Log::info('OCR file processing', [
                'original_name' => $image->getClientOriginalName(),
                'temp_path' => $tempPath,
                'full_path' => $fullPath,
                'file_size' => filesize($fullPath)
            ]);

            // Check if this is a single digit request (from digit grid)
            $boxId = $request->input('box_id', '');
            $boxName = $request->input('box_name', '');
            
            if (strpos($boxId, 'digit-') === 0) {
                // Handle single digit OCR
                $ocrResult = $this->processSingleDigit($fullPath, $boxId, $boxName);
            } else {
                // Use Enhanced OCR Service for full images
                $enhancedOCR = new EnhancedOCRService();
                $ocrResult = $enhancedOCR->processImage($fullPath);
            }
            
            // Clean up temporary file
            Storage::disk('local')->delete($tempPath);

            if (!$ocrResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $ocrResult['message'] ?? 'OCR processing failed'
                ], 500);
            }

            Log::info('Enhanced OCR Processing', [
                'method' => $ocrResult['method'],
                'confidence' => $ocrResult['confidence'],
                'raw_text' => $ocrResult['raw_text'],
                'processing_info' => $ocrResult['processing_info'] ?? []
            ]);

            // Generate suggestions from numbers if structured data is not complete (only for full OCR)
            $suggestions = [];
            if (!empty($ocrResult['numbers'])) {
                $suggestions = $this->suggestReadings($ocrResult['numbers']);
            }

            // Build response based on OCR type
            $response = [
                'success' => true,
                'method' => $ocrResult['method'],
                'confidence' => $ocrResult['confidence'],
                'raw_text' => $ocrResult['raw_text'] ?? '',
                'structured_data' => $ocrResult['structured_data'] ?? null,
                'processing_info' => $ocrResult['processing_info'] ?? []
            ];

            // Add numbers and suggestions only for full OCR (not single digits)
            if (isset($ocrResult['numbers'])) {
                $response['numbers'] = $ocrResult['numbers'];
                $response['suggestions'] = $suggestions;
            }

            // Add single digit specific fields if present
            if (isset($ocrResult['extractedText'])) {
                $response['extractedText'] = $ocrResult['extractedText'];
            }
            if (isset($ocrResult['box_id'])) {
                $response['box_id'] = $ocrResult['box_id'];
                $response['box_name'] = $ocrResult['box_name'];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            // Clean up temporary file if it exists
            if (isset($tempPath)) {
                Storage::disk('local')->delete($tempPath);
            }

            Log::error('OCR Processing Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing image: ' . $e->getMessage()
            ], 500);
        }
    }

    private function extractNumbers($text)
    {
        // Remove all non-numeric characters and extract potential readings
        $cleanText = preg_replace('/[^0-9.]/', '', $text);
        
        // Find all sequences of digits
        preg_match_all('/\d+/', $cleanText, $matches);
        
        $numbers = [];
        foreach ($matches[0] as $match) {
            if (strlen($match) >= 1) { // At least 1 digit
                $numbers[] = (int) $match;
            }
        }

        // Remove duplicates and sort
        $numbers = array_unique($numbers);
        sort($numbers);

        return array_values($numbers);
    }

    private function suggestReadings($numbers)
    {
        $suggestions = [];
        
        foreach ($numbers as $number) {
            // Filter reasonable meter readings (between 0 and 999999)
            if ($number >= 0 && $number <= 999999) {
                $suggestions[] = [
                    'value' => $number,
                    'formatted' => number_format($number),
                    'confidence' => $this->calculateConfidence($number)
                ];
            }
        }

        // Sort by confidence (higher first)
        usort($suggestions, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        return array_slice($suggestions, 0, 3); // Return top 3 suggestions
    }

    private function calculateConfidence($number)
    {
        $confidence = 60; // Base confidence (increased from 50)
        
        // Higher confidence for numbers with reasonable meter reading patterns
        $digits = strlen((string) $number);
        
        // Water meters typically show 3-6 digits
        if ($digits >= 3 && $digits <= 6) {
            $confidence += 25; // Reasonable meter reading length
        } elseif ($digits >= 1 && $digits <= 2) {
            $confidence += 10; // Small readings are possible but less common
        } else {
            $confidence -= 20; // Very long or very short numbers are less likely
        }
        
        if ($number > 0) {
            $confidence += 10; // Non-zero readings are more likely
        }

        // Numbers that look like sequential readings get higher confidence
        if ($number % 10 == 0 || $number % 5 == 0) {
            $confidence += 5; // Round numbers are common in meter readings
        }
        
        // Boost confidence for numbers that look like typical water meter readings
        if ($number >= 100 && $number <= 999999) {
            $confidence += 10; // Typical water meter range
        }
        
        // Penalize extremely large numbers
        if ($number > 999999) {
            $confidence -= 30; // Very large numbers are unlikely
        }

        return min(100, max(0, $confidence));
    }

    private function extractStructuredMeterData($text, $numbers)
    {
        $structuredData = [
            'full_value' => null,
            'normal_value' => null, // Black digits (main reading)
            'precise_value' => null, // Red digits (decimal part)
            'customer_id' => null,
            'confidence' => 0
        ];

        // Fast pattern matching for ONDA meter format: 0853134
        // Look for exactly 7-digit sequence first (most common)
        if (preg_match('/0(\d{6})/', $text, $matches)) {
            $fullValue = '0' . $matches[1]; // 0853134
            $structuredData['full_value'] = $fullValue;
            $structuredData['normal_value'] = substr($fullValue, 0, 4); // 0853
            $structuredData['precise_value'] = substr($fullValue, 4, 3); // 134
            $structuredData['confidence'] = 85;
        }
        // Alternative: look for any 7-digit sequence
        elseif (preg_match('/(\d{7})/', $text, $matches)) {
            $fullValue = $matches[1];
            $structuredData['full_value'] = $fullValue;
            $structuredData['normal_value'] = substr($fullValue, 0, 4);
            $structuredData['precise_value'] = substr($fullValue, 4, 3);
            $structuredData['confidence'] = 75;
        }

        // Fast customer ID extraction: look for 8-digit sequence (21233920)
        if (preg_match('/(\d{8})/', $text, $matches)) {
            $possibleId = $matches[1];
            // Make sure it's different from the meter reading
            if ($possibleId !== ($structuredData['full_value'] ?? '')) {
                $structuredData['customer_id'] = $possibleId;
                $structuredData['confidence'] += 10;
            }
        }

        // Quick fallback using extracted numbers
        if (!$structuredData['full_value'] && !empty($numbers)) {
            // Find 7-digit number for meter reading
            foreach ($numbers as $num) {
                if (strlen((string)$num) === 7) {
                    $structuredData['full_value'] = (string) $num;
                    $structuredData['normal_value'] = substr((string) $num, 0, 4);
                    $structuredData['precise_value'] = substr((string) $num, 4, 3);
                    $structuredData['confidence'] = 65;
                    break;
                }
            }

            // Find 8-digit number for customer ID
            if (!$structuredData['customer_id']) {
                foreach ($numbers as $num) {
                    if (strlen((string)$num) === 8) {
                        $structuredData['customer_id'] = (string) $num;
                        $structuredData['confidence'] += 10;
                        break;
                    }
                }
            }
        }

        // Format the reading as decimal
        if ($structuredData['normal_value'] && $structuredData['precise_value']) {
            $normalPart = ltrim($structuredData['normal_value'], '0') ?: '0';
            $structuredData['formatted_reading'] = $normalPart . '.' . $structuredData['precise_value'];
        }

        return $structuredData;
    }

    /**
     * Process single digit OCR (optimized for individual digits from grid)
     */
    private function processSingleDigit(string $imagePath, string $boxId, string $boxName): array
    {
        try {
            Log::info('Processing single digit', [
                'box_id' => $boxId,
                'box_name' => $boxName,
                'image_path' => $imagePath
            ]);

            // Use Tesseract optimized for single digits
            $ocr = new TesseractOCR($imagePath);
            $ocr->lang('eng')
                ->psm(10)  // PSM 10: Treat image as single character
                ->oem(3)   // Default engine
                ->allowlist('0123456789') // Only digits
                ->configFile('digits');  // Use digits config

            $text = trim($ocr->run());
            
            // Clean the result - should be a single digit
            $cleanText = preg_replace('/[^0-9]/', '', $text);
            
            // Validate single digit
            if (strlen($cleanText) === 1 && ctype_digit($cleanText)) {
                $confidence = 90; // High confidence for clean single digit
                
                Log::info('Single digit OCR success', [
                    'box_id' => $boxId,
                    'raw_text' => $text,
                    'clean_digit' => $cleanText,
                    'confidence' => $confidence
                ]);
                
                return [
                    'success' => true,
                    'method' => 'single-digit',
                    'confidence' => $confidence,
                    'raw_text' => $text,
                    'extractedText' => $cleanText,
                    'box_id' => $boxId,
                    'box_name' => $boxName,
                    'structured_data' => [
                        'digit' => $cleanText,
                        'confidence' => $confidence
                    ]
                ];
            } else {
                // Try alternative approaches for unclear digits
                return $this->tryAlternativeDigitRecognition($imagePath, $boxId, $boxName, $text);
            }

        } catch (\Exception $e) {
            Log::error('Single digit OCR failed', [
                'box_id' => $boxId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'method' => 'single-digit',
                'message' => 'Single digit OCR failed: ' . $e->getMessage(),
                'box_id' => $boxId,
                'box_name' => $boxName
            ];
        }
    }

    /**
     * Try alternative recognition methods for unclear single digits
     */
    private function tryAlternativeDigitRecognition(string $imagePath, string $boxId, string $boxName, string $originalText): array
    {
        try {
            // Try different PSM modes for single digit
            $psmModes = [
                8 => 'single word',
                7 => 'single text line', 
                6 => 'uniform block'
            ];

            foreach ($psmModes as $psm => $description) {
                $ocr = new TesseractOCR($imagePath);
                $ocr->lang('eng')
                    ->psm($psm)
                    ->oem(3)
                    ->allowlist('0123456789');

                $text = trim($ocr->run());
                $cleanText = preg_replace('/[^0-9]/', '', $text);
                
                // Take the first digit if multiple found
                if (strlen($cleanText) >= 1) {
                    $digit = substr($cleanText, 0, 1);
                    $confidence = 70 - ($psm * 5); // Lower confidence for fallback methods
                    
                    Log::info('Alternative digit recognition success', [
                        'box_id' => $boxId,
                        'psm_mode' => $psm,
                        'description' => $description,
                        'raw_text' => $text,
                        'extracted_digit' => $digit,
                        'confidence' => $confidence
                    ]);
                    
                    return [
                        'success' => true,
                        'method' => 'single-digit-alt-psm' . $psm,
                        'confidence' => $confidence,
                        'raw_text' => $text,
                        'extractedText' => $digit,
                        'box_id' => $boxId,
                        'box_name' => $boxName,
                        'structured_data' => [
                            'digit' => $digit,
                            'confidence' => $confidence
                        ]
                    ];
                }
            }

            // Final fallback - return failure
            Log::warning('All digit recognition methods failed', [
                'box_id' => $boxId,
                'original_text' => $originalText
            ]);

            return [
                'success' => false,
                'method' => 'single-digit-failed',
                'message' => 'Could not recognize digit clearly',
                'raw_text' => $originalText,
                'box_id' => $boxId,
                'box_name' => $boxName,
                'confidence' => 0
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'method' => 'single-digit-error',
                'message' => 'Alternative digit recognition failed: ' . $e->getMessage(),
                'box_id' => $boxId,
                'box_name' => $boxName
            ];
        }
    }
}