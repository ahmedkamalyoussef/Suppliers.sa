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
            $table->enum('requestType', ['productRequest', 'pricingRequest', 'contactRequest'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_requests', function (Blueprint $table) {
            $table->enum('requestType', ['product request', 'pricing', 'contact'])->change();
        });
    }
};
