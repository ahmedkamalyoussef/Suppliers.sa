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
        Schema::create('supplier_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rater_supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignId('rated_supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->unsignedTinyInteger('score'); // 1..5
            $table->text('comment')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->timestamps();

            $table->unique(['rater_supplier_id', 'rated_supplier_id']); // prevent duplicate rating
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_ratings');
    }
};
