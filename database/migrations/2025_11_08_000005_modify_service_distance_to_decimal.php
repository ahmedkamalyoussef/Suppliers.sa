<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('supplier_profiles', function (Blueprint $table) {
            $table->decimal('service_distance', 8, 2)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('supplier_profiles', function (Blueprint $table) {
            $table->string('service_distance')->nullable()->change();
        });
    }
};