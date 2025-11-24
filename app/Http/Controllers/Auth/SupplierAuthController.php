<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\UpdateSupplierProfileRequest;
use App\Http\Resources\Supplier\SupplierResource;
use App\Models\Otp;
use App\Models\Supplier;
use App\Models\SupplierProfile;
use App\Notifications\OtpNotification;
use App\Services\SupplierProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;


class SupplierAuthController extends Controller
{
    public function register(Request $request)
    {
        if (! $request->has('business_name') && $request->filled('businessName')) {
            $request->merge(['business_name' => $request->input('businessName')]);
        }

        if (! $request->has('password_confirmation') && $request->filled('passwordConfirmation')) {
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
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $supplier = Supplier::create([
            'name' => $request->business_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'email_verified_at' => null,
            'profile_image' => 'uploads/default.png',
            'plan' => 'Basic',
            'status' => 'pending',
            'role' => 'supplier', // Add default role
        ]);

        $profile = SupplierProfile::create([
            'supplier_id' => $supplier->id,
            'business_name' => $request->business_name,
            'main_phone' => $request->phone,
            'contact_email' => $request->email,
            'slug' => null,
        ]);

        $profile->slug = $this->generateUniqueSupplierSlug($profile->business_name, $profile->id);
        $profile->save();

        $supplier->load('profile');

        $response = [
            'message' => 'Registration successful. Please verify your email.',
            'supplier' => (new SupplierResource($supplier))->toArray($request),
        ];

        return response()->json($response, 201);
    }

    public function login(Request $request)
    {
        $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);

        $supplier = Supplier::where('email', $request->email)->first();

        if (! $supplier || ! Hash::check($request->password, $supplier->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (! $supplier->email_verified_at) {
            return response()->json(['message' => 'Email not verified', 'email_verified' => false], 403);
        }

        $token = $supplier->createToken('auth-token')->plainTextToken;
        $supplier->forceFill(['last_seen_at' => now()])->save();
        $supplier->load('profile');

        $supplierArray = (new SupplierResource($supplier))->toArray($request);
        $supplierArray['role'] = 'supplier'; // Add role to the response
        
        return response()->json([
            'message' => 'Login successful',
            'supplier' => $supplierArray,
            'accessToken' => $token,
            'tokenType' => 'Bearer',
            'role' => 'supplier', // Also add role at the root level for easier access
        ]);
    }

    public function logout(Request $request)
    {
        $bearer = $request->bearerToken();
        if ($bearer) {
            $pat = PersonalAccessToken::findToken($bearer);
            if ($pat) {
                $pat->delete();
            }
        }

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function sendOtp(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $supplier = Supplier::where('email', $request->email)->first();
        if (! $supplier) {
            return response()->json(['message' => 'Email not found'], 404);
        }

        $otp = Otp::generateForSupplier($supplier->id, $supplier->email);
        $supplier->notify(new OtpNotification($otp->otp, 10));

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
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $supplier = Supplier::where('email', $request->email)->first();
        $otp = Otp::where('supplier_id', $supplier->id)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if (! $otp || ! $otp->isValid()) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        $supplier->email_verified_at = now();
        $supplier->status = 'active';
        $supplier->save();

        $otp->delete();

        $token = $supplier->createToken('auth-token')->plainTextToken;
        $supplier->load('profile');

        $supplierArray = (new SupplierResource($supplier))->toArray($request);
        $supplierArray['role'] = 'supplier';
        
        return response()->json([
            'message' => 'Email verified successfully',
            'supplier' => $supplierArray,
            'accessToken' => $token,
            'tokenType' => 'Bearer',
            'role' => 'supplier',
        ]);
    }

    public function updateProfile(Request $request)
    {
        return $this->updateProfilePartial($request);
    }

    public function updateProfilePartial(UpdateSupplierProfileRequest $request)
    {
        $supplier = $request->user();

        if (! ($supplier instanceof Supplier)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Validation & normalization are handled by UpdateSupplierProfileRequest

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

        $service = new SupplierProfileService;
        $profileData = $service->buildProfileDataFromRequest($request, $profile);

        if (! $profile->slug && isset($profileData['business_name'])) {
            $profileData['slug'] = $this->generateUniqueSupplierSlug($profileData['business_name'], $profile->id);
        }

        if (! empty($profileData)) {
            $profile->update($profileData);
        }

        // Create branches inline if provided
        $service->createInlineBranches($supplier, $request);

        // Handle supplier document upload (no metadata)
        $service->handleDocumentUpload($supplier, $request);

        $supplier->load('profile', 'branches');

        return response()->json([
            'message' => 'Profile updated successfully',
            'supplier' => (new SupplierResource($supplier))->toArray($request),
        ]);
    }

    public function getProfile(Request $request)
    {
        $supplier = $request->user();

        if (! ($supplier instanceof Supplier)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $supplier->loadMissing(['profile', 'branches', 'approvedRatings']);

        $profile = $supplier->profile;
        $ratingAverage = $supplier->approvedRatings()->avg('score');
        $ratingCount = $supplier->approvedRatings()->count();

        $ratingDistribution = $supplier->approvedRatings()
            ->selectRaw('score, COUNT(*) as count')
            ->groupBy('score')
            ->pluck('count', 'score')
            ->toArray();

        $response = [
            'id' => $supplier->id,
            'businessName' => $profile?->business_name ?? $supplier->name,
            'businessType' => $profile?->business_type ?? 'Supplier',
            'categories' => $profile?->business_categories ?? [],
            'services' => $profile?->services_offered ?? [],
            'description' => $profile?->description,
            'website' => $profile?->website,
            'address' => $profile?->business_address,
            'serviceDistance' => $profile?->service_distance,
            'contactPhone' => $profile?->main_phone ?? $supplier->phone,
            'contactEmail' => $profile?->contact_email ?? $supplier->email,
            'profileImage' => $this->mediaUrl($supplier->profile_image),
            'status' => $supplier->status,
            'verificationStatus' => $supplier->status === 'active' ? 'verified' : ($supplier->status === 'pending' ? 'pending_verification' : 'suspended'),
            'plan' => $supplier->plan ?? 'Basic',
            'rating' => [
                'average' => $ratingAverage ? round((float) $ratingAverage, 2) : 0,
                'total' => $ratingCount,
            ],
            'workingHours' => $profile?->working_hours ?? $this->defaultWorkingHours(),
            'productKeywords' => $profile?->keywords ?? [],
            'targetCustomers' => $profile?->target_market ?? [],
            'additionalPhones' => $profile?->additional_phones ?? [],
            'createdAt' => optional($supplier->created_at)->toIso8601String(),
            'updatedAt' => optional($supplier->updated_at)->toIso8601String(),
        ];

        return response()->json($response);
    }

    public function updateProfileImage(Request $request)
    {
        $request->validate(['profile_image' => 'required|image']);

        $supplier = $request->user();
        $destDir = public_path('uploads/suppliers');

        if (! File::exists($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        if ($supplier->profile_image && File::exists(public_path($supplier->profile_image))) {
            File::delete(public_path($supplier->profile_image));
        }

        $file = $request->file('profile_image');
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $file->move($destDir, $filename);

        $supplier->profile_image = 'uploads/suppliers/'.$filename;
        $supplier->save();

        $supplier->load('profile');

        return response()->json(['message' => 'Profile image updated successfully', 'supplier' => (new SupplierResource($supplier))->toArray($request)]);
    }

    protected function mediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return url($path);
    }

    private function defaultWorkingHours(): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $default = [];

        foreach ($days as $day) {
            $default[$day] = [
                'open' => $day === 'sunday' ? '10:00' : '08:00',
                'close' => $day === 'sunday' ? '16:00' : '18:00',
                'closed' => $day === 'sunday',
            ];
        }

        return $default;
    }

    private function generateUniqueSupplierSlug(string $name, ?int $profileId = null): string
    {
        $base = Str::slug($name);
        if (! $base) {
            $base = 'supplier';
        }

        $slug = $base;
        $counter = 1;
        while (
            SupplierProfile::where('slug', $slug)
                ->when($profileId, fn ($query) => $query->where('id', '!=', $profileId))
                ->exists()
        ) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
