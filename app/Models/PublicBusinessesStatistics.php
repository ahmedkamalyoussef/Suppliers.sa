<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicBusinessesStatistics extends Model
{
    protected $fillable = [
        'verified_businesses',
        'successful_connections',
        'average_rating',
    ];

    protected $casts = [
        'verified_businesses' => 'integer',
        'successful_connections' => 'integer',
        'average_rating' => 'double',
    ];
}
