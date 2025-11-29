<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_inquiries', function (Blueprint $table) {
            $table->text('admin_response')->nullable()->after('message');
            $table->timestamp('admin_responded_at')->nullable()->after('admin_response');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_inquiries', function (Blueprint $table) {
            $table->dropColumn(['admin_response', 'admin_responded_at']);
        });
    }
};
