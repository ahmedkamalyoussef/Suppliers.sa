<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('supplier_profiles', 'slug')) {
                $table->string('slug')->nullable()->after('business_name');
                $table->unique('slug');
    }
        });

        $profiles = DB::table('supplier_profiles')->whereNull('slug')->get(['id', 'business_name']);

        foreach ($profiles as $profile) {
            $baseSlug = Str::slug($profile->business_name ?: 'supplier-' . $profile->id);
            if (!$baseSlug) {
                $baseSlug = 'supplier-' . $profile->id;
            }

            $slug = $baseSlug;
            $counter = 1;
            while (DB::table('supplier_profiles')->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }

            DB::table('supplier_profiles')->where('id', $profile->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('supplier_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_profiles', 'slug')) {
                $table->dropUnique('supplier_profiles_slug_unique');
                $table->dropColumn('slug');
            }
        });
    }
};
