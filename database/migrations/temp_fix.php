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
        Schema::table('business_requests', function (Blueprint $table) {
            $table->enum('requestType', ['product', 'pricing', 'contact', 'productRequest', 'pricingRequest', 'contactRequest'])->change();
        });

        // Update existing data
        \DB::statement("UPDATE business_requests SET requestType = 'product' WHERE requestType = 'productRequest'");
        \DB::statement("UPDATE business_requests SET requestType = 'pricing' WHERE requestType = 'pricingRequest'");
        \DB::statement("UPDATE business_requests SET requestType = 'contact' WHERE requestType = 'contactRequest'");

        Schema::table('business_requests', function (Blueprint $table) {
            $table->enum('requestType', ['product', 'pricing', 'contact'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_requests', function (Blueprint $table) {
            $table->enum('requestType', ['product', 'pricing', 'contact', 'productRequest', 'pricingRequest', 'contactRequest'])->change();
        });

        // Revert data
        \DB::statement("UPDATE business_requests SET requestType = 'productRequest' WHERE requestType = 'product'");
        \DB::statement("UPDATE business_requests SET requestType = 'pricingRequest' WHERE requestType = 'pricing'");
        \DB::statement("UPDATE business_requests SET requestType = 'contactRequest' WHERE requestType = 'contact'");

        Schema::table('business_requests', function (Blueprint $table) {
            $table->enum('requestType', ['productRequest', 'pricingRequest', 'contactRequest'])->change();
        });
    }
};
