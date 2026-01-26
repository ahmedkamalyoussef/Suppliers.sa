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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // basic, premium
            $table->string('display_name'); // Basic, Premium
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2); // 199.00, 1799.00
            $table->string('currency', 3)->default('SAR');
            $table->enum('billing_cycle', ['monthly', 'yearly']); // شهري، سنوي
            $table->integer('duration_months'); // 1 for monthly, 12 for yearly
            $table->json('features')->nullable(); // مميزات الباقة
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
