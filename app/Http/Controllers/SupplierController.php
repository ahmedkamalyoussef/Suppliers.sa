<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    /**
     * Get list of supplier businesses with filters and sorting
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getBusinesses(Request $request): JsonResponse
    {
        $query = Supplier::with(['profile', 'approvedRatings'])
            ->whereHas('profile')
            ->when($request->filled('status') && $request->boolean('status'), function($q) {
                $q->where('status', 'active');
            });

        // Apply business type filter
        if ($request->filled('business_type')) {
            $query->whereHas('profile', function($q) use ($request) {
                $q->where('business_type', $request->business_type);
            });
        }

        // Apply business categories filter
        if ($request->filled('business_categories')) {
            $categories = is_array($request->business_categories) 
                ? $request->business_categories 
                : explode(',', $request->business_categories);
                
            $query->whereHas('profile', function($q) use ($categories) {
                $q->whereJsonContains('business_categories', $categories);
            });
        }

        // Apply service distance filter
        if ($request->filled('service_distance')) {
            $query->whereHas('profile', function($q) use ($request) {
                $q->where('service_distance', '>=', (float)$request->service_distance);
            });
        }

        // Apply open now filter
        if ($request->filled('open_now') && $request->boolean('open_now')) {
            $now = Carbon::now();
            $dayOfWeek = strtolower($now->format('l'));
            $currentTime = $now->format('H:i:s');
            
            $query->whereHas('profile', function($q) use ($dayOfWeek, $currentTime) {
                $q->whereJsonContains('working_hours->' . $dayOfWeek . '->is_open', true)
                  ->where('working_hours->' . $dayOfWeek . '->opening_time', '<=', $currentTime)
                  ->where('working_hours->' . $dayOfWeek . '->closing_time', '>=', $currentTime);
            });
        }

        // Apply address filter
        if ($request->filled('address')) {
            $query->whereHas('profile', function($q) use ($request) {
                $q->where('business_address', 'like', '%' . $request->address . '%');
            });
        }

        // Apply search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('profile', function($q) use ($search) {
                    $q->where('business_name', 'like', "%{$search}%")
                      ->orWhereJsonContains('services_offered', $search);
                })->orWhereHas('products', function($q) use ($search) {
                    $q->where('product_name', 'like', "%{$search}%");
                });
            });
        }

        // Apply keywords filter
        if ($request->filled('keywords')) {
            $keywords = is_array($request->keywords) 
                ? $request->keywords 
                : explode(',', $request->keywords);
                
            $query->whereHas('profile', function($q) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $q->whereJsonContains('keywords', $keyword);
                }
            });
        }

        // Get the results with pagination
        $suppliers = $query->withCount('approvedRatings as ratings_count')
            ->withAvg('approvedRatings as ratings_avg', 'score')
            ->orderBy($request->input('sort_by', 'id'), $request->input('sort_order', 'asc'))
            ->paginate($request->input('per_page', 15));

        // Format the response
        $formattedSuppliers = $suppliers->map(function($supplier) {
            $servicesOffered = $supplier->profile->services_offered ?? [];
            
            return [
                'supplier_id' => $supplier->id,
                'business_type' => $supplier->profile->business_type ?? null,
                'service_distance' => $supplier->profile->service_distance ?? null,
                'business_name' => $supplier->profile->business_name ?? null,
                'category' => $supplier->profile->category ?? null,
                'total_ratings' => $supplier->ratings_count,
                'ratings_average' => (float)number_format($supplier->ratings_avg ?? 0, 1),
                'target_market' => $supplier->profile->target_market ?? [],
                'services_offered' => array_slice($servicesOffered, 0, 2), // First two services
                'business_image' => $supplier->profile->business_image 
                    ? asset($supplier->profile->business_image) 
                    : ($supplier->profile_image ? asset($supplier->profile_image) : null),
                'latitude' => $supplier->profile->latitude ?? null,
                'longitude' => $supplier->profile->longitude ?? null,
                'address' => $supplier->profile->business_address ?? null,
                'status' => $supplier->status === 'active',
            ];
        });

        return response()->json([
            'data' => $formattedSuppliers,
            'pagination' => [
                'total' => $suppliers->total(),
                'per_page' => $suppliers->perPage(),
                'current_page' => $suppliers->currentPage(),
                'last_page' => $suppliers->lastPage(),
                'from' => $suppliers->firstItem(),
                'to' => $suppliers->lastItem(),
            ]
        ]);
    }

    /**
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
            'ratingsReceived.reply',
            'approvedRatings',
            'approvedRatings.reply',
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
                'category' => $supplier->profile->category ?? null,
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
                // Supplier profile's image (from supplier_profiles table)
                'business_image' => $supplier->profile->business_image ? asset($supplier->profile->business_image) : null,
            ],
            // Supplier's profile image (from users table)
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
