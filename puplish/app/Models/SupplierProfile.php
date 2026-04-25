<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'slug',
        'business_name',
        'business_type',
        'description',
        'website',
        'main_phone',
        'business_address',
        'latitude',
        'longitude',
        'contact_email',
        'business_categories',
        'keywords',
        'target_market',
        'services_offered',
        'additional_phones',
        'working_hours',
        'has_branches',
        'service_distance',
        'category',
        'business_image'
    ];

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
