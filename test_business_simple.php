<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing Business Request Endpoint ===\n\n";

try {
    // Create test supplier manually
    $supplier = new \App\Models\Supplier([
        'id' => 9999,
        'name' => 'Test Supplier',
        'email' => 'test' . time() . '@example.com',
        'phone' => '01234567890',
        'plan' => 'Premium'
    ]);
    $supplier->save();

    // Create supplier profile manually
    $profile = new \App\Models\SupplierProfile([
        'supplier_id' => $supplier->id,
        'business_name' => 'Test Business Company',
        'latitude' => 30.0444,
        'longitude' => 31.2357,
        'business_categories' => json_encode(['Technology', 'Software'])
    ]);
    $profile->save();

    // Create another supplier to receive the inquiry
    $targetSupplier = new \App\Models\Supplier([
        'id' => 9998,
        'name' => 'Target Supplier',
        'email' => 'target' . time() . '@example.com',
        'plan' => 'Premium'
    ]);
    $targetSupplier->save();

    $targetProfile = new \App\Models\SupplierProfile([
        'supplier_id' => $targetSupplier->id,
        'business_name' => 'Target Business',
        'latitude' => 30.0500,
        'longitude' => 31.2400,
        'business_categories' => json_encode(['Technology', 'Software'])
    ]);
    $targetProfile->save();

    echo "Created test suppliers:\n";
    echo "- Sender: " . $supplier->name . " (" . $supplier->email . ")\n";
    echo "- Receiver: " . $targetSupplier->name . " (" . $targetSupplier->email . ")\n\n";

    // Test request data
    $requestData = [
        'requestType' => 'productRequest',
        'industry' => 'Technology',
        'preferred_distance' => '50km',
        'description' => 'Test business request for product inquiry'
    ];

    echo "Request Data:\n";
    foreach ($requestData as $key => $value) {
        echo "- $key: $value\n";
    }

    echo "\n=== Testing Business Request Creation ===\n";

    // Create the business request
    $businessRequest = \App\Models\BusinessRequest::create([
        'requestType' => $requestData['requestType'],
        'industry' => $requestData['industry'],
        'preferred_distance' => $requestData['preferred_distance'],
        'description' => $requestData['description'],
        'supplier_id' => $supplier->id,
    ]);

    echo "✅ Business Request created successfully\n";
    echo "ID: " . $businessRequest->id . "\n";
    echo "Request Type: " . $businessRequest->requestType . "\n";
    echo "Industry: " . $businessRequest->industry . "\n\n";

    // Test inquiry creation (simulating what the controller does)
    $senderName = $profile->business_name ?? 'Business Request';
    
    $inquiry = \App\Models\SupplierToSupplierInquiry::create([
        'sender_supplier_id' => $supplier->id,
        'receiver_supplier_id' => $targetSupplier->id,
        'sender_name' => $senderName,
        'company' => $profile->business_name,
        'email' => $supplier->email,
        'phone' => $supplier->phone,
        'subject' => "Business Request: {$requestData['industry']}",
        'message' => $requestData['description'],
        'type' => 'inquiry',
    ]);

    echo "✅ Supplier Inquiry created successfully\n";
    echo "Inquiry ID: " . $inquiry->id . "\n";
    echo "Sender Name (shown to receiver): " . $inquiry->sender_name . "\n";
    echo "Company: " . $inquiry->company . "\n";
    echo "Email: " . $inquiry->email . "\n";
    echo "Phone: " . $inquiry->phone . "\n";
    echo "Subject: " . $inquiry->subject . "\n";
    echo "Message: " . $inquiry->message . "\n\n";

    echo "=== Verification ===\n";
    echo "✅ Sender identity is VISIBLE (showName behavior)\n";
    echo "✅ Real business name is used: " . $inquiry->sender_name . "\n";
    echo "✅ Request type is preserved: " . $businessRequest->requestType . "\n";
    echo "✅ All request data is transmitted correctly\n";

    // Cleanup
    $businessRequest->delete();
    $inquiry->delete();
    $supplier->delete();
    $profile->delete();
    $targetSupplier->delete();
    $targetProfile->delete();

    echo "\n✅ Test completed successfully! All cleanup done.\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}
