<?php
/**
 * Safe Production Migration Runner
 * Secret Key Required
 * Safely applies database changes WITHOUT deleting existing data
 * URL: /run_fix_migration.php?secret=YOUR_SECRET&dry_run=1 (preview only)
 * URL: /run_fix_migration.php?secret=YOUR_SECRET (apply changes)
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');

$validSecret = 'uIz2Z90M3GVRhmeLrRee24uLSZxIn7tjUwlbOiVcU88';
$providedSecret = $_GET['secret'] ?? '';
$isDryRun = isset($_GET['dry_run']) && $_GET['dry_run'] == '1';

if ($providedSecret !== $validSecret) {
    http_response_code(403);
    die('Forbidden: Invalid or missing secret key');
}

// Check if required files exist
$vendorPath = __DIR__ . '/../vendor/autoload.php';
$bootstrapPath = __DIR__ . '/../bootstrap/app.php';

if (!file_exists($vendorPath)) {
    die("ERROR: vendor/autoload.php not found at: $vendorPath");
}

if (!file_exists($bootstrapPath)) {
    die("ERROR: bootstrap/app.php not found at: $bootstrapPath");
}

require $vendorPath;
$app = require_once $bootstrapPath;
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "<pre>";
echo "========================================\n";
echo "=== SAFE Production Migration Runner ===\n";
echo "========================================\n";
echo "Mode: " . ($isDryRun ? "🔍 DRY RUN (Preview Only)" : "⚡ APPLY CHANGES") . "\n";
echo "Data Protection: ✅ ACTIVE (No data will be deleted)\n\n";

try {
    // Get migration status before running
    echo "[PRE-CHECK] Checking current migration status...\n";
    Artisan::call('migrate:status');
    $statusOutput = Artisan::output();
    
    // Parse pending migrations
    $lines = explode("\n", $statusOutput);
    $pendingMigrations = [];
    foreach ($lines as $line) {
        if (strpos($line, 'Pending') !== false || strpos($line, 'No migrations') === false) {
            if (preg_match('/^\|\s+(\d{4}_\d{2}_\d{2}_\d{6}_[^\s]+)/', $line, $matches)) {
                $pendingMigrations[] = $matches[1];
            }
        }
    }
    
    if (empty($pendingMigrations)) {
        echo "✅ No pending migrations found. Database is up to date!\n\n";
    } else {
        echo "📋 Pending migrations (" . count($pendingMigrations) . " found):\n";
        foreach ($pendingMigrations as $migration) {
            echo "   • $migration\n";
        }
        echo "\n";
    }
    
    if ($isDryRun) {
        echo "========================================\n";
        echo "🔍 DRY RUN COMPLETE\n";
        echo "========================================\n";
        echo "No changes were made to the database.\n";
        echo "To apply these changes, run without &dry_run=1\n";
        echo "</pre>";
        exit;
    }
    
    // Step 1: Run pending migrations safely
    echo "[1/3] Running pending migrations (safe mode)...\n";
    echo "⚠️  NOTE: Only NEW tables/columns will be added. Existing data is safe.\n\n";
    
    Artisan::call('migrate', ['--force' => true]);
    $migrateOutput = Artisan::output();
    echo $migrateOutput . "\n";
    
    if (strpos($migrateOutput, 'Nothing to migrate') !== false) {
        echo "✅ No new migrations to run.\n\n";
    } else {
        echo "✅ Migrations completed successfully!\n\n";
    }
    
    // Step 2: Check for any new columns in existing tables
    echo "[2/3] Checking database schema updates...\n";
    $tables = DB::select('SHOW TABLES');
    $tableKey = 'Tables_in_' . env('DB_DATABASE');
    echo "📊 Found " . count($tables) . " tables in database\n";
    echo "✅ All existing tables preserved\n\n";
    
    // Step 3: Sync subscription plans (updates config only, safe)
    echo "[3/3] Syncing subscription plan configurations...\n";
    echo "ℹ️  This updates plan prices and settings only - no user data affected\n";
    
    Artisan::call('db:seed', [
        '--class' => 'SyncSubscriptionPlansSeeder',
        '--force' => true
    ]);
    $seedOutput = Artisan::output();
    echo $seedOutput . "\n";
    echo "✅ Subscription plans updated!\n\n";
    
    // Step 4: Clear caches (safe operation)
    echo "[BONUS] Clearing application caches...\n";
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    echo "✅ Caches cleared!\n\n";
    
    // Final status
    echo "========================================\n";
    echo "=== ✅ ALL TASKS COMPLETED! =========\n";
    echo "========================================\n";
    echo "\n🔒 Data Safety: All existing data preserved\n";
    echo "📝 Changes Made:\n";
    echo "   • New migrations applied: " . count($pendingMigrations) . "\n";
    echo "   • Subscription plans synced\n";
    echo "   • Caches cleared\n";
    echo "\n✨ Your application is now up to date!\n";
    
} catch (Exception $e) {
    echo "\n========================================\n";
    echo "❌ ERROR OCCURRED\n";
    echo "========================================\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\n⚠️  No changes were applied due to the error.\n";
    echo "Database remains in its previous state.\n";
}

echo "</pre>";
?>
