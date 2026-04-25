<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'customer_name',
        'customer_email',
        'rating',
        'title',
        'comment',
        'review_status',
        'helpful_count',
        'verified',
        'submission_date',
        'approval_date',
    ];

    protected $casts = [
        'rating' => 'integer',
        'verified' => 'boolean',
        'submission_date' => 'datetime',
        'approval_date' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('review_status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('review_status', 'pending_approval');
    }

    public function scopeByRating($query, $rating)
    {
        return $query->where('rating', $rating);
    }

    public function markHelpful()
    {
        $this->increment('helpful_count');
    }
}
