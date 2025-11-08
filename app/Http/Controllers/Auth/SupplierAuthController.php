<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierProfile;
use App\Models\Branch;
use App\Models\Otp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Cache;

class SupplierAuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:suppliers'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'referral_code' => ['nullable', 'string']
        ]);

        $supplier = Supplier::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'email_verified' => false,
            'referral_code' => $request->referral_code
        ]);

        $profile = SupplierProfile::create([
            'supplier_id' => $supplier->id,
            'business_name' => $request->business_name,
            'main_phone' => $request->phone
        ]);

        return response()->json([
            'message' => 'Registration successful',
            'supplier' => $supplier,
            'profile' => $profile
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $supplier = Supplier::where('email', $request->email)->first();

        if (!$supplier || !Hash::check($request->password, $supplier->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!$supplier->email_verified) {
            return response()->json([
                'message' => 'Email not verified',
                'email_verified' => false
            ], 403);
        }

        $token = $supplier->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'supplier' => $supplier,
            'profile' => $supplier->profile
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:suppliers,email'],
        ]);

        $otp = Otp::generateFor($request->email);
        
        // Send OTP via email
        Mail::raw("Your verification code is: {$otp->code}", function ($message) use ($request) {
            $message->to($request->email)
                   ->subject('Email Verification OTP');
        });

        if (app()->environment('local', 'testing')) {
            return response()->json([
                'message' => 'OTP sent successfully',
                'otp' => $otp->code  // Only in non-production
            ]);
        }

        return response()->json([
            'message' => 'OTP sent successfully'
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:suppliers,email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $otp = Otp::where('email', $request->email)
                  ->latest()
                  ->first();

        if (!$otp || !$otp->isValid() || $otp->code !== $request->otp) {
            return response()->json([
                'message' => 'Invalid or expired OTP'
            ], 400);
        }

        $supplier = Supplier::where('email', $request->email)->first();
        $supplier->email_verified = true;
        $supplier->save();

        // Delete used OTP
        $otp->delete();

        // Generate token for automatic login
        $token = $supplier->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Email verified successfully',
            'token' => $token,
            'supplier' => $supplier,
            'profile' => $supplier->profile
        ]);
    }

    public function updateProfile(Request $request)
    {
        $supplier = $request->user();
        
        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'business_name' => ['sometimes', 'string', 'max:255'],
            'business_type' => ['sometimes', 'string', 'max:50'],
            'service_distance' => ['sometimes', 'numeric', 'min:0'],
            'location' => ['sometimes', 'string'],
        ]);

        if ($request->has('name') || $request->has('phone')) {
            $supplier->update($request->only(['name', 'phone']));
        }

        if ($request->hasAny(['business_name', 'business_type', 'service_distance', 'location'])) {
            $supplier->profile->update(
                $request->only(['business_name', 'business_type', 'service_distance', 'location'])
            );
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'supplier' => $supplier->fresh(['profile'])
        ]);
    }
}
