<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierToSupplierInquiry extends Model
{
    protected $fillable = [
        'sender_supplier_id',
        'receiver_supplier_id',
        'sender_name',
        'company',
        'email',
        'phone',
        'subject',
        'message',
        'parent_id',
        'is_read',
        'type'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'type' => 'string'
    ];
    
    protected $attributes = [
        'type' => 'inquiry'
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'sender_supplier_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'receiver_supplier_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(SupplierToSupplierInquiry::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupplierToSupplierInquiry::class, 'parent_id');
    }
}
