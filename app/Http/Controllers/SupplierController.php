<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SupplierController extends Controller
{
    /**
     * Get supplier business details with products
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSupplierBusiness($id): JsonResponse
    {
        $supplier = Supplier::with([
            'profile',
            'services',
            'ratingsReceived.rater',
            'approvedRatings',
            'certifications',
            'productImages',
            'products'
        ])->findOrFail($id);
        
        // Eager load the rater relationship for approved ratings
        $supplier->load(['approvedRatings.rater']);

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
                'target_market' => is_array($supplier->profile->target_market ?? null) && !empty($supplier->profile->target_market[0]) ? 
                    array_map('trim', explode(',', $supplier->profile->target_market[0])) : 
                    (is_string($supplier->profile->target_market ?? null) ? 
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
            'profile_image' => $supplier->profile_image ? asset($supplier->profile_image) : null,
            'ratings' => [
                'average' => $supplier->approvedRatings->avg('score'),
                'count' => $supplier->approvedRatings->count(),
                'reviews' => $supplier->ratingsReceived->filter(function($review) {
                    return $review->is_approved;
                })->map(function($review) {
                    return [
                        'id' => $review->id,
                        'rating' => $review->score,
                        'comment' => $review->comment,
                        'created_at' => $review->created_at->toDateTimeString(),
                        'user' => [
                            'name' => $review->rater->name ?? null,
                            'avatar' => $review->rater->profile_image ? asset($review->rater->profile_image) : null
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
                    'image_url' => $image->image_url ? asset($image->image_url) : null,
                    'name' => $image->name
                ];
            })->filter(function($image) {
                return $image['image_url'] !== null;
            })->values(),
            'services' => $supplier->services->map(function($service) {
                return [
                    'id' => $service->id,
                    'service_name' => $service->service_name
                ];
            }),
            'products' => $supplier->products->map(function($product) {
                return [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'created_at' => $product->created_at->toDateTimeString(),
                    'updated_at' => $product->updated_at->toDateTimeString()
                ];
            })
        ]);
    }
}
