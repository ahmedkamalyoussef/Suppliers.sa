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
        Schema::table('supplier_ratings', function (Blueprint $table) {
            $table->string('status')->default('pending_review')->after('is_approved');
            $table->foreignId('moderated_by_admin_id')->nullable()->after('status')->constrained('admins')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable()->after('moderated_by_admin_id');
            $table->text('moderation_notes')->nullable()->after('moderated_at');
            $table->foreignId('flagged_by_admin_id')->nullable()->after('moderation_notes')->constrained('admins')->nullOnDelete();
            $table->timestamp('flagged_at')->nullable()->after('flagged_by_admin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_ratings', function (Blueprint $table) {
            $table->dropForeign(['moderated_by_admin_id']);
            $table->dropForeign(['flagged_by_admin_id']);
            $table->dropColumn([
                'status',
                'moderated_by_admin_id',
                'moderated_at',
                'moderation_notes',
                'flagged_by_admin_id',
                'flagged_at',
            ]);
        });
    }
};
