<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the old table completely
        Schema::dropIfExists('supplier_inquiries');
        
        // Recreate with new structure
        Schema::create('supplier_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');  // Full Name *
            $table->string('email_address');  // Email Address *
            $table->string('phone_number')->nullable();  // Phone Number (optional)
            $table->string('subject');  // Subject *
            $table->text('message');  // Message *
            $table->boolean('is_read')->default(false);  // Read status
            $table->string('from')->default('admin');  // Always 'admin'
            $table->unsignedBigInteger('supplier_id');  // Supplier who receives the inquiry
            $table->unsignedBigInteger('admin_id')->nullable();  // Admin who responds
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->foreign('admin_id')->references('id')->on('admins')->onDelete('set null');
            
            // Indexes
            $table->index(['supplier_id', 'is_read']);
            $table->index('from');
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
        Schema::dropIfExists('supplier_inquiries');
        
        // Recreate old table structure (if needed)
        Schema::create('supplier_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('is_unread')->default(true);
            $table->text('last_response')->nullable();
            $table->timestamp('last_response_at')->nullable();
            $table->unsignedBigInteger('handled_by_admin_id')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->unsignedBigInteger('supplier_id');
            $table->timestamps();
            
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->foreign('handled_by_admin_id')->references('id')->on('admins')->onDelete('set null');
        });
    }
};
