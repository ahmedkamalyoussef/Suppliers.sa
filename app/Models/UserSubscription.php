<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'status',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'tap_charge_id',
        'paid_amount',
        'currency',
        'auto_renew',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'paid_amount' => 'decimal:2',
        'auto_renew' => 'boolean',
    ];

    /**
     * Get the user that owns the subscription
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription plan
     */
    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    /**
     * Get payment transactions for this subscription
     */
    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /**
     * Scope to get active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('ends_at', '>', now());
    }

    /**
     * Scope to get expired subscriptions
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired')
                    ->orWhere('ends_at', '<=', now());
    }

    /**
     * Check if subscription is currently active
     */
    public function isActive()
    {
        return $this->status === 'active' && $this->ends_at->isFuture();
    }

    /**
     * Check if subscription is expired
     */
    public function isExpired()
    {
        return $this->status === 'expired' || $this->ends_at->isPast();
    }

    /**
     * Get remaining days
     */
    public function getRemainingDaysAttribute()
    {
        if ($this->isActive()) {
            return now()->diffInDays($this->ends_at);
        }
        return 0;
    }

    /**
     * Get status in Arabic
     */
    public function getStatusTextAttribute()
    {
        switch ($this->status) {
            case 'active':
                return 'نشط';
            case 'expired':
                return 'منتهي';
            case 'cancelled':
                return 'ملغي';
            case 'pending':
                return 'في الانتظار';
            default:
                return $this->status;
        }
    }
}
