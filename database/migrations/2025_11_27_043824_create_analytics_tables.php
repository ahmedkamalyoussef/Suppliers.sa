<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Views History Table - Track daily profile views
        Schema::create('analytics_views_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->date('date');
            $table->integer('views_count')->default(0);
            $table->integer('unique_visitors')->default(0);
            $table->timestamps();
            
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->unique(['supplier_id', 'date']);
            $table->index(['supplier_id', 'date']);
        });

        // 2. Search Logs Table - Track search keywords
        Schema::create('analytics_search_logs', function (Blueprint $table) {
            $table->id();
            $table->string('keyword');
            $table->string('search_type')->default('supplier'); // supplier, product, category
            $table->unsignedBigInteger('supplier_id')->nullable(); // If search led to this supplier
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('location')->nullable(); // City, Country
            $table->timestamp('searched_at');
            $table->boolean('resulted_in_contact')->default(false);
            
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            $table->index(['keyword', 'searched_at']);
            $table->index(['supplier_id', 'searched_at']);
        });

        // 3. Visitor Logs Table - Track visitor behavior
        Schema::create('analytics_visitor_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->string('session_id')->nullable();
            $table->string('ip_address');
            $table->string('user_agent')->nullable();
            $table->string('location')->nullable(); // City, Country
            $table->string('customer_type')->nullable(); // Large Organizations, Small Businesses, Individuals
            $table->timestamp('first_visit');
            $table->timestamp('last_visit');
            $table->integer('page_views')->default(1);
            $table->integer('duration_seconds')->default(0);
            $table->boolean('resulted_in_inquiry')->default(false);
            $table->boolean('resulted_in_contact')->default(false);
            
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->index(['supplier_id', 'last_visit']);
            $table->index(['location', 'last_visit']);
        });

        // 4. Performance Metrics Table - Store calculated metrics
        Schema::create('analytics_performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->string('metric_name'); // profile_completion, response_rate, etc.
            $table->decimal('value', 8, 2);
            $table->decimal('target', 8, 2);
            $table->string('unit')->default('%'); // %, stars, score
            $table->date('calculated_date');
            $table->timestamps();
            
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->unique(['supplier_id', 'metric_name', 'calculated_date'], 'perf_metrics_unique');
            $table->index(['supplier_id', 'calculated_date']);
        });

        // 5. Recommendations Table - Store AI-generated recommendations
        Schema::create('analytics_recommendations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->string('recommendation');
            $table->string('priority')->default('medium'); // low, medium, high
            $table->string('category')->default('general'); // profile, marketing, performance
            $table->boolean('is_dismissed')->default(false);
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->index(['supplier_id', 'priority']);
            $table->index(['supplier_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_recommendations');
        Schema::dropIfExists('analytics_performance_metrics');
        Schema::dropIfExists('analytics_visitor_logs');
        Schema::dropIfExists('analytics_search_logs');
        Schema::dropIfExists('analytics_views_history');
    }
};
