<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'profile_image',
        'plan',
        'status',
        'last_seen_at',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function profile()
    {
        return $this->hasOne(SupplierProfile::class);
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function ratingsGiven()
    {
        return $this->hasMany(SupplierRating::class, 'rater_supplier_id');
    }

    public function ratings()
    {
        return $this->hasMany(SupplierRating::class, 'rated_supplier_id');
    }
    
    public function ratingsReceived()
    {
        return $this->hasMany(SupplierRating::class, 'rated_supplier_id');
    }

    public function approvedRatings()
    {
        return $this->hasMany(SupplierRating::class, 'rated_supplier_id')->where('is_approved', true);
    }

    public function inquiries()
    {
        return $this->hasMany(SupplierInquiry::class);
    }

    public function documents()
    {
        return $this->hasMany(SupplierDocument::class);
    }

    public function reportsReceived()
    {
        return $this->hasMany(ContentReport::class, 'target_supplier_id');
    }

    public function reportsSubmitted()
    {
        return $this->hasMany(ContentReport::class, 'reported_by_supplier_id');
    }

    public function productImages(): HasMany
    {
        return $this->hasMany(SupplierProductImage::class);
    }
    
    /**
     * Get the products for the supplier.
     */
    public function products(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(SupplierService::class);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(SupplierCertification::class);
    }
}
