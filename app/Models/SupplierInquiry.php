<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierInquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'full_name',
        'email_address',
        'phone_number',
        'subject',
        'message',
        'is_read',
        'from',
        'admin_id',
        'type',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    protected $attributes = [
        'type' => 'inquiry',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    // Helper method to check if inquiry is unread
    public function isUnread(): bool
    {
        return !$this->is_read;
    }

    // Helper method to mark as read
    public function markAsRead(): bool
    {
        return $this->update(['is_read' => true]);
    }

    // Helper method to assign to admin
    public function assignToAdmin(Admin $admin): bool
    {
        return $this->update(['admin_id' => $admin->id]);
    }
}
