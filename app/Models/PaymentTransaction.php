<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'tap_charge_id',
        'tap_refund_id',
        'type',
        'status',
        'amount',
        'currency',
        'refunded_amount',
        'tap_response',
        'description',
        'metadata',
        'paid_at',
        'refunded_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'tap_response' => 'array',
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    /**
     * Get the user that owns the transaction
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
     * Get user subscription for this transaction
     */
    public function userSubscription()
    {
        return $this->hasOne(UserSubscription::class, 'tap_charge_id', 'tap_charge_id');
    }

    /**
     * Scope to get completed transactions
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get failed transactions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get refunded transactions
     */
    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    /**
     * Scope to get transactions by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if transaction is successful
     */
    public function isSuccessful()
    {
        return $this->status === 'completed';
    }

    /**
     * Check if transaction is refunded
     */
    public function isRefunded()
    {
        return $this->status === 'refunded' || $this->refunded_amount > 0;
    }

    /**
     * Get formatted amount
     */
    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }

    /**
     * Get status in Arabic
     */
    public function getStatusTextAttribute()
    {
        switch ($this->status) {
            case 'pending':
                return 'في الانتظار';
            case 'completed':
                return 'مكتمل';
            case 'failed':
                return 'فشل';
            case 'refunded':
                return 'مسترد';
            case 'cancelled':
                return 'ملغي';
            default:
                return $this->status;
        }
    }

    /**
     * Get type in Arabic
     */
    public function getTypeTextAttribute()
    {
        switch ($this->type) {
            case 'subscription':
                return 'اشتراك';
            case 'refund':
                return 'استرداد';
            case 'renewal':
                return 'تجديد';
            default:
                return $this->type;
        }
    }
}
