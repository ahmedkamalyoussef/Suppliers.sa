<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class MapController extends Controller
{
    /**
     * Get businesses on map
     */
    public function businesses(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bounds' => 'required|array',
            'bounds.north' => 'required|numeric|between:-90,90',
            'bounds.south' => 'required|numeric|between:-90,90',
            'bounds.east' => 'required|numeric|between:-180,180',
            'bounds.west' => 'required|numeric|between:-180,180',
            'category' => 'nullable|string|max:255',
            'business_type' => 'nullable|string|in:Supplier,Service Provider,Manufacturer',
            'rating' => 'nullable|numeric|min:0|max:5',
            'verified' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Business::query()->active();

        // Filter by map bounds
        $query->whereRaw('JSON_EXTRACT(location, "$.coordinates.lat") BETWEEN ? AND ?', [
            $request->bounds['south'],
            $request->bounds['north']
        ])->whereRaw('JSON_EXTRACT(location, "$.coordinates.lng") BETWEEN ? AND ?', [
            $request->bounds['west'],
            $request->bounds['east']
        ]);

        // Apply filters
        if ($request->category) {
            $query->byCategory($request->category);
        }

        if ($request->business_type) {
            $query->byType($request->business_type);
        }

        if ($request->rating) {
            $query->where('rating', '>=', $request->rating);
        }

        if ($request->verified !== null) {
            $query->where('verified', $request->verified);
        }

        $businesses = $query->get();

        return response()->json([
            'success' => true,
            'businesses' => $businesses->map(function ($business) {
                return [
                    'id' => $business->id,
                    'name' => $business->business_name,
                    'category' => $business->category,
                    'business_type' => $business->business_type,
                    'location' => [
                        'lat' => $business->location['coordinates']['lat'] ?? null,
                        'lng' => $business->location['coordinates']['lng'] ?? null,
                        'address' => $business->location['address'] ?? null,
                        'city' => $business->location['city'] ?? null,
                        'region' => $business->location['region'] ?? null,
                    ],
                    'rating' => $business->rating,
                    'reviews' => $business->reviews_count,
                    'verified' => $business->verified,
                    'image' => $business->images->first()?->url,
                    'phone' => $business->phone,
                    'website' => $business->website,
                    'service_distance' => $business->service_distance,
                ];
            })
        ]);
    }

    /**
     * Get directions between two points
     */
    public function directions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'origin' => 'required|array',
            'origin.lat' => 'required|numeric|between:-90,90',
            'origin.lng' => 'required|numeric|between:-180,180',
            'destination' => 'required|array',
            'destination.lat' => 'required|numeric|between:-90,90',
            'destination.lng' => 'required|numeric|between:-180,180',
            'mode' => 'nullable|string|in:driving,walking,transit',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Using Google Maps Directions API
            // Note: You need to set up Google Maps API key in .env
            $apiKey = env('GOOGLE_MAPS_API_KEY');
            
            if (!$apiKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google Maps API key not configured'
                ], 500);
            }

            $origin = $request->origin['lat'] . ',' . $request->origin['lng'];
            $destination = $request->destination['lat'] . ',' . $request->destination['lng'];
            $mode = $request->mode ?? 'driving';

            $response = Http::get("https://maps.googleapis.com/maps/api/directions/json", [
                'origin' => $origin,
                'destination' => $destination,
                'mode' => $mode,
                'key' => $apiKey,
            ]);

            if (!$response->successful()) {
                throw new \Exception('Google Maps API error');
            }

            $data = $response->json();

            if ($data['status'] !== 'OK') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to calculate directions',
                    'error' => $data['error_message'] ?? 'Unknown error'
                ], 400);
            }

            $route = $data['routes'][0];
            $leg = $route['legs'][0];

            return response()->json([
                'success' => true,
                'directions' => [
                    'distance' => [
                        'text' => $leg['distance']['text'],
                        'value' => $leg['distance']['value'],
                    ],
                    'duration' => [
                        'text' => $leg['duration']['text'],
                        'value' => $leg['duration']['value'],
                    ],
                    'steps' => collect($leg['steps'])->map(function ($step) {
                        return [
                            'instruction' => $step['html_instructions'],
                            'distance' => $step['distance'],
                            'duration' => $step['duration'],
                            'start_location' => $step['start_location'],
                            'end_location' => $step['end_location'],
                        ];
                    }),
                    'polyline' => $route['overview_polyline']['points'],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Directions service unavailable. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Geocode address to coordinates
     */
    public function geocode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'address' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $apiKey = env('GOOGLE_MAPS_API_KEY');
            
            if (!$apiKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google Maps API key not configured'
                ], 500);
            }

            $response = Http::get("https://maps.googleapis.com/maps/api/geocode/json", [
                'address' => $request->address,
                'key' => $apiKey,
            ]);

            if (!$response->successful()) {
                throw new \Exception('Google Maps API error');
            }

            $data = $response->json();

            if ($data['status'] !== 'OK') {
                return response()->json([
                    'success' => false,
                    'message' => 'Address not found',
                    'error' => $data['error_message'] ?? 'Unknown error'
                ], 404);
            }

            $result = $data['results'][0];

            return response()->json([
                'success' => true,
                'location' => [
                    'lat' => $result['geometry']['location']['lat'],
                    'lng' => $result['geometry']['location']['lng'],
                    'formatted_address' => $result['formatted_address'],
                    'address_components' => collect($result['address_components'])->map(function ($component) {
                        return [
                            'long_name' => $component['long_name'],
                            'short_name' => $component['short_name'],
                            'types' => $component['types'],
                        ];
                    }),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Geocoding service unavailable. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reverse geocode coordinates to address
     */
    public function reverseGeocode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $apiKey = env('GOOGLE_MAPS_API_KEY');
            
            if (!$apiKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google Maps API key not configured'
                ], 500);
            }

            $response = Http::get("https://maps.googleapis.com/maps/api/geocode/json", [
                'latlng' => $request->lat . ',' . $request->lng,
                'key' => $apiKey,
            ]);

            if (!$response->successful()) {
                throw new \Exception('Google Maps API error');
            }

            $data = $response->json();

            if ($data['status'] !== 'OK') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to find address for these coordinates',
                    'error' => $data['error_message'] ?? 'Unknown error'
                ], 404);
            }

            $result = $data['results'][0];

            return response()->json([
                'success' => true,
                'address' => [
                    'formatted_address' => $result['formatted_address'],
                    'address_components' => collect($result['address_components'])->map(function ($component) {
                        return [
                            'long_name' => $component['long_name'],
                            'short_name' => $component['short_name'],
                            'types' => $component['types'],
                        ];
                    }),
                    'place_id' => $result['place_id'] ?? null,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Reverse geocoding service unavailable. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get nearby businesses
     */
    public function nearby(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|integer|min:1|max:100',
            'category' => 'nullable|string|max:255',
            'business_type' => 'nullable|string|in:Supplier,Service Provider,Manufacturer',
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
        $radius = $request->radius ?? 50;

        // Use Haversine formula for distance calculation
        $query->selectRaw(
            "*, (6371 * acos(cos(radians(?)) * cos(radians(JSON_EXTRACT(location, '$.coordinates.lat'))) * cos(radians(JSON_EXTRACT(location, '$.coordinates.lng')) - radians(?)) + sin(radians(?)) * sin(radians(JSON_EXTRACT(location, '$.coordinates.lat'))))) AS distance",
            [$request->lat, $request->lng, $request->lat]
        )->having('distance', '<=', $radius);

        // Apply filters
        if ($request->category) {
            $query->byCategory($request->category);
        }

        if ($request->business_type) {
            $query->byType($request->business_type);
        }

        $businesses = $query->orderBy('distance')
            ->limit($request->limit ?? 20)
            ->get();

        return response()->json([
            'success' => true,
            'businesses' => $businesses->map(function ($business) {
                return [
                    'id' => $business->id,
                    'name' => $business->business_name,
                    'category' => $business->category,
                    'business_type' => $business->business_type,
                    'location' => [
                        'lat' => $business->location['coordinates']['lat'] ?? null,
                        'lng' => $business->location['coordinates']['lng'] ?? null,
                        'address' => $business->location['address'] ?? null,
                        'city' => $business->location['city'] ?? null,
                        'region' => $business->location['region'] ?? null,
                    ],
                    'distance' => round($business->distance, 2),
                    'rating' => $business->rating,
                    'reviews' => $business->reviews_count,
                    'verified' => $business->verified,
                    'image' => $business->images->first()?->url,
                    'phone' => $business->phone,
                    'service_distance' => $business->service_distance,
                ];
            })
        ]);
    }
}
