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
        Schema::table('supplier_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('supplier_profiles', 'contact_email')) {
                $table->string('contact_email')->nullable()->after('main_phone');
            }

            if (!Schema::hasColumn('supplier_profiles', 'description')) {
                $table->text('description')->nullable()->after('business_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_profiles', 'contact_email')) {
                $table->dropColumn('contact_email');
            }

            if (Schema::hasColumn('supplier_profiles', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
