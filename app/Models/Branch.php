<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'manager_name',
        'latitude',
        'longitude',
        'working_hours',
        'special_services',
        'status',
        'is_main_branch'
    ];

    protected $casts = [
        'working_hours' => 'array',
        'special_services' => 'array',
        'latitude' => 'decimal:6',
        'longitude' => 'decimal:6',
        'is_main_branch' => 'boolean'
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
