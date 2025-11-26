<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\SupplierProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SupplierProfileService
{
    public function buildProfileDataFromRequest(Request $request, SupplierProfile $profile): array
    {
        $profileData = [];

        if ($request->has('businessName')) {
            $profileData['business_name'] = $request->businessName;
        }

        if ($request->has('businessType')) {
            $profileData['business_type'] = $request->businessType;
        }

        // Handle category (independent field)
        if ($request->has('category')) {
            $profileData['category'] = $request->category;
        }
        
        // Handle categories (completely independent from category)
        if ($request->has('categories')) {
            $profileData['business_categories'] = is_array($request->categories) 
                ? $request->categories 
                : [$request->categories];
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

        return $profileData;
    }

    public function createInlineBranches(Supplier $supplier, Request $request): void
    {
        if (! $request->has('branches')) {
            return;
        }

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

    public function handleDocumentUpload(Supplier $supplier, Request $request): void
    {
        if (! $request->hasFile('document')) {
            return;
        }

        // Delete any existing documents for this supplier
        $existingDocuments = $supplier->documents;

        $destDir = public_path('uploads/documents');
        if (! File::exists($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        // Store the new document
        $file = $request->file('document');
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $file->move($destDir, $filename);

        $filePath = 'uploads/documents/'.$filename;

        // Delete old documents and their files
        foreach ($existingDocuments as $existingDoc) {
            if (File::exists(public_path($existingDoc->file_path))) {
                File::delete(public_path($existingDoc->file_path));
            }
            $existingDoc->delete();
        }

        // Create new document record
        SupplierDocument::create([
            'supplier_id' => $supplier->id,
            'file_path' => $filePath,
        ]);
    }

    protected function defaultBranchHours(): array
    {
        return [
            'sat' => ['closed' => false, 'open' => '09:00', 'close' => '17:00'],
            'sun' => ['closed' => false, 'open' => '09:00', 'close' => '17:00'],
            'mon' => ['closed' => false, 'open' => '09:00', 'close' => '17:00'],
            'tue' => ['closed' => false, 'open' => '09:00', 'close' => '17:00'],
            'wed' => ['closed' => false, 'open' => '09:00', 'close' => '17:00'],
            'thu' => ['closed' => false, 'open' => '09:00', 'close' => '14:00'],
            'fri' => ['closed' => true],
        ];
    }
}
