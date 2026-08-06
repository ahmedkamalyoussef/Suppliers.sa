<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'sender_supplier_id',
        'sender_email',
        'receiver_supplier_id',
        'receiver_email',
        'subject',
        'message',
        'type',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    protected $attributes = [
        'type' => 'message',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'sender_supplier_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'receiver_supplier_id');
    }
}
