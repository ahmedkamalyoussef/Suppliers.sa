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
        Schema::table('supplier_to_supplier_inquiries', function (Blueprint $table) {
            $table->enum('type', ['inquiry', 'reply'])->default('inquiry')->after('parent_id');
        });
        
        // Update existing records
        \DB::statement("UPDATE supplier_to_supplier_inquiries SET type = IF(parent_id IS NULL, 'inquiry', 'reply')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_to_supplier_inquiries', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
