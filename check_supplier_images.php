<?php

require __DIR__ . '/vendor/autoload.php';

echo "Check Supplier Images Debug\n";
echo "==========================\n\n";

// Check suppliers table structure
echo "1. Checking suppliers table structure:\n";
echo "=====================================\n";

// Check if suppliers have profiles
echo "SELECT s.id, s.name, s.email, sp.business_image\n";
echo "FROM suppliers s\n";
echo "LEFT JOIN supplier_profiles sp ON s.id = sp.supplier_id\n";
echo "WHERE s.id IN (1, 2);\n\n";

echo "Expected results:\n";
echo "================\n";
echo "If business_image is NULL, it means:\n";
echo "1. Supplier has no profile record\n";
echo "2. Profile exists but business_image is not set\n";
echo "3. Profile exists but business_image is empty string\n\n";

echo "Solutions:\n";
echo "==========\n";
echo "1. Check if supplier_profiles table exists and has data\n";
echo "2. Make sure business_image column exists in supplier_profiles\n";
echo "3. Update existing profiles to have business_image\n\n";

echo "SQL to fix:\n";
echo "===========\n";
echo "-- Check if profiles exist\n";
echo "SELECT * FROM supplier_profiles WHERE supplier_id IN (1, 2);\n\n";

echo "-- Create profiles if they don't exist\n";
echo "INSERT INTO supplier_profiles (supplier_id, business_image, created_at, updated_at)\n";
echo "SELECT id, 'default-image.jpg', NOW(), NOW()\n";
echo "FROM suppliers\n";
echo "WHERE id IN (1, 2)\n";
echo "AND id NOT IN (SELECT supplier_id FROM supplier_profiles WHERE supplier_id IN (1, 2));\n\n";

echo "-- Update existing profiles with default image\n";
echo "UPDATE supplier_profiles\n";
echo "SET business_image = 'default-image.jpg', updated_at = NOW()\n";
echo "WHERE supplier_id IN (1, 2)\n";
echo "AND (business_image IS NULL OR business_image = '');\n\n";

echo "In Laravel code:\n";
echo "==============\n";
echo "// Check if profile exists\n";
echo "\$supplier = Supplier::with('profile')->find(1);\n";
echo "if (!\$supplier->profile) {\n";
echo "    // Create profile\n";
echo "    \$supplier->profile()->create([\n";
echo "        'business_image' => 'default-image.jpg'\n";
echo "    ]);\n";
echo "} elseif (!\$supplier->profile->business_image) {\n";
echo "    // Update existing profile\n";
echo "    \$supplier->profile->update([\n";
echo "        'business_image' => 'default-image.jpg'\n";
echo "    ]);\n";
echo "}\n\n";

echo "Quick fix in controller:\n";
echo "=======================\n";
echo "// In the controller, you can return a default image\n";
echo "'sender_image' => \$inquiry->sender && \$inquiry->sender->profile \n";
echo "    ? (\$inquiry->sender->profile->business_image ?: 'default-image.jpg')\n";
echo "    : 'default-image.jpg',\n\n";

echo "This will ensure you always have an image URL instead of null.\n";
