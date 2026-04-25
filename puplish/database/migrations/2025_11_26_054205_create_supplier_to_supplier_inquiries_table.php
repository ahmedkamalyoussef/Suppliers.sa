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
        Schema::create('supplier_to_supplier_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignId('receiver_supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->string('sender_name');
            $table->string('company')->nullable();
            $table->string('email');
            $table->string('phone');
            $table->string('subject');
            $table->text('message');
            $table->foreignId('parent_id')->nullable()->constrained('supplier_to_supplier_inquiries')->onDelete('cascade');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_to_supplier_inquiries');
    }
};
