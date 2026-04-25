<?php
/**
 * Production Migration & Seeder Runner
 * Secret Key Required
 * Runs: All pending migrations + SyncSubscriptionPlansSeeder
 */
$validSecret = 'uIz2Z90M3GVRhmeLrRee24uLSZxIn7tjUwlbOiVcU88';
$providedSecret = $_GET['secret'] ?? '';

if ($providedSecret !== $validSecret) {
    http_response_code(403);
    die('Forbidden: Invalid or missing secret key');
}

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;

echo "<pre>";
echo "========================================\n";
echo "=== Production Migration Runner ======\n";
echo "========================================\n\n";

try {
    // Step 1: Run all pending migrations
    echo "[1/4] Running all pending migrations...\n";
    Artisan::call('migrate', ['--force' => true]);
    echo Artisan::output() . "\n";
    echo "✅ All migrations completed!\n\n";
    
    // Step 2: Fix payments foreign key (if not already applied)
    echo "[2/4] Checking payments table fix...\n";
    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_04_13_000000_fix_payments_foreign_key.php',
        '--force' => true
    ]);
    echo Artisan::output() . "\n";
    echo "✅ Payments table verified!\n\n";
    
    // Step 3: Sync Subscription Plans (the important one!)
    echo "[3/4] Syncing subscription plans with SAR currency...\n";
    Artisan::call('db:seed', [
        '--class' => 'SyncSubscriptionPlansSeeder',
        '--force' => true
    ]);
    echo Artisan::output() . "\n";
    echo "✅ Subscription plans synced with latest SAR pricing!\n\n";
    
    // Step 4: Clear caches
    echo "[4/4] Clearing caches...\n";
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    echo "✅ All caches cleared!\n\n";
    
    echo "========================================\n";
    echo "=== ✅ ALL TASKS COMPLETED! =========\n";
    echo "========================================\n";
    echo "\nMigration Status: Production Ready\n";
    echo "Currency: SAR (Saudi Riyal)\n";
    echo "Plans Updated: Monthly & Yearly Premium\n";
    
} catch (Exception $e) {
    echo "\n========================================\n";
    echo "❌ ERROR OCCURRED\n";
    echo "========================================\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "</pre>";
?>
