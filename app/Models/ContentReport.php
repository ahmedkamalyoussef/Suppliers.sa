<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'target_supplier_id',
        'reported_by_supplier_id',
        'report_type',
        'target_type',
        'target_id',
        'status',
        'reason',
        'details',
        'reported_by_name',
        'reported_by_email',
        'handled_by_admin_id',
        'handled_at',
        'resolution_notes',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
    ];

    public function targetSupplier()
    {
        return $this->belongsTo(Supplier::class, 'target_supplier_id');
    }

    public function reporter()
    {
        return $this->belongsTo(Supplier::class, 'reported_by_supplier_id');
    }

    public function handler()
    {
        return $this->belongsTo(Admin::class, 'handled_by_admin_id');
    }
}

