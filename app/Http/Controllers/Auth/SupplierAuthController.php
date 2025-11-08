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
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SupplierAuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:suppliers'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'referral_code' => ['nullable', 'string']
        ]);

        // Prevent using an email already registered as a buyer
        if (\App\Models\User::where('email', $request->email)->exists()) {
            return response()->json([
                'message' => 'Email is already registered as a buyer.'
            ], 422);
        }

        $supplier = Supplier::create([
            'name' => $request->business_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            // use email_verified_at to be compatible with unified verification column
            'email_verified_at' => null,
            // default profile image
            'profile_image' => 'uploads/default.png',
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

        if (!$supplier->email_verified_at) {
            return response()->json([
                'message' => 'Email not verified',
                'email_verified' => false
            ], 403);
        }

        $token = $supplier->createToken('auth-token')->plainTextToken;

        // Load the supplier with profile
        $supplier->load('profile');

        return response()->json([
            'message' => 'Login successful',
            'user' => $supplier,
            'access_token' => $token,
            'token_type' => 'Bearer'
        ]);
    }

    public function logout(Request $request)
    {
        // Try deleting currentAccessToken
        try {
            if ($request->user() && method_exists($request->user(), 'currentAccessToken')) {
                $token = $request->user()->currentAccessToken();
                if ($token && method_exists($token, 'delete')) {
                    $token->delete();
                    return response()->json(['message' => 'Logged out successfully']);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Fallback: delete by bearer token
        $bearer = $request->bearerToken();
        if ($bearer) {
            $pat = PersonalAccessToken::findToken($bearer);
            if ($pat) {
                $pat->delete();
                return response()->json(['message' => 'Logged out successfully']);
            }
        }

        return response()->json(['message' => 'Logged out successfully']);
    }

  public function sendOtp(Request $request)
{
    $request->validate([
        'email' => ['required', 'email'],
    ]);

    // أولاً نحاول نلاقي الـ User
    $user = \App\Models\User::where('email', $request->email)->first();
    if ($user) {
        $otp = Otp::generateForUser($user->id);
    } else {
        // لو مش موجود في Users، نجرب الـ Supplier
        $supplier = \App\Models\Supplier::where('email', $request->email)->first();
        if (!$supplier) {
            return response()->json([
                'message' => 'Email not found in users or suppliers.'
            ], 404);
        }
        $otp = Otp::generateForSupplier($supplier->id);
    }

    // إرسال الإيميل
    Mail::raw("Your verification code is: {$otp->otp}", function ($message) use ($request) {
        $message->to($request->email)
                ->subject('Email Verification OTP');
    });

    // للبيئة المحلية أو الاختبارات، نرجع OTP
    if (app()->environment('local', 'testing')) {
        return response()->json([
            'message' => 'OTP sent successfully',
            'otp' => $otp->otp
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

        $supplier = \App\Models\Supplier::where('email', $request->email)->first();

        $otp = Otp::where('user_id', $supplier->id)
                  ->where('otp', $request->otp)
                  ->where('expires_at', '>', now())
                  ->first();

        if (!$otp || !$otp->isValid()) {
            return response()->json([
                'message' => 'Invalid or expired OTP'
            ], 400);
        }

        $supplier = Supplier::where('email', $request->email)->first();
        $supplier->email_verified_at = now();
        $supplier->save();

        // Delete used OTP
        $otp->delete();

        // Generate token for automatic login
        $token = $supplier->createToken('auth-token')->plainTextToken;

        // Load the supplier with profile
        $supplier->load('profile');

        return response()->json([
            'message' => 'Email verified successfully',
            'user' => $supplier,
            'access_token' => $token,
            'token_type' => 'Bearer'
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
            'business_categories' => ['sometimes', 'array'],
            'keywords' => ['sometimes', 'array'],
            'target_market' => ['sometimes', 'array'],
            'services_offered' => ['sometimes', 'array'],
            'website' => ['sometimes', 'string', 'nullable'],
            'additional_phones' => ['sometimes', 'array'],
            'business_address' => ['sometimes', 'string', 'nullable'],
            'latitude' => ['sometimes', 'numeric', 'nullable'],
            'longitude' => ['sometimes', 'numeric', 'nullable'],
            'working_hours' => ['sometimes', 'array', 'nullable'],
            'has_branches' => ['sometimes', 'boolean']
        ]);

        // Update supplier fields
        $supplierFields = array_filter($request->only(['name', 'phone']));
        if (!empty($supplierFields)) {
            $supplier->update($supplierFields);
        }

        // Get all profile fields that might be updated
        $profileFields = array_filter($request->only([
            'business_name',
            'business_type',
            'service_distance',
            'business_categories',
            'keywords',
            'target_market',
            'services_offered',
            'website',
            'additional_phones',
            'business_address',
            'latitude',
            'longitude',
            'working_hours',
            'has_branches'
        ]));

        // Update profile if we have any fields to update
        if (!empty($profileFields)) {
            $supplier->profile->update($profileFields);
        }

        // Reload supplier with fresh profile data
        $supplier->load('profile');

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $supplier
        ]);
    }

    public function updateProfileImage(Request $request)
    {
        $request->validate([
            'profile_image' => 'required|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        $supplier = $request->user();

        $destDir = public_path('uploads/suppliers');
        if (!File::exists($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        if ($supplier->profile_image) {
            $existing = public_path($supplier->profile_image);
            if (File::exists($existing)) {
                File::delete($existing);
            }
        }

        $file = $request->file('profile_image');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->move($destDir, $filename);

        $supplier->profile_image = 'uploads/suppliers/' . $filename;
        $supplier->save();

        // reload profile relation
        $supplier->load('profile');

        return response()->json([
            'message' => 'Profile image updated successfully',
            'user' => $supplier
        ]);
    }
}
