<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('supplier_profiles', function (Blueprint $table) {
            $table->string('category')->nullable()->after('business_type');
            $table->string('image')->nullable()->after('category');
        });
    }

    public function down()
    {
        Schema::table('supplier_profiles', function (Blueprint $table) {
            $table->dropColumn(['category', 'image']);
        });
    }
};
