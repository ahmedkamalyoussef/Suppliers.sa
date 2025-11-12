<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    protected $fillable = [
        'user_id',
        'admin_id',
        'supplier_id',
        'otp',
        'expires_at',
        'email'
    ];

    protected $casts = [
        'expires_at' => 'datetime'
    ];

    public static function generateForUser($userId, $email = null)
    {
        // Delete any existing OTPs for this user
        self::where('user_id', $userId)->delete();

        // Generate new OTP for user
        $otp = self::create([
            'user_id' => $userId,
            'supplier_id' => null,
            'email' => $email,
            'otp' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'expires_at' => now()->addMinutes(10)
        ]);

        return $otp;
    }

    public static function generateForAdmin($adminId, $email = null)
    {
        // Delete any existing OTPs for this admin
        self::where('admin_id', $adminId)->delete();

        // Generate new OTP for admin
        $otp = self::create([
            'user_id' => null,
            'admin_id' => $adminId,
            'supplier_id' => null,
            'email' => $email,
            'otp' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'expires_at' => now()->addMinutes(10)
        ]);

        return $otp;
    }

    public static function generateForSupplier($supplierId, $email = null)
    {
        // Delete any existing OTPs for this supplier
        self::where('supplier_id', $supplierId)->delete();

        // Generate new OTP for supplier
        $otp = self::create([
            'user_id' => null,
            'admin_id' => null,
            'supplier_id' => $supplierId,
            'email' => $email,
            'otp' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'expires_at' => now()->addMinutes(10)
        ]);

        return $otp;
    }

    public function isValid()
    {
        return !$this->expires_at->isPast();
    }
}
