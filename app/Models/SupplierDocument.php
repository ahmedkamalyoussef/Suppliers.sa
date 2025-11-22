<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'file_path',
    ];

    protected $casts = [];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    // Reviewer relationship removed with metadata columns
}
