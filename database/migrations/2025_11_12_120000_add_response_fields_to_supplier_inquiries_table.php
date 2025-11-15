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
        Schema::table('supplier_inquiries', function (Blueprint $table) {
            $table->boolean('is_unread')->default(true)->after('status');
            $table->text('last_response')->nullable()->after('is_unread');
            $table->timestamp('last_response_at')->nullable()->after('last_response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_inquiries', function (Blueprint $table) {
            $table->dropColumn(['is_unread', 'last_response', 'last_response_at']);
        });
    }
};

