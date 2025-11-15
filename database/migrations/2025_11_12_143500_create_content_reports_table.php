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
        Schema::create('content_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('reported_by_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('report_type');
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('reason')->nullable();
            $table->text('details')->nullable();
            $table->string('reported_by_name')->nullable();
            $table->string('reported_by_email')->nullable();
            $table->foreignId('handled_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_reports');
    }
};

