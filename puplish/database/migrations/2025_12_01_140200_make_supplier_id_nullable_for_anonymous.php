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
        Schema::table('supplier_inquiries', function (Blueprint $table) {
            // Drop the existing foreign key
            $table->dropForeign(['supplier_id']);
            
            // Make the column nullable
            $table->unsignedBigInteger('supplier_id')->nullable()->change();
            
            // Re-add the foreign key with set null on delete
            $table->foreign('supplier_id')
                  ->references('id')
                  ->on('suppliers')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_inquiries', function (Blueprint $table) {
            // Drop the foreign key
            $table->dropForeign(['supplier_id']);
            
            // Make the column not nullable
            $table->unsignedBigInteger('supplier_id')->nullable(false)->change();
            
            // Re-add the original foreign key
            $table->foreign('supplier_id')
                  ->references('id')
                  ->on('suppliers')
                  ->onDelete('cascade');
        });
    }
};
