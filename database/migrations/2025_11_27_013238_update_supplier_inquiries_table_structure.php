<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_inquiries', function (Blueprint $table) {
            // Drop foreign key first if it exists
            $table->dropForeign(['handled_by_admin_id']);
            
            // Drop old columns that are no longer needed
            $oldColumns = ['name', 'email', 'phone', 'company', 'status', 'is_unread', 'last_response', 'last_response_at', 'handled_by_admin_id', 'handled_at'];
            foreach ($oldColumns as $column) {
                if (Schema::hasColumn('supplier_inquiries', $column)) {
                    $table->dropColumn($column);
                }
            }
            
            // Add new columns if they don't exist
            if (!Schema::hasColumn('supplier_inquiries', 'full_name')) {
                $table->string('full_name')->after('id');
            }
            if (!Schema::hasColumn('supplier_inquiries', 'email_address')) {
                $table->string('email_address')->after('full_name');
            }
            if (!Schema::hasColumn('supplier_inquiries', 'phone_number')) {
                $table->string('phone_number')->nullable()->after('email_address');
            }
            if (!Schema::hasColumn('supplier_inquiries', 'subject')) {
                $table->string('subject')->after('phone_number');
            }
            if (!Schema::hasColumn('supplier_inquiries', 'message')) {
                $table->text('message')->after('subject');
            }
            if (!Schema::hasColumn('supplier_inquiries', 'is_read')) {
                $table->boolean('is_read')->default(false)->after('message');
            }
            if (!Schema::hasColumn('supplier_inquiries', 'from')) {
                $table->string('from')->default('admin')->after('is_read');  // Always 'admin'
            }
            if (!Schema::hasColumn('supplier_inquiries', 'admin_id')) {
                $table->unsignedBigInteger('admin_id')->nullable()->after('supplier_id');
                $table->foreign('admin_id')->references('id')->on('admins')->onDelete('set null');
            }
        });

        // Add 'from' column to supplier_to_supplier_inquiries table
        Schema::table('supplier_to_supplier_inquiries', function (Blueprint $table) {
            if (!Schema::hasColumn('supplier_to_supplier_inquiries', 'from')) {
                $table->string('from')->default('supplier')->after('message');  // Always 'supplier'
            }
        });
    }

    public function down(): void
    {
        Schema::table('supplier_inquiries', function (Blueprint $table) {
            // Drop new columns
            $newColumns = ['full_name', 'email_address', 'phone_number', 'subject', 'message', 'is_read', 'from', 'admin_id'];
            foreach ($newColumns as $column) {
                if (Schema::hasColumn('supplier_inquiries', $column)) {
                    $table->dropColumn($column);
                }
            }
            
            // Add back old columns
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_unread')->default(true);
            $table->string('status')->default('pending');
            
            // Drop foreign key if it exists
            $table->dropForeign(['admin_id']);
        });

        // Remove 'from' column from supplier_to_supplier_inquiries table
        Schema::table('supplier_to_supplier_inquiries', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_to_supplier_inquiries', 'from')) {
                $table->dropColumn('from');
            }
        });
    }
};
