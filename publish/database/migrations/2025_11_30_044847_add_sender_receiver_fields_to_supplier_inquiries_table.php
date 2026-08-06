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
            $table->unsignedBigInteger('sender_id')->nullable()->after('id');
            $table->unsignedBigInteger('receiver_id')->nullable()->after('sender_id');
            $table->boolean('is_guest')->default(false)->after('sender_id');
            
            // Add foreign keys
            $table->foreign('sender_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->foreign('receiver_id')->references('id')->on('suppliers')->onDelete('cascade');
            
            // Add indexes
            $table->index(['sender_id', 'type']);
            $table->index(['receiver_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_inquiries', function (Blueprint $table) {
            $table->dropForeign(['sender_id']);
            $table->dropForeign(['receiver_id']);
            $table->dropColumn(['sender_id', 'receiver_id', 'is_guest']);
        });
    }
};
