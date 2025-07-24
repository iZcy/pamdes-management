<?php
// Quick test script to verify tariff validation logic
// Run with: php test_tariff_validation.php

require_once 'vendor/autoload.php';

// Mock data structure for testing
$mockTariffs = [
    ['usage_min' => 1, 'usage_max' => 10],    // 1-10
    ['usage_min' => 11, 'usage_max' => 20],   // 11-20
    ['usage_min' => 21, 'usage_max' => 30],   // 21-30
    ['usage_min' => 31, 'usage_max' => null], // 31+
];

echo "=== Testing Tariff Range Validation Logic ===\n\n";

// Test case 1: Edit 11-20 to 11-25 (should adjust 21-30 to 26-30)
echo "Test 1: Edit 11-20 to 11-25\n";
echo "Current: 1-10, 11-20, 21-30, 31+\n";
echo "Edit: 11-20 → 11-25\n";

$editingRange = $mockTariffs[1]; // 11-20
$newMax = 25;
$nextRange = $mockTariffs[2]; // 21-30
$requiredNextMin = $newMax + 1; // 26

if ($requiredNextMin <= $nextRange['usage_max']) {
    $newNextRangeSize = $nextRange['usage_max'] - $requiredNextMin + 1;
    if ($newNextRangeSize >= 1) {
        echo "✅ VALID: Next range becomes 26-30 (size: {$newNextRangeSize})\n";
    } else {
        echo "❌ INVALID: Next range would be too small\n";
    }
} else {
    echo "❌ INVALID: Would eliminate next range\n";
}

echo "\n";

// Test case 2: Edit 11-20 to 11-29 (should fail - next range would be too small)
echo "Test 2: Edit 11-20 to 11-29\n";
echo "Current: 1-10, 11-20, 21-30, 31+\n";
echo "Edit: 11-20 → 11-29\n";

$newMax = 29;
$requiredNextMin = $newMax + 1; // 30

if ($requiredNextMin <= $nextRange['usage_max']) {
    $newNextRangeSize = $nextRange['usage_max'] - $requiredNextMin + 1;
    if ($newNextRangeSize >= 1) {
        echo "✅ VALID: Next range becomes 30-30 (size: {$newNextRangeSize})\n";
    } else {
        echo "❌ INVALID: Next range would be too small\n";
    }
} else {
    echo "❌ INVALID: Would eliminate next range\n";
}

echo "\n";

// Test case 3: Edit 11-20 to 11-30 (should fail - would eliminate next range)
echo "Test 3: Edit 11-20 to 11-30\n";
echo "Current: 1-10, 11-20, 21-30, 31+\n";
echo "Edit: 11-20 → 11-30\n";

$newMax = 30;
$requiredNextMin = $newMax + 1; // 31
$rangeAfterNext = $mockTariffs[3]; // 31+

if ($requiredNextMin >= $rangeAfterNext['usage_min']) {
    echo "❌ INVALID: Would affect multiple ranges (next range collides with 31+)\n";
} else {
    echo "✅ VALID: Would be allowed\n";
}

echo "\n";

// Test case 4: Edit 11-20 to 11-31 (should fail - affects multiple ranges)
echo "Test 4: Edit 11-20 to 11-31\n";
echo "Current: 1-10, 11-20, 21-30, 31+\n";
echo "Edit: 11-20 → 11-31\n";

$newMax = 31;
$requiredNextMin = $newMax + 1; // 32

if ($requiredNextMin > $rangeAfterNext['usage_min']) {
    echo "❌ INVALID: Would affect multiple ranges (forces 31+ to become 32+)\n";
} else {
    echo "✅ VALID: Would be allowed\n";
}

echo "\n=== Test Complete ===\n";
?>