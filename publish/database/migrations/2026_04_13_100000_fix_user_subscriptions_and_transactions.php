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
        // Fix user_subscriptions table
        if (Schema::hasTable('user_subscriptions')) {
            Schema::table('user_subscriptions', function (Blueprint $table) {
                // Drop foreign key if exists
                try {
                    $table->dropForeign(['user_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
                
                // Rename column
                if (Schema::hasColumn('user_subscriptions', 'user_id')) {
                    $table->renameColumn('user_id', 'supplier_id');
                }
                
                // Add foreign key
                $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            });
        }
        
        // Fix payment_transactions table
        if (Schema::hasTable('payment_transactions')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                // Drop foreign key if exists
                try {
                    $table->dropForeign(['user_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
                
                // Rename column
                if (Schema::hasColumn('payment_transactions', 'user_id')) {
                    $table->renameColumn('user_id', 'supplier_id');
                }
                
                // Add foreign key
                $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert user_subscriptions table
        if (Schema::hasTable('user_subscriptions')) {
            Schema::table('user_subscriptions', function (Blueprint $table) {
                try {
                    $table->dropForeign(['supplier_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
                
                if (Schema::hasColumn('user_subscriptions', 'supplier_id')) {
                    $table->renameColumn('supplier_id', 'user_id');
                }
                
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
        
        // Revert payment_transactions table
        if (Schema::hasTable('payment_transactions')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                try {
                    $table->dropForeign(['supplier_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
                
                if (Schema::hasColumn('payment_transactions', 'supplier_id')) {
                    $table->renameColumn('supplier_id', 'user_id');
                }
                
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }
};
