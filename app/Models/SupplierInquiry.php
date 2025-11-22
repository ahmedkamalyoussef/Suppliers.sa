<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierInquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'name',
        'email',
        'phone',
        'company',
        'subject',
        'message',
        'status',
        'is_unread',
        'last_response',
        'last_response_at',
        'handled_by_admin_id',
        'handled_at',
    ];

    protected $casts = [
        'is_unread' => 'boolean',
        'last_response_at' => 'datetime',
        'handled_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function handledBy()
    {
        return $this->belongsTo(Admin::class, 'handled_by_admin_id');
    }
}
