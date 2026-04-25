<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierRating extends Model
{
    protected $fillable = [
        'rater_supplier_id',
        'rated_supplier_id',
        'score',
        'comment',
        'reviewer_name',
        'reviewer_email',
        'is_approved',
        'status',
        'type',
        'is_read',
        'moderated_by_admin_id',
        'moderated_at',
        'moderation_notes',
        'flagged_by_admin_id',
        'flagged_at',
    ];

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
            'is_read' => 'boolean',
            'moderated_at' => 'datetime',
            'flagged_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'type' => 'review',
    ];

    public function rater()
    {
        return $this->belongsTo(Supplier::class, 'rater_supplier_id');
    }

    public function rated()
    {
        return $this->belongsTo(Supplier::class, 'rated_supplier_id');
    }

    public function moderatedBy()
    {
        return $this->belongsTo(Admin::class, 'moderated_by_admin_id');
    }

    public function flaggedBy()
    {
        return $this->belongsTo(Admin::class, 'flagged_by_admin_id');
    }

    public function replies()
    {
        return $this->hasMany(ReviewReply::class, 'supplier_rating_id');
    }

    public function reply()
    {
        return $this->hasOne(ReviewReply::class, 'supplier_rating_id');
    }
}
