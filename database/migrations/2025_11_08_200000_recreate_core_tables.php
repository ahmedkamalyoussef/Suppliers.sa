<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // USERS
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email')->unique()->nullable();
                $table->string('password');
                $table->string('profile_image')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        // SUPPLIERS
        if (!Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('password');
                $table->boolean('email_verified')->default(false);
                $table->string('profile_image')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        // SUPPLIER PROFILES
        if (!Schema::hasTable('supplier_profiles')) {
            Schema::create('supplier_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
                $table->string('main_phone')->nullable();
                $table->string('business_name')->nullable();
                $table->string('business_type')->nullable();
                $table->json('business_categories')->nullable();
                $table->json('keywords')->nullable();
                $table->json('target_market')->nullable();
                $table->json('services_offered')->nullable();
                $table->decimal('service_distance', 8, 2)->nullable();
                $table->string('website')->nullable();
                $table->json('additional_phones')->nullable();
                $table->string('business_address')->nullable();
                $table->decimal('latitude', 10, 6)->nullable();
                $table->decimal('longitude', 10, 6)->nullable();
                $table->json('working_hours')->nullable();
                $table->boolean('has_branches')->default(false);
                $table->timestamps();
            });
        }

        // BRANCHES
        if (!Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
                $table->string('name');
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('address')->nullable();
                $table->string('manager_name')->nullable();
                $table->decimal('latitude', 10, 6)->nullable();
                $table->decimal('longitude', 10, 6)->nullable();
                $table->json('working_hours')->nullable();
                $table->json('special_services')->nullable();
                $table->string('status')->default('active');
                $table->boolean('is_main_branch')->default(false);
                $table->timestamps();
            });
        }

        // OTPS with two FKs: user_id and supplier_id (both nullable)
        if (!Schema::hasTable('otps')) {
            Schema::create('otps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('supplier_id')->nullable()->index();
                $table->string('email')->nullable()->index();
                $table->string('otp', 6)->index();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                // Add explicit foreign keys if referenced tables exist
                if (Schema::hasTable('users')) {
                    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                }
                if (Schema::hasTable('suppliers')) {
                    $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
                }
            });
        }

        // Personal access tokens (sanctum) - only create if missing
        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }

        // jobs and cache tables (lightweight) if missing
        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->tinyInteger('attempts')->unsigned();
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->unique();
                $table->longText('value');
                $table->integer('expiration');
            });
        }
    }

    public function down(): void
    {
        // Intentionally do not drop tables automatically to prevent data loss.
    }
};
