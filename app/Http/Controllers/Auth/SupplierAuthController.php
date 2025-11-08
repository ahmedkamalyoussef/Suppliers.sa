<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierProfile;
use App\Models\Otp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
            return response()->json(['message' => 'Email is already registered as a buyer.'], 422);
        }

        $supplier = Supplier::create([
            'name' => $request->business_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'email_verified_at' => null,
            'profile_image' => 'uploads/default.png',
            'referral_code' => $request->referral_code
        ]);

        $profile = SupplierProfile::create([
            'supplier_id' => $supplier->id,
            'business_name' => $request->business_name,
            'main_phone' => $request->phone
        ]);

        $supplier->load('profile');

        return response()->json([
            'message' => 'Registration successful',
            'user' => $supplier
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);

        $supplier = Supplier::where('email', $request->email)->first();

        if (!$supplier || !Hash::check($request->password, $supplier->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!$supplier->email_verified_at) {
            return response()->json(['message' => 'Email not verified', 'email_verified' => false], 403);
        }

        $token = $supplier->createToken('auth-token')->plainTextToken;
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
        $bearer = $request->bearerToken();
        if ($bearer) {
            $pat = PersonalAccessToken::findToken($bearer);
            if ($pat) $pat->delete();
        }
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function sendOtp(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = \App\Models\User::where('email', $request->email)->first();
        if ($user) {
            $otp = Otp::generateForUser($user->id);
        } else {
            $supplier = Supplier::where('email', $request->email)->first();
            if (!$supplier) return response()->json(['message' => 'Email not found'], 404);
            $otp = Otp::generateForSupplier($supplier->id);
        }

        Mail::raw("Your verification code is: {$otp->otp}", function ($message) use ($request) {
            $message->to($request->email)->subject('Email Verification OTP');
        });

        if (app()->environment('local', 'testing')) {
            return response()->json(['message' => 'OTP sent', 'otp' => $otp->otp]);
        }

        return response()->json(['message' => 'OTP sent successfully']);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:suppliers,email'],
            'otp' => ['required', 'string', 'size:6']
        ]);

        $supplier = Supplier::where('email', $request->email)->first();
        $otp = Otp::where('user_id', $supplier->id)
                  ->where('otp', $request->otp)
                  ->where('expires_at', '>', now())
                  ->first();

        if (!$otp || !$otp->isValid()) return response()->json(['message' => 'Invalid or expired OTP'], 400);

        $supplier->email_verified_at = now();
        $supplier->save();

        $otp->delete();

        $token = $supplier->createToken('auth-token')->plainTextToken;
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
            'name' => ['sometimes','string','max:255'],
            'phone' => ['sometimes','string','max:20'],
            'business_name' => ['sometimes','string','max:255'],
            'business_type' => ['sometimes','string','max:50'],
            'service_distance' => ['sometimes','numeric','min:0'],
            'business_categories' => ['sometimes','array'],
            'keywords' => ['sometimes','array'],
            'target_market' => ['sometimes','array'],
            'services_offered' => ['sometimes','array'],
            'website' => ['sometimes','string','nullable'],
            'additional_phones' => ['sometimes','array'],
            'business_address' => ['sometimes','string','nullable'],
            'latitude' => ['sometimes','numeric','nullable'],
            'longitude' => ['sometimes','numeric','nullable'],
            'working_hours' => ['sometimes','array','nullable'],
            'has_branches' => ['sometimes','boolean']
        ]);

        $supplierFields = array_filter($request->only(['name','phone']));
        if ($supplierFields) $supplier->update($supplierFields);

        $profileFields = array_filter($request->only([
            'business_name','business_type','service_distance','business_categories','keywords',
            'target_market','services_offered','website','additional_phones','business_address',
            'latitude','longitude','working_hours','has_branches'
        ]));
        if ($profileFields) $supplier->profile->update($profileFields);

        $supplier->load('profile');

        return response()->json(['message'=>'Profile updated successfully','user'=>$supplier]);
    }

    public function updateProfileImage(Request $request)
    {
        $request->validate(['profile_image'=>'required|image|mimes:jpeg,png,jpg|max:2048']);

        $supplier = $request->user();
        $destDir = public_path('uploads/suppliers');

        if (!File::exists($destDir)) File::makeDirectory($destDir, 0755, true);

        if ($supplier->profile_image && File::exists(public_path($supplier->profile_image))) {
            File::delete(public_path($supplier->profile_image));
        }

        $file = $request->file('profile_image');
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $file->move($destDir, $filename);

        $supplier->profile_image = 'uploads/suppliers/'.$filename;
        $supplier->save();

        $supplier->load('profile');

        return response()->json(['message'=>'Profile image updated successfully','user'=>$supplier]);
    }
}
