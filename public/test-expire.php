<?php
/**
 * Test Subscription Expiration
 * Usage: https://api.supplier.sa/test-expire.php?secret=uIz2Z90M3GVRhmeLrRee24uLSZxIn7tjUwlbOiVcU88
 */

$secret_key = 'uIz2Z90M3GVRhmeLrRee24uLSZxIn7tjUwlbOiVcU88';
$provided_key = $_GET['secret'] ?? '';

if ($provided_key !== $secret_key) {
    http_response_code(403);
    die('Forbidden');
}

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "<pre>";
echo "========================================\n";
echo "Test Subscription Expiration\n";
echo "========================================\n\n";

// 1. Check ALL subscriptions
echo "1. All subscriptions:\n";
$all = DB::table('user_subscriptions')->get();
foreach ($all as $sub) {
    $isExpired = $sub->ends_at < now() ? 'YES' : 'NO';
    echo "   ID: {$sub->id}, Status: {$sub->status}, Ends: {$sub->ends_at}, Expired: {$isExpired}\n";
}
echo "\n";

// 2. What the query SHOULD find
echo "2. Query: WHERE status='active' AND ends_at < now()\n";
echo "   Now: " . now() . "\n";
$expired = DB::table('user_subscriptions')
    ->where('status', 'active')
    ->where('ends_at', '<', now())
    ->get();
    
if ($expired->isEmpty()) {
    echo "   Result: NO subscriptions found\n";
} else {
    foreach ($expired as $sub) {
        echo "   Found: ID {$sub->id}, Ends: {$sub->ends_at}\n";
    }
}
echo "\n";

// 3. Run the expire command
echo "3. Running subscriptions:expire command:\n";
try {
    \Illuminate\Support\Facades\Artisan::call('subscriptions:expire');
    echo "   Output: " . \Illuminate\Support\Facades\Artisan::output() . "\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Check after
echo "4. After running command:\n";
$after = DB::table('user_subscriptions')->where('supplier_id', 1)->first();
if ($after) {
    echo "   Status: {$after->status}\n";
    echo "   Ends: {$after->ends_at}\n";
}

echo "\n========================================\n";
echo "⚠️ Delete this file after use!\n";
echo "</pre>";
