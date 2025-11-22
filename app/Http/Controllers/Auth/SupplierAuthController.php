<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierProfile;
use App\Models\SupplierDocument;
use App\Models\Otp;
use App\Notifications\OtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            'supplier' => $this->transformSupplier($supplier, false),
        ];


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
        $supplier->forceFill(['last_seen_at' => now()])->save();
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
            'otp' => ['required', 'string', 'size:6']
        ]);

        $supplier = Supplier::where('email', $request->email)->first();
        $otp = Otp::where('supplier_id', $supplier->id)
                  ->where('otp', $request->otp)
                  ->where('expires_at', '>', now())
                  ->first();

        if (!$otp || !$otp->isValid()) return response()->json(['message' => 'Invalid or expired OTP'], 400);

        $supplier->email_verified_at = now();
        $supplier->status = 'active';
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
        return $this->updateProfilePartial($request);
    }
    
    public function updateProfilePartial(Request $request)
    {
        $supplier = $request->user();

        if (!($supplier instanceof Supplier)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Normalize array-like inputs coming from form-data or raw values
        foreach (['whoDoYouServe', 'targetCustomers'] as $arrKey) {
            if ($request->has($arrKey)) {
                $val = $request->input($arrKey);
                // If sent as JSON string, decode
                if (is_string($val)) {
                    $decoded = json_decode($val, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $request->merge([$arrKey => $decoded]);
                    } else {
                        // Wrap plain string into array
                        $request->merge([$arrKey => [$val]]);
                    }
                }
            }
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'businessName' => ['sometimes', 'string', 'max:255'],
            'businessType' => ['sometimes', 'string', Rule::in(['supplier','store','office','individual'])],
            'category' => ['sometimes', 'string', 'max:255'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['string', 'max:255'],
            'services' => ['sometimes', 'array'],
            'services.*' => ['string', 'max:255'],
            'productKeywords' => ['sometimes', 'array'],
            'productKeywords.*' => ['string', 'max:255'],
            // Allow legacy array targetCustomers or new array whoDoYouServe
            'targetCustomers' => ['sometimes', 'array'],
            'targetCustomers.*' => ['string', 'max:255'],
            'whoDoYouServe' => ['sometimes', 'array'],
            'whoDoYouServe.*' => ['string', 'max:255'],
            'serviceDistance' => ['sometimes', 'string', 'max:255'],
            // Additional phones as array of objects
            'additionalPhones' => ['sometimes', 'array'],
            'additionalPhones.*.number' => ['required_with:additionalPhones', 'string', 'max:20'],
            'additionalPhones.*.name' => ['nullable', 'string', 'max:255'],
            'additionalPhones.*.type' => ['nullable', 'string', 'max:50'],
            // Working hours flexible object keyed by weekdays
            'workingHours' => ['sometimes', 'array'],
            'workingHours.*.closed' => ['sometimes', 'boolean'],
            'workingHours.*.open' => ['sometimes', 'string'],
            'workingHours.*.close' => ['sometimes', 'string'],
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
            // Inline branches creation
            'branches' => ['sometimes', 'array'],
            'branches.*.name' => ['required_with:branches', 'string', 'max:255'],
            'branches.*.phone' => ['required_with:branches', 'string', 'max:20'],
            'branches.*.email' => ['nullable', 'email', 'max:255'],
            'branches.*.address' => ['required_with:branches', 'string', 'max:500'],
            'branches.*.manager' => ['required_with:branches', 'string', 'max:255'],
            'branches.*.location.lat' => ['sometimes', 'numeric'],
            'branches.*.location.lng' => ['sometimes', 'numeric'],
            'branches.*.workingHours' => ['sometimes', 'array'],
            'branches.*.specialServices' => ['sometimes', 'array'],
            'branches.*.isMainBranch' => ['sometimes', 'boolean'],
            // Optional supplier document upload (metadata all optional)
            'document' => ['sometimes', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
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
        if ($request->has('whoDoYouServe')) {
            $profileData['target_market'] = $request->input('whoDoYouServe', []);
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

        if (!$profile->slug && isset($profileData['business_name'])) {
            $profileData['slug'] = $this->generateUniqueSupplierSlug($profileData['business_name'], $profile->id);
        }

        if (!empty($profileData)) {
            $profile->update($profileData);
        }

        // Create branches inline if provided
        if ($request->has('branches')) {
            $branches = $request->input('branches', []);
            foreach ($branches as $b) {
                $payload = [
                    'name' => $b['name'] ?? null,
                    'phone' => $b['phone'] ?? null,
                    'email' => $b['email'] ?? null,
                    'address' => $b['address'] ?? null,
                    'manager_name' => $b['manager'] ?? null,
                    'latitude' => data_get($b, 'location.lat'),
                    'longitude' => data_get($b, 'location.lng'),
                    'working_hours' => $b['workingHours'] ?? $this->defaultBranchHours(),
                    'special_services' => $b['specialServices'] ?? [],
                    'is_main_branch' => (bool) ($b['isMainBranch'] ?? false),
                    'status' => 'active',
                ];

                $supplier->branches()->create($payload);
            }
        }

        // Create or replace a supplier document if provided
        if ($request->hasFile('document')) {
            $validatedDocType = $request->input('documentType', 'general');

            // If a document with same type exists, delete and replace
            $existingDocument = $supplier->documents()
                ->where('document_type', $validatedDocType)
                ->first();

            if ($existingDocument) {
                // Delete old file if exists
                $oldPath = $existingDocument->file_path;
                if ($oldPath && File::exists(public_path($oldPath))) {
                    File::delete(public_path($oldPath));
                }
                $existingDocument->delete();
            }

            // Ensure destination dir
            $destDir = public_path('uploads/documents');
            if (!File::exists($destDir)) {
                File::makeDirectory($destDir, 0755, true);
            }

            $file = $request->file('document');
            $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
            $file->move($destDir, $filename);

            $filePath = 'uploads/documents/'.$filename;

            SupplierDocument::create([
                'supplier_id' => $supplier->id,
                'file_path' => $filePath,
            ]);
        }

        $supplier->load('profile', 'branches');

        return response()->json([
            'message' => 'Profile updated successfully',
            'supplier' => $this->transformSupplier($supplier),
        ]);
    }

    public function getProfile(Request $request)
    {
        $supplier = $request->user();

        if (!($supplier instanceof Supplier)) {
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

        return response()->json(['message'=>'Profile image updated successfully','supplier'=>$this->transformSupplier($supplier, false)]);
    }
    
    protected function mediaUrl(?string $path): ?string
    {
        if (!$path) {
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
        if (!$base) {
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
