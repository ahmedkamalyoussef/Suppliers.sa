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
            if (Schema::hasColumn('supplier_ratings', 'rater_supplier_id')) {
                $table->dropForeign(['rater_supplier_id']);
                $table->dropForeign(['rated_supplier_id']);
                $table->dropUnique('supplier_ratings_rater_supplier_id_rated_supplier_id_unique');
                $table->unsignedBigInteger('rater_supplier_id')->nullable()->change();
                $table->index('rater_supplier_id');
                $table->index('rated_supplier_id');
            }

            if (! Schema::hasColumn('supplier_ratings', 'reviewer_name')) {
                $table->string('reviewer_name')->nullable()->after('comment');
            }

            if (! Schema::hasColumn('supplier_ratings', 'reviewer_email')) {
                $table->string('reviewer_email')->nullable()->after('reviewer_name');
            }
        });

        Schema::table('supplier_ratings', function (Blueprint $table) {
            $table->foreign('rater_supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->foreign('rated_supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_ratings', function (Blueprint $table) {
            $table->dropForeign(['rater_supplier_id']);
            $table->dropForeign(['rated_supplier_id']);
            $table->dropIndex(['rater_supplier_id']);
            $table->dropIndex(['rated_supplier_id']);

            if (Schema::hasColumn('supplier_ratings', 'reviewer_email')) {
                $table->dropColumn('reviewer_email');
            }

            if (Schema::hasColumn('supplier_ratings', 'reviewer_name')) {
                $table->dropColumn('reviewer_name');
            }

            $table->unsignedBigInteger('rater_supplier_id')->nullable(false)->change();
            $table->unique(['rater_supplier_id', 'rated_supplier_id']);
        });

        Schema::table('supplier_ratings', function (Blueprint $table) {
            $table->foreign('rater_supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            $table->foreign('rated_supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
        });
    }
};
