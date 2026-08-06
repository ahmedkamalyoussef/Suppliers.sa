<?php
/**
 * Clear All Caches
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
echo "=== Clearing All Caches ===\n\n";

$commands = [
    'config:clear',
    'cache:clear',
    'route:clear',
    'view:clear',
];

foreach ($commands as $command) {
    echo "Running: $command\n";
    Artisan::call($command);
    echo Artisan::output();
}

echo "\n✅ All caches cleared!\n";
echo "</pre>";
?>
