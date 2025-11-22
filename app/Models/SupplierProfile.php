<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierProfile extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'business_categories' => 'array',
        'keywords' => 'array',
        'target_market' => 'array',
        'services_offered' => 'array',
        'additional_phones' => 'array',
        'working_hours' => 'array',
        'has_branches' => 'boolean',
        'service_distance' => 'string',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
