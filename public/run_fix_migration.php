<?php
/**
 * Fix Payments Table Migration Runner
 * Secret Key Required
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
echo "=== Fixing Payments Table ===\n\n";

try {
    echo "Running migration to fix foreign key...\n";
    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_04_13_000000_fix_payments_foreign_key.php',
        '--force' => true
    ]);
    echo Artisan::output() . "\n";
    
    echo "✅ Payments table fixed successfully!\n";
    echo "Foreign key now references 'suppliers' table.\n\n";
    
    echo "Clearing caches...\n";
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    echo "✅ Caches cleared!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}

echo "</pre>";
?>
