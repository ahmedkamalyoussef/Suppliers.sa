<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('password');
            $table->boolean('email_verified')->default(false);
            $table->string('referral_code')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('supplier_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->string('main_phone');
            $table->string('business_name');
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

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('phone');
            $table->string('email');
            $table->string('address');
            $table->string('manager_name');
            $table->decimal('latitude', 10, 6);
            $table->decimal('longitude', 10, 6);
            $table->json('working_hours');
            $table->json('special_services');
            $table->string('status')->default('active');
            $table->boolean('is_main_branch')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('branches');
        Schema::dropIfExists('supplier_profiles');
        Schema::dropIfExists('suppliers');
    }
};