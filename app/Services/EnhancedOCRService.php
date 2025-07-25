<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use thiagoalessio\TesseractOCR\TesseractOCR;

class EnhancedOCRService
{
    private $imageManager;
    
    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Process image with multiple OCR approaches for better accuracy
     */
    public function processImage(string $imagePath): array
    {
        $startTime = microtime(true);
        
        try {
            // Step 1: Pre-process image for better OCR
            $processedImages = $this->preprocessImage($imagePath);
            
            // Step 2: Try multiple OCR approaches
            $results = [];
            
            // Approach 1: Direct Tesseract (fastest)
            $directResult = $this->directTesseractOCR($processedImages['original']);
            if ($this->hasValidMeterData($directResult)) {
                $results[] = ['method' => 'direct', 'data' => $directResult, 'confidence' => 85];
            }
            
            // Approach 2: Enhanced contrast image
            $contrastResult = $this->directTesseractOCR($processedImages['contrast']);
            if ($this->hasValidMeterData($contrastResult)) {
                $results[] = ['method' => 'contrast', 'data' => $contrastResult, 'confidence' => 90];
            }
            
            // Approach 3: Binary threshold image (best for meter displays)
            $binaryResult = $this->directTesseractOCR($processedImages['binary']);
            if ($this->hasValidMeterData($binaryResult)) {
                $results[] = ['method' => 'binary', 'data' => $binaryResult, 'confidence' => 95];
            }
            
            // Clean up processed images
            $this->cleanupTempImages($processedImages);
            
            // Choose best result
            $bestResult = $this->chooseBestResult($results);
            
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::info('Enhanced OCR completed', [
                'processing_time_ms' => $processingTime,
                'methods_tried' => count($results),
                'best_method' => $bestResult['method'] ?? 'none',
                'confidence' => $bestResult['confidence'] ?? 0
            ]);
            
            return $bestResult;
            
        } catch (\Exception $e) {
            Log::error('Enhanced OCR failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Fallback to simple OCR
            return $this->fallbackSimpleOCR($imagePath);
        }
    }

    /**
     * Preprocess image with multiple enhancement techniques
     */
    private function preprocessImage(string $imagePath): array
    {
        $originalPath = $imagePath;
        
        try {
            $image = $this->imageManager->read($imagePath);
            
            // Create different processed versions
            $processedImages = [
                'original' => $originalPath
            ];
            
            // Enhanced contrast version
            $contrastImage = clone $image;
            $contrastImage->contrast(30)->brightness(10);
            $contrastPath = $this->saveTempImage($contrastImage, 'contrast');
            $processedImages['contrast'] = $contrastPath;
            
            // Binary threshold version (excellent for meter displays)
            $binaryImage = clone $image;
            $binaryImage->greyscale()->contrast(50)->brightness(-10);
            $binaryPath = $this->saveTempImage($binaryImage, 'binary');
            $processedImages['binary'] = $binaryPath;
            
            return $processedImages;
            
        } catch (\Exception $e) {
            Log::warning('Image preprocessing failed, using original', [
                'error' => $e->getMessage()
            ]);
            
            return ['original' => $originalPath];
        }
    }

    /**
     * Fast direct Tesseract OCR
     */
    private function directTesseractOCR(string $imagePath): array
    {
        try {
            $ocr = new TesseractOCR($imagePath);
            $ocr->lang('eng')
                ->psm(6)  // Uniform block - best for meter displays
                ->oem(3)  // Default engine
                ->allowlist(range(0, 9)); // Only digits for speed
            
            $text = trim($ocr->run());
            
            return $this->extractMeterDataFromText($text);
            
        } catch (\Exception $e) {
            Log::warning('Direct Tesseract failed', [
                'error' => $e->getMessage(),
                'image' => basename($imagePath)
            ]);
            
            return ['text' => '', 'numbers' => []];
        }
    }

    /**
     * Extract meter-specific data from OCR text
     */
    private function extractMeterDataFromText(string $text): array
    {
        // Extract all numbers
        preg_match_all('/\d+/', $text, $matches);
        $numbers = array_map('intval', $matches[0]);
        
        $result = [
            'text' => $text,
            'numbers' => $numbers,
            'meter_reading' => null,
            'customer_id' => null,
            'structured_data' => null
        ];
        
        // Fast pattern matching for ONDA meters
        $structured = $this->fastStructuredExtraction($text, $numbers);
        if ($structured) {
            $result['structured_data'] = $structured;
            $result['meter_reading'] = $structured['formatted_reading'] ?? null;
            $result['customer_id'] = $structured['customer_id'] ?? null;
        }
        
        return $result;
    }

    /**
     * Ultra-fast structured data extraction optimized for ONDA meters
     */
    private function fastStructuredExtraction(string $text, array $numbers): ?array
    {
        $structured = [
            'full_value' => null,
            'normal_value' => null,
            'precise_value' => null,
            'customer_id' => null,
            'formatted_reading' => null,
            'confidence' => 0
        ];

        // Method 1: Look for 7-digit sequence starting with 0 (most common ONDA pattern)
        if (preg_match('/0(\d{6})/', $text, $matches)) {
            $fullValue = '0' . $matches[1];
            $structured['full_value'] = $fullValue;
            $structured['normal_value'] = substr($fullValue, 0, 4);
            $structured['precise_value'] = substr($fullValue, 4, 3);
            $structured['confidence'] = 90;
        }
        // Method 2: Any 7-digit sequence
        elseif (preg_match('/(\d{7})/', $text, $matches)) {
            $fullValue = $matches[1];
            $structured['full_value'] = $fullValue;
            $structured['normal_value'] = substr($fullValue, 0, 4);
            $structured['precise_value'] = substr($fullValue, 4, 3);
            $structured['confidence'] = 80;
        }
        // Method 3: Use numbers array
        else {
            $sevenDigitNumbers = array_filter($numbers, fn($n) => strlen((string)$n) === 7);
            if (!empty($sevenDigitNumbers)) {
                $fullValue = (string) reset($sevenDigitNumbers);
                $structured['full_value'] = $fullValue;
                $structured['normal_value'] = substr($fullValue, 0, 4);
                $structured['precise_value'] = substr($fullValue, 4, 3);
                $structured['confidence'] = 70;
            }
        }

        // Extract customer ID (8-digit)
        $eightDigitNumbers = array_filter($numbers, fn($n) => strlen((string)$n) === 8);
        if (!empty($eightDigitNumbers)) {
            $structured['customer_id'] = (string) reset($eightDigitNumbers);
            $structured['confidence'] += 10;
        }

        // Create formatted reading
        if ($structured['normal_value'] && $structured['precise_value']) {
            $normalPart = ltrim($structured['normal_value'], '0') ?: '0';
            $structured['formatted_reading'] = $normalPart . '.' . $structured['precise_value'];
        }

        return $structured['confidence'] > 0 ? $structured : null;
    }

    /**
     * Check if OCR result contains valid meter data
     */
    private function hasValidMeterData(array $result): bool
    {
        return !empty($result['structured_data']) && 
               ($result['structured_data']['full_value'] !== null || 
                $result['structured_data']['customer_id'] !== null);
    }

    /**
     * Choose the best result from multiple OCR attempts
     */
    private function chooseBestResult(array $results): array
    {
        if (empty($results)) {
            return ['success' => false, 'message' => 'No valid OCR results'];
        }

        // Sort by confidence, then by method preference
        usort($results, function($a, $b) {
            if ($a['confidence'] === $b['confidence']) {
                // Prefer binary > contrast > direct
                $methodOrder = ['binary' => 3, 'contrast' => 2, 'direct' => 1];
                return ($methodOrder[$b['method']] ?? 0) <=> ($methodOrder[$a['method']] ?? 0);
            }
            return $b['confidence'] <=> $a['confidence'];
        });

        $best = $results[0];
        
        return [
            'success' => true,
            'method' => $best['method'],
            'confidence' => $best['confidence'],
            'raw_text' => $best['data']['text'] ?? '',
            'numbers' => $best['data']['numbers'] ?? [],
            'structured_data' => $best['data']['structured_data'] ?? null,
            'processing_info' => [
                'methods_tried' => count($results),
                'all_confidences' => array_column($results, 'confidence')
            ]
        ];
    }

    /**
     * Fallback to simple OCR if enhanced fails
     */
    private function fallbackSimpleOCR(string $imagePath): array
    {
        try {
            $ocr = new TesseractOCR($imagePath);
            $text = trim($ocr->lang('eng')->psm(6)->run());
            
            preg_match_all('/\d+/', $text, $matches);
            $numbers = array_map('intval', $matches[0]);
            
            return [
                'success' => true,
                'method' => 'fallback',
                'confidence' => 50,
                'raw_text' => $text,
                'numbers' => $numbers,
                'structured_data' => $this->fastStructuredExtraction($text, $numbers)
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'All OCR methods failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Save processed image temporarily
     */
    private function saveTempImage($image, string $suffix): string
    {
        $tempPath = storage_path('app/temp/ocr_' . $suffix . '_' . uniqid() . '.jpg');
        
        // Ensure directory exists
        $dir = dirname($tempPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $image->save($tempPath, quality: 95);
        
        return $tempPath;
    }

    /**
     * Clean up temporary processed images
     */
    private function cleanupTempImages(array $processedImages): void
    {
        foreach ($processedImages as $key => $path) {
            if ($key !== 'original' && file_exists($path)) {
                unlink($path);
            }
        }
    }
}