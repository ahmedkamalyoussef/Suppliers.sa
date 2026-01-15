<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Http\Request;
use App\Http\Controllers\Admin\AdminEmailController;

// Test data for the email endpoint
$testData = [
    'to' => 'test@example.com',
    'subject' => 'Test Email from Admin',
    'message' => 'This is a test email sent from the admin email endpoint.'
];

// Create a mock request
$request = new Request();
$request->merge($testData);

echo "Testing Admin Email Endpoint\n";
echo "============================\n";
echo "To: " . $testData['to'] . "\n";
echo "Subject: " . $testData['subject'] . "\n";
echo "Message: " . $testData['message'] . "\n\n";

// Note: This test file is for demonstration purposes
// In a real environment, you would need to:
// 1. Set up proper Laravel application context
// 2. Authenticate as an admin user
// 3. Make actual HTTP requests to the API endpoints

echo "API Endpoints Created:\n";
echo "=====================\n";
echo "POST /api/admin/email/send - Send single email\n";
echo "POST /api/admin/email/send-bulk - Send bulk emails\n\n";

echo "Request Format for Single Email:\n";
echo "================================\n";
echo json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

echo "Request Format for Bulk Email:\n";
echo "==============================\n";
$bulkTestData = [
    'recipients' => ['user1@example.com', 'user2@example.com', 'user3@example.com'],
    'subject' => 'Bulk Email from Admin',
    'message' => 'This is a bulk email sent to multiple recipients.'
];
echo json_encode($bulkTestData, JSON_PRETTY_PRINT) . "\n\n";

echo "Authentication:\n";
echo "===============\n";
echo "- Requires admin authentication (auth:sanctum middleware)\n";
echo "- Only authenticated admin users can access these endpoints\n";
echo "- Email sending uses Resend service (configured in Laravel)\n\n";

echo "Usage Example (cURL):\n";
echo "======================\n";
echo "curl -X POST http://your-domain.com/api/admin/email/send \\\n";
echo "  -H 'Authorization: Bearer YOUR_ADMIN_TOKEN' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\"to\":\"recipient@example.com\",\"subject\":\"Test Subject\",\"message\":\"Test message\"}'\n\n";
