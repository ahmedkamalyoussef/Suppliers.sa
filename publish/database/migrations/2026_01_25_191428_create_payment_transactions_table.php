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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->onDelete('set null');
            $table->string('tap_charge_id')->nullable()->unique(); // معرف عملية الدفع من Tap
            $table->string('tap_refund_id')->nullable(); // معرف عملية الاسترداد
            $table->enum('type', ['subscription', 'refund', 'renewal'])->default('subscription');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded', 'cancelled'])->default('pending');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('SAR');
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->json('tap_response')->nullable(); // استجابة Tap كاملة
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // بيانات إضافية
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['tap_charge_id']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
