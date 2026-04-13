<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if payments table exists
        if (Schema::hasTable('payments')) {
            // Drop foreign key constraint first
            try {
                Schema::table('payments', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                    $table->dropIndex(['user_id', 'status']);
                });
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }
            
            // Drop the table
            Schema::dropIfExists('payments');
        }
        
        // Create payments table with supplier_id instead of user_id
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->string('tap_id')->nullable(); // Tap charge ID
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('SAR');
            $table->enum('status', ['INITIATED', 'AUTHORIZED', 'CAPTURED', 'FAILED', 'VOID'])->default('INITIATED');
            $table->boolean('is_paid')->default(false);
            $table->json('raw_response')->nullable(); // Full response from Tap
            $table->string('order_id')->nullable(); // Order reference
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            $table->index(['supplier_id', 'status']);
            $table->index(['tap_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
