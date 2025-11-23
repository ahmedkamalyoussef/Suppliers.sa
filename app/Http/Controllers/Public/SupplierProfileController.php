<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierProfileController extends Controller
{
    public function show($id)
    {
        $supplier = Supplier::with([
            'profile',
            'services',
            'ratingsReceived',
            'approvedRatings',
            'certifications',
            'productImages'
        ])->findOrFail($id);

        return response()->json([
            'id' => $supplier->id,
            'name' => $supplier->name,
            'status' => $supplier->status,
            'profile' => [
                'business_type' => $supplier->profile->business_type ?? null,
                'website' => $supplier->profile->website ?? null,
                'contact_email' => $supplier->profile->contact_email ?? null,
                'description' => $supplier->profile->description ?? null,
                'service_distance' => $supplier->profile->service_distance ?? null,
                'target_market' => is_array($supplier->profile->target_market) && !empty($supplier->profile->target_market[0]) ? 
                    array_map('trim', explode(',', $supplier->profile->target_market[0])) : 
                    (is_string($supplier->profile->target_market) ? 
                        array_map('trim', explode(',', $supplier->profile->target_market)) : 
                        []),
                'main_phone' => $supplier->profile->main_phone ?? null,
                'additional_phones' => $supplier->profile->additional_phones ?? [],
                'business_address' => $supplier->profile->business_address ?? null,
                'latitude' => $supplier->profile->latitude ?? null,
                'longitude' => $supplier->profile->longitude ?? null,
                'working_hours' => $supplier->profile->working_hours ?? null,
                'services_offered' => $supplier->profile->services_offered ?? [],
            ],
            'profile_image' => $supplier->profile_image ? asset('storage/' . $supplier->profile_image) : null,
            'ratings' => [
                'average' => $supplier->approvedRatings->avg('rating'),
                'count' => $supplier->approvedRatings->count(),
                'reviews' => $supplier->ratingsReceived->map(function($review) {
                    return [
                        'id' => $review->id,
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'created_at' => $review->created_at->toDateTimeString(),
                        'user' => [
                            'name' => $review->rater->name ?? null,
                            'avatar' => $review->rater->profile_image ? asset('storage/' . $review->rater->profile_image) : null
                        ]
                    ];
                })
            ],
            'certifications' => $supplier->certifications->map(function($cert) {
                return [
                    'id' => $cert->id,
                    'certification_name' => $cert->certification_name
                ];
            }),
            'product_images' => $supplier->productImages->map(function($image) {
                return [
                    'id' => $image->id,
                    'image_url' => $image->image_url ? asset('storage/' . $image->image_url) : null
                ];
            })->filter(function($image) {
                return $image['image_url'] !== null;
            })->values(),
            'services' => $supplier->services->map(function($service) {
                return [
                    'id' => $service->id,
                    'service_name' => $service->service_name
                ];
            })
        ]);
    }
}
