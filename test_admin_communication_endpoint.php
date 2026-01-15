<?php

echo "Testing Admin Supplier Communication Endpoints\n";
echo "===============================================\n\n";

echo "New Admin Endpoints Created:\n";
echo "============================\n";
echo "GET /api/admin/communications - Get all communications between two suppliers\n";
echo "GET /api/admin/communications/summary - Get communication summary between two suppliers\n\n";

echo "Request Parameters:\n";
echo "==================\n";
echo "Both endpoints require:\n";
echo "- supplier1_id (integer, required) - First supplier ID\n";
echo "- supplier2_id (integer, required) - Second supplier ID (must be different from supplier1_id)\n\n";

echo "Example Request:\n";
echo "===============\n";
echo "GET /api/admin/communications?supplier1_id=1&supplier2_id=2\n\n";

echo "Response Format (Full Communications):\n";
echo "====================================\n";
echo json_encode([
    'message' => 'Communications retrieved successfully',
    'suppliers' => [
        'supplier1' => [
            'id' => 1,
            'name' => 'Supplier One',
            'email' => 'supplier1@example.com'
        ],
        'supplier2' => [
            'id' => 2,
            'name' => 'Supplier Two',
            'email' => 'supplier2@example.com'
        ]
    ],
    'statistics' => [
        'total_communications' => 15,
        'total_inquiries' => 8,
        'total_messages' => 7,
        'unread_count' => 3,
        'last_communication' => '2026-01-15T19:50:00.000000Z'
    ],
    'communications' => [
        [
            'id' => 123,
            'type' => 'inquiry',
            'sender_id' => 1,
            'sender_name' => 'Supplier One',
            'sender_email' => 'supplier1@example.com',
            'receiver_id' => 2,
            'receiver_name' => 'Supplier Two',
            'receiver_email' => 'supplier2@example.com',
            'subject' => 'Business Partnership Inquiry',
            'message' => 'We are interested in collaborating...',
            'phone' => '+1234567890',
            'company' => 'Company Name',
            'is_read' => false,
            'read_at' => null,
            'parent_id' => null,
            'inquiry_type' => 'inquiry',
            'created_at' => '2026-01-15T19:50:00.000000Z',
            'updated_at' => '2026-01-15T19:50:00.000000Z'
        ],
        [
            'id' => 456,
            'type' => 'message',
            'sender_id' => 2,
            'sender_name' => 'Supplier Two',
            'sender_email' => 'supplier2@example.com',
            'receiver_id' => 1,
            'receiver_name' => 'Supplier One',
            'receiver_email' => 'supplier1@example.com',
            'subject' => 'Re: Business Partnership',
            'message' => 'Thank you for your interest...',
            'message_type' => 'message',
            'is_read' => true,
            'created_at' => '2026-01-15T18:30:00.000000Z',
            'updated_at' => '2026-01-15T18:30:00.000000Z'
        ]
    ]
], JSON_PRETTY_PRINT) . "\n\n";

echo "Response Format (Summary):\n";
echo "========================\n";
echo json_encode([
    'message' => 'Communication summary retrieved successfully',
    'supplier1_id' => 1,
    'supplier2_id' => 2,
    'summary' => [
        'total_inquiries' => 8,
        'total_messages' => 7,
        'total_communications' => 15,
        'last_communication_at' => '2026-01-15T19:50:00.000000Z'
    ]
], JSON_PRETTY_PRINT) . "\n\n";

echo "Features:\n";
echo "=========\n";
echo "✅ Retrieves both inquiries and messages between two suppliers\n";
echo "✅ Includes sender and receiver information\n";
echo "✅ Shows read/unread status\n";
echo "✅ Provides communication statistics\n";
echo "✅ Sorted by date (newest first)\n";
echo "✅ Admin-only access with proper authentication\n";
echo "✅ Comprehensive logging for audit trail\n";
echo "✅ Error handling and validation\n\n";

echo "Usage Example (cURL):\n";
echo "======================\n";
echo "curl -X GET 'http://your-domain.com/api/admin/communications?supplier1_id=1&supplier2_id=2' \\\n";
echo "  -H 'Authorization: Bearer YOUR_ADMIN_TOKEN'\n\n";

echo "curl -X GET 'http://your-domain.com/api/admin/communications/summary?supplier1_id=1&supplier2_id=2' \\\n";
echo "  -H 'Authorization: Bearer YOUR_ADMIN_TOKEN'\n\n";

echo "Error Responses:\n";
echo "================\n";
echo "422 - Validation errors (missing/invalid IDs)\n";
echo "403 - Unauthorized (non-admin access)\n";
echo "500 - Server error\n\n";

echo "Data Sources:\n";
echo "=============\n";
echo "- SupplierToSupplierInquiry model (inquiries between suppliers)\n";
echo "- Message model (direct messages between suppliers)\n";
echo "- Supplier model (supplier information)\n\n";
