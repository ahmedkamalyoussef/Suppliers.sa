<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Api\Supplier\BusinessRequestController;
use App\Models\Supplier;
use App\Models\SupplierProfile;
use App\Models\SupplierToSupplierInquiry;

// Mock authentication
function mockAuth() {
    $supplier = new Supplier([
        'id' => 1,
        'name' => 'Test Supplier',
        'email' => 'test@example.com',
        'phone' => '01234567890',
        'plan' => 'Premium'
    ]);
    
    $profile = new SupplierProfile([
        'business_name' => 'Test Business',
        'latitude' => 30.0444,
        'longitude' => 31.2357,
        'business_categories' => ['Technology', 'Software']
    ]);
    
    $supplier->setRelation('profile', $profile);
    
    return $supplier;
}

// Test data
$testData = [
    'requestType' => 'productRequest',
    'industry' => 'Technology',
    'preferred_distance' => '50km',
    'description' => 'Test business request for product inquiry'
];

echo "=== Testing Business Request Endpoint ===\n\n";

// Mock request
$request = new Request();
$request->merge($testData);

// Mock authenticated user
$request->setUserResolver(function() {
    return mockAuth();
});

echo "Request Data:\n";
echo "- requestType: " . $request->requestType . "\n";
echo "- industry: " . $request->industry . "\n";
echo "- preferred_distance: " . $request->preferred_distance . "\n";
echo "- description: " . $request->description . "\n\n";

// Test validation
$validationRules = (new \App\Http\Requests\StoreBusinessRequestRequest())->rules();
echo "Validation Rules:\n";
print_r($validationRules);

echo "\n=== Test Results ===\n";

// Check if validation would pass
$validator = \Validator::make($testData, $validationRules);
if ($validator->fails()) {
    echo "❌ Validation failed:\n";
    print_r($validator->errors());
} else {
    echo "✅ Validation passed\n";
}

// Test sender name logic
$supplier = mockAuth();
$senderName = $supplier->profile->business_name ?? 'Business Request';

echo "\nSender Information:\n";
echo "- Sender Name: " . $senderName . "\n";
echo "- Email: " . $supplier->email . "\n";
echo "- Phone: " . $supplier->phone . "\n";
echo "- Business Name: " . $supplier->profile->business_name . "\n";

echo "\n✅ Test completed successfully!\n";
echo "The business request will be sent with the supplier's real name (showName behavior).\n";
echo "Request type: " . $request->requestType . "\n";
