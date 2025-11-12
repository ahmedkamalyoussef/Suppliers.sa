<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Supplier extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'profile_image'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime'
    ];

    public function profile()
    {
        return $this->hasOne(SupplierProfile::class);
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function ratingsGiven()
    {
        return $this->hasMany(SupplierRating::class, 'rater_supplier_id');
    }

    public function ratingsReceived()
    {
        return $this->hasMany(SupplierRating::class, 'rated_supplier_id');
    }
}
