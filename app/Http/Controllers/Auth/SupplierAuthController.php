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
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SupplierAuthController extends Controller
{
    public function register(Request $request)
    {
        if (!$request->has('business_name') && $request->filled('businessName')) {
            $request->merge(['business_name' => $request->input('businessName')]);
        }

        if (!$request->has('password_confirmation') && $request->filled('passwordConfirmation')) {
            $request->merge(['password_confirmation' => $request->input('passwordConfirmation')]);
        }

        $validator = Validator::make($request->all(), [
            'business_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('suppliers', 'email'),
                Rule::unique('admins', 'email'),
            ],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $password = $request->filled('password') ? $request->password : Str::random(12);

        $supplier = Supplier::create([
            'name' => $request->business_name,
            'email' => $request->email,
            'password' => Hash::make($password),
            'phone' => $request->phone,
            'email_verified_at' => null,
            'profile_image' => 'uploads/default.png',
        ]);

        $profile = SupplierProfile::create([
            'supplier_id' => $supplier->id,
            'business_name' => $request->business_name,
            'main_phone' => $request->phone,
            'contact_email' => $request->email,
        ]);

        $otp = Otp::generateForSupplier($supplier->id, $supplier->email);
        Mail::raw("Your verification code is: {$otp->otp}", function ($message) use ($supplier) {
            $message->to($supplier->email)->subject('Email Verification OTP');
        });

        $supplier->load('profile');

        $response = [
            'message' => 'Registration successful. Please verify your email.',
            'supplier' => $this->transformSupplier($supplier, false),
            'generatedPassword' => $request->filled('password') ? null : $password,
        ];

        if (app()->environment('local', 'testing')) {
            $response['debugOtp'] = $otp->otp;
        }

        return response()->json($response, 201);
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
            'supplier' => $this->transformSupplier($supplier),
            'accessToken' => $token,
            'tokenType' => 'Bearer',
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

        $supplier = Supplier::where('email', $request->email)->first();
        if (!$supplier) {
            return response()->json(['message' => 'Email not found'], 404);
        }

        $otp = Otp::generateForSupplier($supplier->id, $supplier->email);

        Mail::raw("Your verification code is: {$otp->otp}", function ($message) use ($supplier) {
            $message->to($supplier->email)->subject('Email Verification OTP');
        });

        $response = ['message' => 'OTP sent successfully'];

        if (app()->environment('local', 'testing')) {
            $response['debugOtp'] = $otp->otp;
        }

        return response()->json($response);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:suppliers,email'],
            'otp' => ['required', 'string', 'size:6']
        ]);

        $supplier = Supplier::where('email', $request->email)->first();
        $otp = Otp::where('supplier_id', $supplier->id)
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
            'supplier' => $this->transformSupplier($supplier),
            'accessToken' => $token,
            'tokenType' => 'Bearer',
        ]);
    }

    public function updateProfile(Request $request)
    {
        $supplier = $request->user();

        if (!($supplier instanceof Supplier)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'businessName' => ['sometimes', 'string', 'max:255'],
            'businessType' => ['sometimes', 'string', 'max:100'],
            'category' => ['sometimes', 'string', 'max:255'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['string', 'max:255'],
            'services' => ['sometimes', 'array'],
            'services.*' => ['string', 'max:255'],
            'productKeywords' => ['sometimes', 'array'],
            'productKeywords.*' => ['string', 'max:255'],
            'targetCustomers' => ['sometimes', 'array'],
            'targetCustomers.*' => ['string', 'max:255'],
            'serviceDistance' => ['sometimes', 'numeric', 'min:0'],
            'additionalPhones' => ['sometimes', 'array'],
            'workingHours' => ['sometimes', 'array'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'contactEmail' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('suppliers', 'email')->ignore($supplier->id),
                Rule::unique('admins', 'email'),
            ],
            'contactPhone' => ['sometimes', 'string', 'max:20'],
            'mainPhone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'location.lat' => ['sometimes', 'numeric'],
            'location.lng' => ['sometimes', 'numeric'],
            'hasBranches' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ($request->filled('name')) {
            $supplier->name = $request->name;
        } elseif ($request->filled('businessName')) {
            $supplier->name = $request->businessName;
        }

        if ($request->filled('contactPhone')) {
            $supplier->phone = $request->contactPhone;
        }

        if ($request->filled('contactEmail')) {
            $supplier->email = $request->contactEmail;
        }

        $supplier->save();

        $profile = $supplier->profile ?: SupplierProfile::create(['supplier_id' => $supplier->id]);

        $profileData = [];

        if ($request->has('businessName')) {
            $profileData['business_name'] = $request->businessName;
        }

        if ($request->has('businessType')) {
            $profileData['business_type'] = $request->businessType;
        }

        if ($request->has('categories') || $request->has('category')) {
            $categories = $request->input('categories', []);
            if (!$categories && $request->filled('category')) {
                $categories = [$request->category];
            }
            $profileData['business_categories'] = $categories;
        }

        if ($request->has('services')) {
            $profileData['services_offered'] = $request->input('services', []);
        }

        if ($request->has('productKeywords')) {
            $profileData['keywords'] = $request->input('productKeywords', []);
        }

        if ($request->has('targetCustomers')) {
            $profileData['target_market'] = $request->input('targetCustomers', []);
        }

        if ($request->has('serviceDistance')) {
            $profileData['service_distance'] = $request->serviceDistance;
        }

        if ($request->has('website')) {
            $profileData['website'] = $request->website;
        }

        if ($request->has('address')) {
            $profileData['business_address'] = $request->address;
        }

        if ($request->has('mainPhone')) {
            $profileData['main_phone'] = $request->mainPhone;
        } elseif ($request->has('contactPhone')) {
            $profileData['main_phone'] = $request->contactPhone;
        }

        if ($request->has('contactEmail')) {
            $profileData['contact_email'] = $request->contactEmail;
        }

        if ($request->has('additionalPhones')) {
            $profileData['additional_phones'] = $request->input('additionalPhones', []);
        }

        if ($request->has('workingHours')) {
            $profileData['working_hours'] = $request->input('workingHours', []);
        }

        if ($request->has('description')) {
            $profileData['description'] = $request->description;
        }

        if ($request->has('hasBranches')) {
            $profileData['has_branches'] = (bool) $request->hasBranches;
        }

        if ($request->has('location')) {
            $location = $request->input('location', []);
            $profileData['latitude'] = data_get($location, 'lat');
            $profileData['longitude'] = data_get($location, 'lng');
        }

        if (!empty($profileData)) {
            $profile->update($profileData);
        }

        $supplier->load('profile', 'branches');

        return response()->json([
            'message' => 'Profile updated successfully',
            'supplier' => $this->transformSupplier($supplier),
        ]);
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

        return response()->json([
            'message' => 'Profile image updated successfully',
            'supplier' => $this->transformSupplier($supplier, false),
        ]);
    }
}
