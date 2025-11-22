<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_name',
        'category',
        'business_type',
        'description',
        'phone',
        'email',
        'website',
        'location',
        'services',
        'target_customers',
        'product_keywords',
        'working_hours',
        'additional_phones',
        'branches',
        'rating',
        'reviews_count',
        'years_in_business',
        'clients_served',
        'specialization',
        'service_distance',
        'verified',
        'badge',
        'features',
        'images',
        'gallery_images',
        'user_id',
        'status',
    ];

    protected $casts = [
        'location' => 'array',
        'services' => 'array',
        'target_customers' => 'array',
        'product_keywords' => 'array',
        'working_hours' => 'array',
        'additional_phones' => 'array',
        'branches' => 'array',
        'features' => 'array',
        'images' => 'array',
        'gallery_images' => 'array',
        'verified' => 'boolean',
        'rating' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(BusinessReview::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(BusinessImage::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeVerified($query)
    {
        return $query->where('verified', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('business_type', $type);
    }

    public function scopeNearLocation($query, $lat, $lng, $radius = 50)
    {
        // Haversine formula for distance calculation
        return $query->selectRaw(
            '*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance',
            [$lat, $lng, $lat]
        )->having('distance', '<=', $radius);
    }
}
