<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Quick test of main search categories
$tests = [
    // Categories
    'ملابس', 'موضة', 'زراعة', 'كمبيوتر', 'بناء', 'كهرباء', 'طعام', 'صحة', 'سيارات', 'أثاث',
    'fashion', 'agriculture', 'computer', 'construction', 'electronics', 'food', 'health', 'cars', 'furniture',
    
    // Ratings
    'ممتاز', 'جيد', 'سيء', 'تقيم 4', 'rating 3', '5 stars', 'اكبر من 3', 'اقل من 4',
    'excellent', 'good', 'bad', '4 stars', '3 stars', 'greater than 3', 'less than 4',
    
    // Products
    'قميص', 'فستان', 'سمدة', 'لابتوب', 'اسمنت', 'برجر', 'كريم', 'سيارة', 'كنبة', 'كتاب',
    'shirt', 'dress', 'fertilizer', 'laptop', 'cement', 'burger', 'cream', 'car', 'sofa', 'book',
    
    // Services
    'توصيل', 'صيانة', 'استشارة', 'تطوير', 'بناء', 'طبخ', 'علاج', 'تركيب', 'تصميم', 'طباعة',
    'delivery', 'maintenance', 'consulting', 'development', 'construction', 'cooking', 'treatment', 'installation', 'design', 'printing',
    
    // Address
    'القاهرة', 'الاسكندرية', 'الجيزة', 'المنصورة', 'وسط البلد', 'الدقي', 'المعادي',
    'Cairo', 'Alexandria', 'Giza', 'Mansoura', 'downtown', 'Dokki', 'Maadi',
    
    // Business Type
    'مورد', 'شركة', 'متجر', 'مصنع', 'مطعم', 'عيادة', 'مكتب', 'فندق',
    'supplier', 'company', 'store', 'factory', 'restaurant', 'clinic', 'office', 'hotel',
    
    // Working Hours
    'مفتوح الآن', 'يعمل الآن', '24 ساعة', 'مفتوح باكر', 'مفتوح ليل',
    'open now', 'working now', '24 hours', 'open early', 'open late',
    
    // Keywords
    'محترف', 'خبير', 'جودة عالية', 'سعر منخفض', 'سريع', 'موثوق',
    'professional', 'expert', 'high quality', 'low price', 'fast', 'reliable'
];

echo "Running " . count($tests) . " comprehensive search tests...\n\n";

$results = [
    'total' => count($tests),
    'passed' => 0,
    'failed' => 0,
    'errors' => 0,
    'details' => []
];

foreach ($tests as $i => $query) {
    try {
        $url = "http://localhost:8000/api/public/businesses?ai=" . urlencode($query);
        $response = file_get_contents($url, false, stream_context_create([
            'http' => ['timeout' => 5]
        ]));
        
        if ($response === false) {
            $results['errors']++;
            echo "ERROR: Test $i failed - Network error for query: $query\n";
            continue;
        }
        
        $data = json_decode($response, true);
        if ($data === null) {
            $results['errors']++;
            echo "ERROR: Test $i failed - Invalid JSON for query: $query\n";
            continue;
        }
        
        $count = $data['data'] ? count($data['data']) : 0;
        
        if ($count > 0) {
            $results['passed']++;
        } else {
            $results['failed']++;
            echo "FAILED: Test $i - No results for query: $query\n";
        }
        
        $results['details'][] = [
            'query' => $query,
            'results' => $count
        ];
        
        // Progress indicator
        if (($i + 1) % 20 === 0) {
            echo "Progress: " . ($i + 1) . "/" . $results['total'] . " tests completed\n";
        }
        
    } catch (Exception $e) {
        $results['errors']++;
        echo "ERROR: Test $i failed - Exception: " . $e->getMessage() . " for query: $query\n";
    }
}

echo "\n=== COMPREHENSIVE TEST RESULTS ===\n";
echo "Total Tests: {$results['total']}\n";
echo "Passed: {$results['passed']} (" . round(($results['passed'] / $results['total']) * 100, 2) . "%)\n";
echo "Failed: {$results['failed']} (" . round(($results['failed'] / $results['total']) * 100, 2) . "%)\n";
echo "Errors: {$results['errors']} (" . round(($results['errors'] / $results['total']) * 100, 2) . "%)\n\n";

// Show some successful tests
echo "=== SAMPLE SUCCESSFUL TESTS ===\n";
$passedCount = 0;
foreach ($results['details'] as $detail) {
    if ($detail['results'] > 0 && $passedCount < 10) {
        echo "{$detail['query']}: {$detail['results']} results\n";
        $passedCount++;
    }
}

// Save detailed results
file_put_contents('/srv/http/back/test_results.json', json_encode($results, JSON_PRETTY_PRINT));
echo "\nDetailed results saved to: /srv/http/back/test_results.json\n";
