<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop foreign key constraints from otps table first
        Schema::table('otps', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        // Now drop users table
        Schema::dropIfExists('users');
    }

    public function down(): void
    {
        // Recreate users table if needed (for rollback)
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // Add user_id back to otps
        Schema::table('otps', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users');
        });
    }
};
