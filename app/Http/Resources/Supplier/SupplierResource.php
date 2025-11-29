<?php

namespace App\Http\Resources\Supplier;

use App\Models\Branch;
use App\Support\Media;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Public\BranchResource;
use App\Http\Resources\Supplier\SupplierCertificationResource;
use App\Http\Resources\Supplier\SupplierProductImageResource;
use App\Http\Resources\Supplier\SupplierServiceResource;

class SupplierResource extends JsonResource
{
    public function toArray($request): array
    {
        $supplier = $this->resource;
        $withRelations = true;

        $supplier->loadMissing(['profile', 'branches']);
        $profile = $supplier->profile;

        $ratingAverage = $this->extractAggregate($supplier, ['rating_average', 'approved_ratings_avg_score', 'approved_ratings_avg']);
        $ratingCount = $this->extractAggregate($supplier, ['rating_count', 'approved_ratings_count']);

        return array_filter([
            'id' => $supplier->id,
            'slug' => $profile?->slug,
            'name' => $supplier->name,
            'email' => $supplier->email,
            'phone' => $supplier->phone,
            'profileImage' => Media::url($supplier->profile_image),
            'emailVerifiedAt' => optional($supplier->email_verified_at)->toIso8601String(),
            'status' => $supplier->status,
            'plan' => $supplier->plan,
            'lastSeenAt' => optional($supplier->last_seen_at)->toIso8601String(),
            'profileCompletion' => $this->calculateProfileCompletion($supplier),
            'rating' => $ratingAverage !== null ? round((float) $ratingAverage, 2) : null,
            'reviewsCount' => $ratingCount !== null ? (int) $ratingCount : null,
            'profile' => $profile ? array_filter([
                'slug' => $profile->slug,
                'businessName' => $profile->business_name,
                'businessType' => $profile->business_type,
                'category' => $profile->category, // Main category (single value)
                'categories' => $profile->business_categories ?? [], // Array of categories
                'description' => $profile->description,
                'services' => $profile->services_offered ?? [],
                'website' => $profile->website,
                'address' => $profile->business_address,
                'mainPhone' => $profile->main_phone,
                'contactEmail' => $profile->contact_email,
                'targetCustomers' => $profile->target_market ?? [],
                'whoDoYouServe' => $profile->target_market ?? [],
                'productKeywords' => $profile->keywords ?? [],
                'serviceDistance' => $profile->service_distance !== null ? (string) $profile->service_distance : null,
                'additionalPhones' => $profile->additional_phones ?? [],
                'workingHours' => $profile->working_hours ?? [],
                'hasBranches' => (bool) $profile->has_branches,
                'location' => ($profile->latitude || $profile->longitude) ? [
                    'lat' => $profile->latitude ? (float) $profile->latitude : null,
                    'lng' => $profile->longitude ? (float) $profile->longitude : null,
                ] : null,
            ]) : null,
            'branches' => $withRelations ? $supplier->branches->map(function (Branch $branch) {
                return (new BranchResource($branch))->toArray(request());
            })->toArray() : null,
            'product_images' => $withRelations ? $supplier->productImages->map(function ($image) {
                return [
                    'id' => $image->id,
                    'image_url' => $image->image_url,
                    'created_at' => $image->created_at->toIso8601String(),
                    'updated_at' => $image->updated_at->toIso8601String(),
                ];
            })->values() : null,
            'services' => $withRelations ? $supplier->services->map(function ($service) {
                return [
                    'id' => $service->id,
                    'service_name' => $service->service_name,
                    'created_at' => $service->created_at->toIso8601String(),
                    'updated_at' => $service->updated_at->toIso8601String(),
                ];
            })->values() : null,
            'certifications' => $withRelations ? $supplier->certifications->map(function ($cert) {
                return [
                    'id' => $cert->id,
                    'certification_name' => $cert->certification_name,
                    'created_at' => $cert->created_at->toIso8601String(),
                    'updated_at' => $cert->updated_at->toIso8601String(),
                ];
            })->values() : null,
        ], function ($value) {
            return $value !== null;
        });
    }

    private function extractAggregate(object $model, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (isset($model->$key)) {
                return $model->$key;
            }
        }

        return null;
    }

    private function calculateProfileCompletion($supplier): int
    {
        $supplier->loadMissing('profile', 'branches');
        $profile = $supplier->profile;

        $checks = [
            (bool) $supplier->phone,
            (bool) $profile?->business_name,
            (bool) $profile?->business_type,
            ! empty($profile?->business_categories),
            ! empty($profile?->services_offered),
            (bool) $profile?->description,
            (bool) $profile?->website,
            (bool) $profile?->business_address,
            ! empty($profile?->working_hours),
            ! empty($profile?->additional_phones),
            ! empty($profile?->keywords),
            (bool) $supplier->profile_image,
            $supplier->branches()->exists(),
        ];

        $total = count($checks);
        $completed = count(array_filter($checks));

        if ($total === 0) {
            return 0;
        }

        return (int) round(($completed / $total) * 100);
    }
}
