<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'business_name',
        'plan',
        'status',
        'location',
        'notification_settings',
        'last_active',
        'profile_completion',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'location' => 'array',
        'notification_settings' => 'array',
        'last_active' => 'datetime',
        'profile_completion' => 'integer',
    ];

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(BusinessReview::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByPlan($query, $plan)
    {
        return $query->where('plan', $plan);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function canAddBusiness(): bool
    {
        $maxBusinesses = $this->getMaxBusinesses();
        $currentCount = $this->businesses()->count();

        return $currentCount < $maxBusinesses;
    }

    public function getMaxBusinesses(): int
    {
        return match ($this->plan) {
            'Basic' => 8,
            'Premium' => 15,
            'Enterprise' => 50,
            default => 8
        };
    }

    public function getRemainingBusinesses(): int
    {
        return $this->getMaxBusinesses() - $this->businesses()->count();
    }
}
