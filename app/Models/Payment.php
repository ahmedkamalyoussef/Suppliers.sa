<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tap_id',
        'amount',
        'currency',
        'status',
        'is_paid',
        'raw_response',
        'order_id',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_paid' => 'boolean',
        'raw_response' => 'array',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markAsPaid()
    {
        $this->update([
            'status' => 'CAPTURED',
            'is_paid' => true,
            'paid_at' => now(),
        ]);
    }

    public function markAsFailed()
    {
        $this->update([
            'status' => 'FAILED',
            'is_paid' => false,
        ]);
    }

    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'INITIATED');
    }
}
