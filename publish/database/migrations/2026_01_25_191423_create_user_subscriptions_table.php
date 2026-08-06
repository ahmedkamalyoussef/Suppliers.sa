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
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_plan_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['active', 'expired', 'cancelled', 'pending'])->default('pending');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('cancelled_at')->nullable();
            $table->string('tap_charge_id')->nullable(); // ربط مع عملية الدفع
            $table->decimal('paid_amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('SAR');
            $table->boolean('auto_renew')->default(false);
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['status', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
