<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

// Get first admin
$admin = Admin::first();

if ($admin) {
    echo "Admin found: " . $admin->email . "\n";
    
    // Test the update endpoint with sample data
    $testData = [
        'verified_businesses' => 200,
        'successful_connections' => 1500,
        'average_rating' => 4.5
    ];
    
    echo "Test data for update: " . json_encode($testData) . "\n";
    echo "To test the admin endpoint, use:\n";
    echo "PUT /api/admin/businesses-statistics\n";
    echo "Headers: Authorization: Bearer <admin_token>\n";
    echo "Body: " . json_encode($testData) . "\n";
} else {
    echo "No admin found\n";
}
