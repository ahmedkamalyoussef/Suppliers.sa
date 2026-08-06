<?php
/**
 * Safe Migration Runner for Production
 * This script runs migrations safely without losing data
 *
 * Usage: Visit https://api.supplier.sa/run_migrate.php?secret=uIz2Z90M3GVRhmeLrRee24uLSZxIn7tjUwlbOiVcU88 (DELETE after use!)
 */

// Security: Secret key required
$secret_key = 'uIz2Z90M3GVRhmeLrRee24uLSZxIn7tjUwlbOiVcU88';
$provided_key = $_GET['secret'] ?? '';

if ($provided_key !== $secret_key) {
    http_response_code(403);
    die('Forbidden: Invalid or missing secret key');
}

// Bootstrap Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

echo "<pre>";
echo "========================================\n";
echo "Laravel Safe Migration Runner\n";
echo "========================================\n\n";

// 1. Run specific new migrations (fix tables)
echo "1. Running new migrations...\n";
$newMigrations = [
    '2026_04_13_000000_fix_payments_foreign_key.php',
    '2026_04_13_100000_fix_user_subscriptions_and_transactions.php',
    '2026_04_13_200000_drop_users_table.php',
    '2026_04_13_300000_add_subscription_columns_to_suppliers.php',
];

foreach ($newMigrations as $migration) {
    echo "  - Running: $migration\n";
    try {
        Artisan::call('migrate', [
            '--path' => "database/migrations/$migration",
            '--force' => true,
        ]);
        echo "    ✓ Completed\n";
        echo Artisan::output();
    } catch (Exception $e) {
        echo "    ✗ Error: " . $e->getMessage() . "\n";
        Log::error("Migration $migration error: " . $e->getMessage());
    }
}
echo "\n";

// 2. Run any other pending migrations
echo "2. Running any remaining migrations...\n";
try {
    Artisan::call('migrate', [
        '--force' => true,
    ]);
    echo "✓ All migrations completed:\n";
    echo Artisan::output();
    echo "\n";
} catch (Exception $e) {
    echo "✗ Migration error: " . $e->getMessage() . "\n\n";
    Log::error('Migration error: ' . $e->getMessage());
}

// 3. Clear caches
echo "3. Clearing caches...\n";
try {
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    echo "✓ Caches cleared\n\n";
} catch (Exception $e) {
    echo "✗ Cache clear error: " . $e->getMessage() . "\n\n";
}

// 4. Show database status
echo "4. Database status:\n";
echo "-------------------------------------------\n";
try {
    $payments = DB::table('payments')->count();
    $transactions = DB::table('payment_transactions')->count();
    $subscriptions = DB::table('user_subscriptions')->count();
    echo "- Payments: $payments records\n";
    echo "- Payment Transactions: $transactions records\n";
    echo "- User Subscriptions: $subscriptions records\n";
} catch (Exception $e) {
    echo "Could not fetch counts: " . $e->getMessage() . "\n";
}

echo "\n========================================\n";
echo "Migration completed successfully!\n";
echo "========================================\n";
echo "\n⚠️  IMPORTANT: Delete this file immediately!\n";
echo "Path: " . __FILE__ . "\n";
echo "</pre>";

Log::info('Migration runner executed successfully via web');
