<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_documents')) {
            Schema::table('supplier_documents', function (Blueprint $table) {
                // Drop FKs first if present
                if (Schema::hasColumn('supplier_documents', 'reviewed_by_admin_id')) {
                    try {
                        $table->dropForeign(['reviewed_by_admin_id']);
                    } catch (\Throwable $e) {
                        // ignore if foreign key name differs
                    }
                }
            });

            Schema::table('supplier_documents', function (Blueprint $table) {
                foreach ([
                    'document_type',
                    'reference_number',
                    'issue_date',
                    'expiry_date',
                    'status',
                    'notes',
                    'reviewed_by_admin_id',
                    'reviewed_at',
                ] as $col) {
                    if (Schema::hasColumn('supplier_documents', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // No-op: we will not recreate dropped columns
    }
};
