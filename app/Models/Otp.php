<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    protected $fillable = [
        'email',
        'code',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime'
    ];

    public static function generateFor($email)
    {
        // Delete any existing OTPs for this email
        self::where('email', $email)->delete();

        // Generate new OTP
        $otp = self::create([
            'email' => $email,
            'code' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'expires_at' => now()->addMinutes(10)
        ]);

        return $otp;
    }

    public function isValid()
    {
        return !$this->expires_at->isPast();
    }
}
