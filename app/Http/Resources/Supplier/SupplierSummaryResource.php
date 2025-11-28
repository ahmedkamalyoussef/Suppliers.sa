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
            'businessImage' => $profile?->business_image ? asset($profile->business_image) : null,
            'slug' => $profile?->slug,
            'category' => $profile?->business_categories[0] ?? null,
            'categories' => $profile?->business_categories ? array_slice($profile->business_categories, 0, 2) : [],
            'businessType' => $profile?->business_type,
            'address' => $profile?->business_address,
            'serviceDistance' => $profile?->service_distance !== null ? (float) $profile->service_distance : null,
            'rating' => $ratingAverage !== null ? round((float) $ratingAverage, 2) : null,
            'reviewsCount' => $ratingCount !== null ? (int) $ratingCount : null,
            'status' => $supplier->status,
            'plan' => $supplier->plan,
            'latitude' => $profile?->latitude,
            'longitude' => $profile?->longitude,
            'mainPhone' => $profile?->main_phone,
            'contactEmail' => $profile?->contact_email,
            'services' => $this->formatServices($profile?->services_offered),
            'targetMarket' => $this->formatTargetMarket($profile?->target_market),
            'preferences' => [
                'marketing_emails' => $supplier->marketing_emails ?? false,
                'profile_visibility' => $supplier->profile_visibility ?? 'public',
                'show_email_publicly' => $supplier->show_email_publicly ?? false,
                'show_phone_publicly' => $supplier->show_phone_publicly ?? false,
                'allow_direct_contact' => $supplier->allow_direct_contact ?? true,
                'allow_search_engine_indexing' => $supplier->allow_search_engine_indexing ?? true
            ],
        ], function ($value) {
            return $value !== null;
        });
    }

    /**
     * Format target market to ensure it's always an array of strings
     */
    /**
     * Format services_offered to ensure it's always an array
     */
    protected function formatServices($services)
    {
        if (is_string($services)) {
            return array_filter(array_map('trim', explode(',', $services)));
        }
        
        if (is_array($services)) {
            // If it's an array of arrays, get the first item
            if (is_array(($services[0] ?? null))) {
                return array_column($services, 0);
            }
            
            // If it's an array with comma-separated strings, split them
            return collect($services)
                ->flatMap(function ($item) {
                    return is_string($item) ? array_map('trim', explode(',', $item)) : [$item];
                })
                ->filter()
                ->values()
                ->toArray();
        }
        
        return [];
    }

    protected function formatTargetMarket($targetMarket)
    {
        if (is_string($targetMarket)) {
            return array_filter(array_map('trim', explode(',', $targetMarket)));
        }
        
        if (is_array($targetMarket)) {
            // If it's an array of arrays, get the first item
            if (is_array(($targetMarket[0] ?? null))) {
                return array_column($targetMarket, 0);
            }
            
            // If it's an array with comma-separated strings, split them
            return collect($targetMarket)
                ->flatMap(function ($item) {
                    return is_string($item) ? array_map('trim', explode(',', $item)) : [$item];
                })
                ->filter()
                ->values()
                ->toArray();
        }
        
        return [];
    }
}
