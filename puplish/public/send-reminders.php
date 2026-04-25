<?php
/**
 * Send Subscription Reminders via HTTP
 * Usage: https://api.supplier.sa/send-reminders.php?secret=uIz2Z90M3GVRhmeLrRee24uLSZxIn7tjUwlbOiVcU88
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

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

$output = [];
$output[] = "Started: " . now();

try {
    // Run reminders command
    Artisan::call('subscriptions:send-reminders');
    $commandOutput = Artisan::output();
    $output[] = "Command output: " . trim($commandOutput);
    
    Log::info('Cron: subscriptions:send-reminders executed via HTTP', ['output' => $commandOutput]);
    
    $output[] = "Status: SUCCESS";
    
} catch (Exception $e) {
    $output[] = "Error: " . $e->getMessage();
    Log::error('Cron: subscriptions:send-reminders failed', ['error' => $e->getMessage()]);
    $output[] = "Status: FAILED";
}

$output[] = "Finished: " . now();

// Return plain text for cron logging
header('Content-Type: text/plain');
echo implode("\n", $output);
