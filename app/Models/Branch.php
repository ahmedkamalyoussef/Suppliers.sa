<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'service_distance',
        'is_main_branch'
    ];

    protected $casts = [
        'service_distance' => 'decimal:2',
        'is_main_branch' => 'boolean'
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
