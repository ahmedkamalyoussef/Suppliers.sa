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
        'is_approved',
    ];

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
        ];
    }

    public function rater()
    {
        return $this->belongsTo(Supplier::class, 'rater_supplier_id');
    }

    public function rated()
    {
        return $this->belongsTo(Supplier::class, 'rated_supplier_id');
    }
}
