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
            'ratingsReceived.rater',
            'approvedRatings.rater',
            'certifications',
            'productImages'
        ])->findOrFail($id);

        // Check profile visibility
        if ($supplier->profile_visibility === 'limited' && !auth()->check()) {
            return response()->json([
                'message' => 'This profile is not publicly available',
                'status' => 'restricted'
            ], 403);
        }

        // Authorization check - only owner can view their own profile
        if (auth()->check() && auth()->id() != $supplier->id) {
            return response()->json([
                'message' => 'You are not authorized to view this profile',
                'status' => 'unauthorized'
            ], 403);
        }

        return response()->json([
            'id' => $supplier->id,
            'name' => $supplier->name,
            'status' => $supplier->status,
            'profile' => [
                'business_type' => $supplier->profile->business_type ?? null,
                'category' => $supplier->profile->category ?? null,
                'image' => $supplier->profile->image ? asset('storage/' . $supplier->profile->image) : null,
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
                'business_image' => $supplier->profile->business_image ? asset($supplier->profile->business_image) : null,
            ],
            // Supplier's profile image (from users table)
            'profile_image' => $supplier->profile_image ? asset($supplier->profile_image) : null,
            'ratings' => [
                'average' => $supplier->approvedRatings->avg('score'),
                'count' => $supplier->approvedRatings->count(),
                'reviews' => $supplier->ratingsReceived->filter(function($review) {
                    return $review->is_approved === true || $review->is_approved === 'approved';
                })->map(function($review) {
                    return [
                        'id' => $review->id,
                        'rating' => $review->score,
                        'comment' => $review->comment,
                        'created_at' => $review->created_at->toDateTimeString(),
                        'user' => [
                            'name' => $review->rater?->name ?? null,
                            'avatar' => ($review->rater && $review->rater->profile_image) ? asset($review->rater->profile_image) : null
                        ],
                        'reply' => $review->reply ? [
                            'id' => $review->reply->id,
                            'reply' => $review->reply->reply,
                            'type' => $review->reply->type,
                            'created_at' => $review->reply->created_at->toDateTimeString()
                        ] : null
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
            'preferences' => [
                'marketing_emails' => $supplier->marketing_emails ?? false,
                'profile_visibility' => $supplier->profile_visibility ?? 'public',
                'show_email_publicly' => $supplier->show_email_publicly ?? false,
                'show_phone_publicly' => $supplier->show_phone_publicly ?? false,
                'allow_direct_contact' => $supplier->allow_direct_contact ?? true,
                'allow_search_engine_indexing' => $supplier->allow_search_engine_indexing ?? true
            ]
        ]);
    }
}
