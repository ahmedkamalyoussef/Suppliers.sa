<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BusinessController extends Controller
{
    /**
     * Create a new business
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user can add more businesses
        if (! $user->canAddBusiness()) {
            return response()->json([
                'success' => false,
                'message' => 'You have reached your business limit. Upgrade your plan to add more businesses.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'business_name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'business_type' => ['required', Rule::in(['Supplier', 'Service Provider', 'Manufacturer'])],
            'description' => 'required|string|max:2000',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'website' => 'nullable|url|max:255',
            'location' => 'required|array',
            'location.address' => 'required|string|max:500',
            'location.city' => 'required|string|max:255',
            'location.region' => 'required|string|max:255',
            'location.postal_code' => 'nullable|string|max:20',
            'location.coordinates' => 'required|array',
            'location.coordinates.lat' => 'required|numeric|between:-90,90',
            'location.coordinates.lng' => 'required|numeric|between:-180,180',
            'services' => 'nullable|array',
            'services.*' => 'string|max:255',
            'target_customers' => 'nullable|array',
            'target_customers.*' => 'string|max:255',
            'product_keywords' => 'nullable|array',
            'product_keywords.*' => 'string|max:255',
            'working_hours' => 'nullable|array',
            'additional_phones' => 'nullable|array',
            'additional_phones.*.type' => 'required|string|max:50',
            'additional_phones.*.number' => 'required|string|max:20',
            'additional_phones.*.name' => 'required|string|max:255',
            'branches' => 'nullable|array',
            'years_in_business' => 'nullable|integer|min:0|max:200',
            'clients_served' => 'nullable|string|max:255',
            'specialization' => 'nullable|string|max:500',
            'service_distance' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $business = Business::create([
            'business_name' => $request->business_name,
            'category' => $request->category,
            'business_type' => $request->business_type,
            'description' => $request->description,
            'phone' => $request->phone,
            'email' => $request->email,
            'website' => $request->website,
            'location' => $request->location,
            'services' => $request->services ?? [],
            'target_customers' => $request->target_customers ?? [],
            'product_keywords' => $request->product_keywords ?? [],
            'working_hours' => $request->working_hours ?? [],
            'additional_phones' => $request->additional_phones ?? [],
            'branches' => $request->branches ?? [],
            'years_in_business' => $request->years_in_business,
            'clients_served' => $request->clients_served,
            'specialization' => $request->specialization,
            'service_distance' => $request->service_distance,
            'rating' => 0,
            'reviews_count' => 0,
            'verified' => false,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Business created successfully',
            'business' => [
                'id' => $business->id,
                'business_name' => $business->business_name,
                'category' => $business->category,
                'business_type' => $business->business_type,
                'description' => $business->description,
                'phone' => $business->phone,
                'email' => $business->email,
                'website' => $business->website,
                'location' => $business->location,
                'services' => $business->services,
                'target_customers' => $business->target_customers,
                'product_keywords' => $business->product_keywords,
                'working_hours' => $business->working_hours,
                'rating' => $business->rating,
                'reviews' => $business->reviews_count,
                'years_in_business' => $business->years_in_business,
                'clients_served' => $business->clients_served,
                'specialization' => $business->specialization,
                'service_distance' => $business->service_distance,
                'verified' => $business->verified,
                'status' => $business->status,
                'created_at' => $business->created_at->toISOString(),
            ],
        ], 201);
    }

    /**
     * Get business details
     */
    public function show(Business $business): JsonResponse
    {
        // Load relationships
        $business->load(['images' => function ($query) {
            $query->orderBy('sort_order');
        }]);

        return response()->json([
            'success' => true,
            'business' => [
                'id' => $business->id,
                'business_name' => $business->business_name,
                'category' => $business->category,
                'business_type' => $business->business_type,
                'description' => $business->description,
                'phone' => $business->phone,
                'email' => $business->email,
                'website' => $business->website,
                'location' => $business->location,
                'services' => $business->services,
                'target_customers' => $business->target_customers,
                'service_distance' => $business->service_distance,
                'rating' => $business->rating,
                'reviews' => $business->reviews_count,
                'years_in_business' => $business->years_in_business,
                'clients_served' => $business->clients_served,
                'specialization' => $business->specialization,
                'working_hours' => $business->working_hours,
                'gallery_images' => $business->images->map(function ($image) {
                    return [
                        'url' => $image->url,
                        'caption' => $image->caption,
                    ];
                }),
                'images' => $business->images->pluck('url'),
                'verified' => $business->verified,
                'badge' => $business->badge,
                'features' => $business->features,
            ],
        ]);
    }

    /**
     * Update business
     */
    public function update(Request $request, Business $business): JsonResponse
    {
        $this->authorize('update', $business);

        $validator = Validator::make($request->all(), [
            'business_name' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|nullable|string|max:255',
            'business_type' => ['sometimes', 'required', Rule::in(['Supplier', 'Service Provider', 'Manufacturer'])],
            'description' => 'sometimes|required|string|max:2000',
            'phone' => 'sometimes|required|string|max:20',
            'email' => 'sometimes|required|email|max:255',
            'website' => 'sometimes|nullable|url|max:255',
            'location' => 'sometimes|required|array',
            'location.address' => 'required|string|max:500',
            'location.city' => 'required|string|max:255',
            'location.region' => 'required|string|max:255',
            'location.postal_code' => 'nullable|string|max:20',
            'location.coordinates' => 'required|array',
            'location.coordinates.lat' => 'required|numeric|between:-90,90',
            'location.coordinates.lng' => 'required|numeric|between:-180,180',
            'services' => 'sometimes|nullable|array',
            'services.*' => 'string|max:255',
            'target_customers' => 'sometimes|nullable|array',
            'target_customers.*' => 'string|max:255',
            'product_keywords' => 'sometimes|nullable|array',
            'product_keywords.*' => 'string|max:255',
            'working_hours' => 'sometimes|nullable|array',
            'additional_phones' => 'sometimes|nullable|array',
            'additional_phones.*.type' => 'required|string|max:50',
            'additional_phones.*.number' => 'required|string|max:20',
            'additional_phones.*.name' => 'required|string|max:255',
            'branches' => 'sometimes|nullable|array',
            'years_in_business' => 'sometimes|nullable|integer|min:0|max:200',
            'clients_served' => 'sometimes|nullable|string|max:255',
            'specialization' => 'sometimes|nullable|string|max:500',
            'service_distance' => 'sometimes|nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $business->update($request->only([
            'business_name', 'category', 'business_type', 'description', 'phone', 'email', 'website',
            'location', 'services', 'target_customers', 'product_keywords', 'working_hours',
            'additional_phones', 'branches', 'years_in_business', 'clients_served',
            'specialization', 'service_distance',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Business updated successfully',
            'business' => $business->fresh(),
        ]);
    }

    /**
     * Delete business
     */
    public function destroy(Business $business): JsonResponse
    {
        $this->authorize('delete', $business);

        $business->delete();

        return response()->json([
            'success' => true,
            'message' => 'Business deleted successfully',
        ]);
    }

    /**
     * Upload business images
     */
    public function uploadImages(Request $request, Business $business): JsonResponse
    {
        $this->authorize('update', $business);

        $validator = Validator::make($request->all(), [
            'images' => 'required|array|max:10',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $uploadedImages = [];

        foreach ($request->file('images') as $index => $image) {
            $path = $image->store('business-images', 'public');
            $url = Storage::url($path);

            $businessImage = BusinessImage::create([
                'business_id' => $business->id,
                'url' => $url,
                'caption' => $request->captions[$index] ?? null,
                'type' => 'gallery',
                'sort_order' => $business->images()->count() + $index,
            ]);

            $uploadedImages[] = $url;
        }

        return response()->json([
            'success' => true,
            'message' => 'Images uploaded successfully',
            'images' => $uploadedImages,
        ]);
    }

    /**
     * Get business reviews
     */
    public function reviews(Request $request, Business $business): JsonResponse
    {
        $query = $business->reviews()->approved();

        if ($request->rating) {
            $query->byRating($request->rating);
        }

        $reviews = $query->orderBy('created_at', $request->sort === 'oldest' ? 'asc' : 'desc')
            ->paginate($request->limit ?? 10);

        return response()->json([
            'success' => true,
            'reviews' => $reviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'customer_name' => $review->customer_name,
                    'rating' => $review->rating,
                    'title' => $review->title,
                    'comment' => $review->comment,
                    'date' => $review->created_at->toISOString(),
                    'helpful' => $review->helpful_count ?? 0,
                    'verified' => $review->verified,
                ];
            }),
            'pagination' => [
                'page' => $reviews->currentPage(),
                'limit' => $reviews->perPage(),
                'total' => $reviews->total(),
                'totalPages' => $reviews->lastPage(),
            ],
            'summary' => [
                'averageRating' => $business->rating,
                'totalReviews' => $business->reviews_count,
                'ratingDistribution' => $this->getRatingDistribution($business),
            ],
        ]);
    }

    /**
     * Update business location
     */
    public function updateLocation(Request $request, Business $business): JsonResponse
    {
        $this->authorize('update', $business);

        $validator = Validator::make($request->all(), [
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:255',
            'region' => 'required|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'coordinates' => 'required|array',
            'coordinates.lat' => 'required|numeric|between:-90,90',
            'coordinates.lng' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $business->update([
            'location' => [
                'address' => $request->address,
                'city' => $request->city,
                'region' => $request->region,
                'postal_code' => $request->postal_code,
                'coordinates' => $request->coordinates,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Location updated successfully',
        ]);
    }

    private function getRatingDistribution(Business $business): array
    {
        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        $business->reviews()->approved()->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->each(function ($count, $rating) use (&$distribution) {
                $distribution[$rating] = $count;
            });

        return $distribution;
    }
}
