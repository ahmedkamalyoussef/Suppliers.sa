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
        Schema::create('public_businesses_statistics', function (Blueprint $table) {
            $table->id();
            $table->integer('verified_businesses')->default(0);
            $table->integer('successful_connections')->default(0);
            $table->double('average_rating', 8, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_businesses_statistics');
    }
};
