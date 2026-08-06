<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Simplify supplier_product_images table
        Schema::table('supplier_product_images', function (Blueprint $table) {
            // Drop any columns except id, supplier_id, image_url, and timestamps
            $columns = Schema::getColumnListing('supplier_product_images');
            $columnsToKeep = ['id', 'supplier_id', 'image_url', 'created_at', 'updated_at'];
            $columnsToDrop = array_diff($columns, $columnsToKeep);
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });

        // Simplify supplier_services table
        Schema::table('supplier_services', function (Blueprint $table) {
            // Drop any columns except id, supplier_id, service_name, and timestamps
            $columns = Schema::getColumnListing('supplier_services');
            $columnsToKeep = ['id', 'supplier_id', 'service_name', 'created_at', 'updated_at'];
            $columnsToDrop = array_diff($columns, $columnsToKeep);
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });

        // Simplify supplier_certifications table
        Schema::table('supplier_certifications', function (Blueprint $table) {
            // Drop any columns except id, supplier_id, certification_name, and timestamps
            $columns = Schema::getColumnListing('supplier_certifications');
            $columnsToKeep = ['id', 'supplier_id', 'certification_name', 'created_at', 'updated_at'];
            $columnsToDrop = array_diff($columns, $columnsToKeep);
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Since we're only removing columns, we can't fully reverse this migration
        // as we don't know the exact previous structure
    }
};
