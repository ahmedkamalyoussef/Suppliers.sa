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
        Schema::create('total_searches', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->integer('search_count')->default(0);
            $table->timestamps();
            
            $table->unique('date'); // One record per day
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('total_searches');
    }
};
