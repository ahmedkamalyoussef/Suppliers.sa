<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessRequest extends Model
{
    protected $fillable = [
        'appearance',
        'industry',
        'preferred_distance',
        'description',
        'supplier_id',
    ];

    protected $casts = [
        'appearance' => 'string',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($businessRequest) {
            $supplier = $businessRequest->supplier;
            
            if (!$supplier || $supplier->plan === 'Basic') {
                throw new \Exception('Only suppliers with non-basic plans can create business requests');
            }
        });
    }
}
