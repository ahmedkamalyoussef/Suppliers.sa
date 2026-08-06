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
        Schema::table('supplier_documents', function (Blueprint $table) {
            // Add back the columns that were dropped
            $table->string('document_type')->nullable();
            $table->string('reference_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_documents', function (Blueprint $table) {
            // Drop the columns we added
            $table->dropColumn([
                'document_type',
                'reference_number', 
                'issue_date',
                'expiry_date',
                'status',
                'notes',
                'reviewed_by_admin_id',
                'reviewed_at'
            ]);
        });
    }
};
