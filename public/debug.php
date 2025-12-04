<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP Version: " . phpversion() . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] ?? 'Not set' . "\n";
echo "Script Name: " . $_SERVER['SCRIPT_NAME'] ?? 'Not set' . "\n";
echo "Request URI: " . $_SERVER['REQUEST_URI'] ?? 'Not set' . "\n";

// Try to include Laravel
try {
    require __DIR__.'/../vendor/autoload.php';
    echo "Autoload: SUCCESS\n";
    
    $app = require_once __DIR__.'/../bootstrap/app.php';
    echo "Bootstrap: SUCCESS\n";
    
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    echo "Kernel: SUCCESS\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>
