<?php

namespace App\Http\Controllers\Api\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBusinessRequestRequest;
use App\Models\BusinessRequest;
use App\Models\Supplier;
use App\Models\SupplierProfile;
use App\Models\SupplierToSupplierInquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessRequestController extends Controller
{
    public function store(StoreBusinessRequestRequest $request)
    {
        $supplier = $request->user();
        
        // Check if supplier has non-basic plan
        if ($supplier->plan === 'Basic') {
            return response()->json([
                'message' => 'Only suppliers with non-basic plans can create business requests'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Create the business request
            $businessRequest = BusinessRequest::create([
                'requestType' => $request->requestType,
                'industry' => $request->industry,
                'preferred_distance' => $request->preferred_distance,
                'description' => $request->description,
                'supplier_id' => $supplier->id,
            ]);

            // Get supplier profile for location
            $supplierProfile = $supplier->profile;
            if (!$supplierProfile || !$supplierProfile->latitude || !$supplierProfile->longitude) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Supplier profile must have location coordinates'
                ], 400);
            }

            // Find matching suppliers (including Basic plans for receiving inquiries)
            $matchingSuppliers = $this->findMatchingSuppliersForInquiries(
                $request->industry,
                $request->preferred_distance,
                $supplierProfile->latitude,
                $supplierProfile->longitude,
                $supplier->id
            );

            // Send inquiries to matching suppliers
            $inquiriesCreated = 0;
            foreach ($matchingSuppliers as $targetSupplier) {
                $senderName = $supplierProfile->business_name ?? 'Business Request';

                SupplierToSupplierInquiry::create([
                    'sender_supplier_id' => $supplier->id,
                    'receiver_supplier_id' => $targetSupplier->id,
                    'sender_name' => $senderName,
                    'company' => $supplierProfile->business_name,
                    'email' => $supplier->email,
                    'phone' => $supplier->phone ?? '',
                    'subject' => "Business Request: {$request->industry}",
                    'message' => $request->description,
                    'type' => 'inquiry',
                ]);

                $inquiriesCreated++;
            }

            // If no matching suppliers found, keep the business request but return simple response
            if ($matchingSuppliers->isEmpty()) {
                DB::commit();

                return response()->json([
                    'message' => 'Business request created successfully',
                    'inquiries_sent' => 0,
                    'matching_suppliers_count' => 0,
                    'note' => 'No suppliers found matching both industry and distance criteria'
                ], 201);
            }

            DB::commit();

            return response()->json([
                'message' => 'Business request created successfully',
                'inquiries_sent' => $inquiriesCreated,
                'matching_suppliers_count' => count($matchingSuppliers)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create business request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function findMatchingSuppliersForInquiries($industry, $preferredDistance, $latitude, $longitude, $excludeSupplierId)
    {
        // Check if preferred distance is "anywhere" or similar
        $isAnywhere = strtolower(trim($preferredDistance)) === 'anywhere' || 
                     strtolower(trim($preferredDistance)) === 'any' || 
                     strtolower(trim($preferredDistance)) === 'worldwide';

        // Find suppliers with matching industry in their business_categories
        // Include ALL plans (including Basic) for receiving inquiries
        $matchingSuppliers = Supplier::with('profile')
            ->whereHas('profile', function ($query) use ($industry) {
                $query->whereJsonContains('business_categories', $industry)
                      ->orWhere(function ($subQuery) use ($industry) {
                          // Find categories that contain the industry as substring
                          $subQuery->whereRaw("JSON_SEARCH(business_categories, 'one', ?, NULL, '$[*]') IS NOT NULL", ["%{$industry}%"]);
                      });
            })
            ->where('id', '!=', $excludeSupplierId)
            ->get();

        // If "anywhere", only filter by category match
        if ($isAnywhere) {
            return $matchingSuppliers->filter(function ($supplier) use ($industry) {
                // Verify that at least one category contains the industry term
                $hasMatchingCategory = false;
                if ($supplier->profile && $supplier->profile->business_categories) {
                    foreach ($supplier->profile->business_categories as $category) {
                        if (stripos($category, $industry) !== false || stripos($industry, $category) !== false) {
                            $hasMatchingCategory = true;
                            break;
                        }
                    }
                }
                return $hasMatchingCategory;
            });
        }

        // For specific distances, require coordinates and filter by distance
        $distanceKm = $this->parseDistance($preferredDistance);
        
        if ($distanceKm === null) {
            return collect(); // Return empty collection if distance format is invalid
        }

        // Filter suppliers that have coordinates
        $suppliersWithCoords = $matchingSuppliers->filter(function ($supplier) {
            return $supplier->profile && 
                   $supplier->profile->latitude && 
                   $supplier->profile->longitude;
        });

        // Filter by distance and verify category match
        return $suppliersWithCoords->filter(function ($supplier) use ($latitude, $longitude, $distanceKm, $industry) {
            $distance = $this->calculateDistance(
                $latitude, 
                $longitude, 
                $supplier->profile->latitude, 
                $supplier->profile->longitude
            );
            
            // Additional check: verify that at least one category contains the industry term
            $hasMatchingCategory = false;
            if ($supplier->profile->business_categories) {
                foreach ($supplier->profile->business_categories as $category) {
                    if (stripos($category, $industry) !== false || stripos($industry, $category) !== false) {
                        $hasMatchingCategory = true;
                        break;
                    }
                }
            }
            
            return $distance <= $distanceKm && $hasMatchingCategory;
        });
    }

    private function findMatchingSuppliers($industry, $preferredDistance, $latitude, $longitude, $excludeSupplierId)
    {
        // Convert preferred distance to numeric value (assuming it's like "10km", "50km", etc.)
        $distanceKm = $this->parseDistance($preferredDistance);
        
        if ($distanceKm === null) {
            return collect(); // Return empty collection if distance format is invalid
        }

        // Find suppliers with matching industry in their business_categories
        // Use partial matching to find categories that contain the search term
        $matchingSuppliers = Supplier::with('profile')
            ->whereHas('profile', function ($query) use ($industry) {
                $query->whereJsonContains('business_categories', $industry)
                      ->orWhere(function ($subQuery) use ($industry) {
                          // Find categories that contain the industry as substring
                          $subQuery->whereRaw("JSON_SEARCH(business_categories, 'one', ?, NULL, '$[*]') IS NOT NULL", ["%{$industry}%"]);
                      });
            })
            ->where('id', '!=', $excludeSupplierId)
            ->where('plan', '!=', 'Basic') // Only non-basic plans
            ->whereHas('profile', function ($query) use ($latitude, $longitude) {
                $query->whereNotNull('latitude')
                      ->whereNotNull('longitude');
            })
            ->get();

        // Filter by distance and verify category match
        return $matchingSuppliers->filter(function ($supplier) use ($latitude, $longitude, $distanceKm, $industry) {
            $distance = $this->calculateDistance(
                $latitude, 
                $longitude, 
                $supplier->profile->latitude, 
                $supplier->profile->longitude
            );
            
            // Additional check: verify that at least one category contains the industry term
            $hasMatchingCategory = false;
            if ($supplier->profile->business_categories) {
                foreach ($supplier->profile->business_categories as $category) {
                    if (stripos($category, $industry) !== false || stripos($industry, $category) !== false) {
                        $hasMatchingCategory = true;
                        break;
                    }
                }
            }
            
            return $distance <= $distanceKm && $hasMatchingCategory;
        });
    }

    private function parseDistance($distanceString)
    {
        // Extract numeric value from distance string (e.g., "10km" -> 10, "50 miles" -> 50)
        if (preg_match('/(\d+)/', $distanceString, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        // Haversine formula to calculate distance between two points
        $earthRadius = 6371; // Earth's radius in kilometers

        $latDiff = deg2rad($lat2 - $lat1);
        $lonDiff = deg2rad($lon2 - $lon1);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDiff / 2) * sin($lonDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c; // Distance in kilometers
    }

    private function findAllSuppliersWithCategory($industry, $excludeSupplierId)
    {
        return Supplier::with('profile')
            ->whereHas('profile', function ($query) use ($industry) {
                $query->whereJsonContains('business_categories', $industry)
                      ->orWhere(function ($subQuery) use ($industry) {
                          $subQuery->whereRaw("JSON_SEARCH(business_categories, 'one', ?, NULL, '$[*]') IS NOT NULL", ["%{$industry}%"]);
                      });
            })
            ->where('id', '!=', $excludeSupplierId)
            ->get();
    }

    private function findSuppliersWithinDistance($preferredDistance, $latitude, $longitude, $excludeSupplierId)
    {
        $distanceKm = $this->parseDistance($preferredDistance);
        
        if ($distanceKm === null) {
            return collect();
        }

        return Supplier::with('profile')
            ->where('id', '!=', $excludeSupplierId)
            ->whereHas('profile', function ($query) use ($latitude, $longitude) {
                $query->whereNotNull('latitude')
                      ->whereNotNull('longitude');
            })
            ->get()
            ->filter(function ($supplier) use ($latitude, $longitude, $distanceKm) {
                $distance = $this->calculateDistance(
                    $latitude, 
                    $longitude, 
                    $supplier->profile->latitude, 
                    $supplier->profile->longitude
                );
                
                return $distance <= $distanceKm;
            });
    }

    private function findNonBasicSuppliers($excludeSupplierId)
    {
        return Supplier::where('id', '!=', $excludeSupplierId)
            ->where('plan', '!=', 'Basic')
            ->get();
    }
}
 