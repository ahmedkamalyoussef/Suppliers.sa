<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewReply extends Model
{
    protected $fillable = [
        'supplier_rating_id',
        'supplier_id',
        'reply',
        'type',
    ];

    protected $attributes = [
        'type' => 'reviewReply',
    ];

    public function rating(): BelongsTo
    {
        return $this->belongsTo(SupplierRating::class, 'supplier_rating_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
