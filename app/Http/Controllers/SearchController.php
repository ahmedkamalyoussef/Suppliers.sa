<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessReview;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class SearchController extends Controller
{
    /**
     * Search businesses
     */
    public function businesses(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'distance' => 'nullable|string|in:5km,10km,25km,50km,100km',
            'rating' => 'nullable|string|in:3+,4+,4.5+',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Business::query()->active();

        // Search query
        if ($request->q) {
            $searchTerm = $request->q;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('business_name', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%")
                  ->orWhere('category', 'like', "%{$searchTerm}%")
                  ->orWhereJsonContains('services', $searchTerm)
                  ->orWhereJsonContains('product_keywords', $searchTerm);
            });
        }

        // Category filter
        if ($request->category) {
            $query->byCategory($request->category);
        }

        // Location filter
        if ($request->location) {
            $query->where('location.city', 'like', "%{$request->location}%")
                  ->orWhere('location.region', 'like', "%{$request->location}%");
        }

        // Distance filter (requires coordinates)
        if ($request->distance && $request->lat && $request->lng) {
            $radius = (int) str_replace('km', '', $request->distance);
            $query->nearLocation($request->lat, $request->lng, $radius);
        }

        // Rating filter
        if ($request->rating) {
            $minRating = match($request->rating) {
                '3+' => 3.0,
                '4+' => 4.0,
                '4.5+' => 4.5,
                default => 0
            };
            $query->where('rating', '>=', $minRating);
        }

        $businesses = $query->paginate($request->limit ?? 20);

        return response()->json([
            'success' => true,
            'businesses' => $businesses->map(function ($business) {
                return [
                    'id' => $business->id,
                    'name' => $business->business_name,
                    'category' => $business->category,
                    'location' => $business->location['city'] . ', ' . $business->location['region'],
                    'rating' => $business->rating,
                    'reviews' => $business->reviews_count,
                    'description' => substr($business->description, 0, 150) . '...',
                    'image' => $business->images->first()?->url,
                    'verified' => $business->verified,
                    'business_type' => $business->business_type,
                    'target_customers' => $business->target_customers,
                ];
            }),
            'pagination' => [
                'page' => $businesses->currentPage(),
                'limit' => $businesses->perPage(),
                'total' => $businesses->total(),
                'totalPages' => $businesses->lastPage(),
            ]
        ]);
    }

    /**
     * Get search suggestions
     */
    public function suggestions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:2|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = $request->q;
        $suggestions = [];

        // Get business name suggestions
        $businessNames = Business::where('business_name', 'like', "%{$query}%")
            ->active()
            ->limit(5)
            ->pluck('business_name');

        // Get category suggestions
        $categories = Business::where('category', 'like', "%{$query}%")
            ->active()
            ->distinct()
            ->limit(5)
            ->pluck('category');

        // Get service suggestions
        $services = Business::whereJsonContains('services', $query)
            ->active()
            ->limit(5)
            ->get()
            ->flatMap(function ($business) use ($query) {
                return collect($business->services)
                    ->filter(fn($service) => str_contains(strtolower($service), strtolower($query)))
                    ->take(3);
            });

        // Combine and limit suggestions
        $allSuggestions = $businessNames
            ->merge($categories)
            ->merge($services)
            ->unique()
            ->take(10);

        return response()->json([
            'success' => true,
            'suggestions' => $allSuggestions->toArray()
        ]);
    }

    /**
     * Advanced search
     */
    public function advanced(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|max:255',
            'filters' => 'nullable|array',
            'filters.categories' => 'nullable|array',
            'filters.categories.*' => 'string|max:255',
            'filters.business_types' => 'nullable|array',
            'filters.business_types.*' => 'string|in:Supplier,Service Provider,Manufacturer',
            'filters.locations' => 'nullable|array',
            'filters.locations.*' => 'string|max:255',
            'filters.distance' => 'nullable|string|in:5km,10km,25km,50km,100km',
            'filters.rating' => 'nullable|numeric|min:0|max:5',
            'filters.verified' => 'nullable|boolean',
            'filters.price_range' => 'nullable|array',
            'filters.price_range.min' => 'nullable|numeric|min:0',
            'filters.price_range.max' => 'nullable|numeric|min:0',
            'filters.services' => 'nullable|array',
            'filters.services.*' => 'string|max:255',
            'sort' => 'nullable|array',
            'sort.field' => 'nullable|string|in:relevance,rating,distance,reviews',
            'sort.order' => 'nullable|string|in:asc,desc',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Business::query()->active();
        $filters = $request->filters ?? [];
        $sort = $request->sort ?? ['field' => 'relevance', 'order' => 'desc'];

        // Search query
        if ($request->query) {
            $searchTerm = $request->query;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('business_name', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%")
                  ->orWhere('category', 'like', "%{$searchTerm}%")
                  ->orWhereJsonContains('services', $searchTerm)
                  ->orWhereJsonContains('product_keywords', $searchTerm);
            });
        }

        // Apply filters
        if (!empty($filters['categories'])) {
            $query->whereIn('category', $filters['categories']);
        }

        if (!empty($filters['business_types'])) {
            $query->whereIn('business_type', $filters['business_types']);
        }

        if (!empty($filters['locations'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['locations'] as $location) {
                    $q->orWhere('location.city', 'like', "%{$location}%")
                      ->orWhere('location.region', 'like', "%{$location}%");
                }
            });
        }

        if (!empty($filters['distance']) && $request->lat && $request->lng) {
            $radius = (int) str_replace('km', '', $filters['distance']);
            $query->nearLocation($request->lat, $request->lng, $radius);
        }

        if (isset($filters['rating'])) {
            $query->where('rating', '>=', $filters['rating']);
        }

        if (isset($filters['verified'])) {
            $query->where('verified', $filters['verified']);
        }

        if (!empty($filters['services'])) {
            foreach ($filters['services'] as $service) {
                $query->whereJsonContains('services', $service);
            }
        }

        // Apply sorting
        switch ($sort['field']) {
            case 'rating':
                $query->orderBy('rating', $sort['order']);
                break;
            case 'reviews':
                $query->orderBy('reviews_count', $sort['order']);
                break;
            case 'distance':
                if ($request->lat && $request->lng) {
                    $query->orderBy('distance', $sort['order']);
                } else {
                    $query->orderBy('business_name', $sort['order']);
                }
                break;
            case 'relevance':
            default:
                // For relevance, we'll keep the original order from the search
                if ($sort['order'] === 'asc') {
                    $query->orderBy('business_name', 'asc');
                }
                break;
        }

        $businesses = $query->paginate($request->limit ?? 20);

        // Get available filters for response
        $availableFilters = [
            'categories' => Business::active()->distinct()->pluck('category')->filter()->values(),
            'locations' => Business::active()->distinct()->pluck('location.city')->filter()->values(),
            'distances' => ['5km', '10km', '25km', '50km', '100km'],
            'ratings' => ['3+', '4+', '4.5+'],
        ];

        return response()->json([
            'success' => true,
            'businesses' => $businesses->map(function ($business) {
                return [
                    'id' => $business->id,
                    'name' => $business->business_name,
                    'category' => $business->category,
                    'business_type' => $business->business_type,
                    'rating' => $business->rating,
                    'reviews' => $business->reviews_count,
                    'distance' => $business->distance ?? null,
                    'service_distance' => $business->service_distance,
                    'image' => $business->images->first()?->url,
                    'services' => $business->services,
                    'location' => [
                        'city' => $business->location['city'] ?? null,
                        'region' => $business->location['region'] ?? null,
                    ],
                    'verified' => $business->verified,
                    'badge' => $business->badge,
                ];
            }),
            'pagination' => [
                'page' => $businesses->currentPage(),
                'limit' => $businesses->perPage(),
                'total' => $businesses->total(),
                'totalPages' => $businesses->lastPage(),
            ],
            'filters' => $availableFilters
        ]);
    }

    /**
     * Search using images (Readdy.ai integration)
     */
    public function imageSearch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|string', // base64 encoded image
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Call Readdy.ai API
            $response = Http::post('https://readdy.ai/api/form/d2rfvq7frndo9ftj12l0', [
                'image' => $request->image,
                'description' => $request->description ?? '',
            ]);

            if (!$response->successful()) {
                throw new \Exception('Readdy.ai API error');
            }

            $results = $response->json();

            // Search businesses based on the image recognition results
            $searchTerms = is_array($results) ? implode(' ', $results) : $results;
            
            $businesses = Business::where('business_name', 'like', "%{$searchTerms}%")
                ->orWhere('description', 'like', "%{$searchTerms}%")
                ->orWhere('category', 'like', "%{$searchTerms}%")
                ->orWhereJsonContains('services', $searchTerms)
                ->orWhereJsonContains('product_keywords', $searchTerms)
                ->active()
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'businesses' => $businesses->map(function ($business) {
                    return [
                        'id' => $business->id,
                        'name' => $business->business_name,
                        'category' => $business->category,
                        'business_type' => $business->business_type,
                        'rating' => $business->rating,
                        'reviews' => $business->reviews_count,
                        'distance' => $business->service_distance,
                        'service_distance' => $business->service_distance,
                        'image' => $business->images->first()?->url,
                        'services' => $business->services,
                        'location' => [
                            'city' => $business->location['city'] ?? null,
                            'region' => $business->location['region'] ?? null,
                        ],
                        'verified' => $business->verified,
                        'badge' => $business->badge,
                    ];
                }),
                'pagination' => [
                    'page' => 1,
                    'limit' => 20,
                    'total' => $businesses->count(),
                    'totalPages' => 1,
                ],
                'filters' => [
                    'categories' => $businesses->pluck('category')->unique()->values(),
                    'locations' => $businesses->pluck('location.city')->unique()->filter()->values(),
                    'distances' => ['5km', '10km', '25km', '50km', '100km'],
                    'ratings' => ['3+', '4+', '4.5+'],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Image search failed. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
