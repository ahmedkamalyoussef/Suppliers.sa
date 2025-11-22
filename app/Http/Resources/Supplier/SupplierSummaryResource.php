<?php

namespace App\Http\Resources\Supplier;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $supplier = $this->resource;
        $profile = $supplier->profile;

        // Extract rating aggregates
        $ratingAverage = $supplier->rating_average ??
                        $supplier->approved_ratings_avg_score ??
                        $supplier->approved_ratings_avg ?? null;
        $ratingCount = $supplier->rating_count ??
                       $supplier->approved_ratings_count ?? null;

        return array_filter([
            'id' => $supplier->id,
            'name' => $supplier->name,
            'profileImage' => \App\Support\Media::mediaUrl($supplier->profile_image),
            'slug' => $profile?->slug,
            'category' => $profile?->business_categories[0] ?? null,
            'categories' => $profile?->business_categories ?? [],
            'businessType' => $profile?->business_type,
            'address' => $profile?->business_address,
            'serviceDistance' => $profile?->service_distance !== null ? (float) $profile->service_distance : null,
            'rating' => $ratingAverage !== null ? round((float) $ratingAverage, 2) : null,
            'reviewsCount' => $ratingCount !== null ? (int) $ratingCount : null,
            'status' => $supplier->status,
            'plan' => $supplier->plan,
        ], function ($value) {
            return $value !== null;
        });
    }
}
