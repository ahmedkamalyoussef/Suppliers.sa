<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Supplier\RatingResource;
use App\Http\Resources\Supplier\SupplierSummaryResource;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Http\Resources\Public\BranchResource;

class PublicBusinessController extends Controller
{
    /**
     * Get business statistics
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStats()
    {
        $now = now();
        $day = strtolower($now->format('l'));
        $currentTime = $now->format('H:i:s');
        $startOfWeek = $now->copy()->startOfWeek();

        // statuses
        $validStatuses = ['active', 'approved', 'pending'];

        // All suppliers
        $suppliers = Supplier::whereIn('status', $validStatuses)
            ->with(['profile', 'branches'])
            ->get();

        $totalBusinesses = $suppliers->count();
        $openNowCount = 0;

        foreach ($suppliers as $supplier) {
            $isOpen = false;
            
            // Check main business working hours only
            if ($supplier->profile && $supplier->profile->working_hours) {
                $hours = $supplier->profile->working_hours;
                
                if (isset($hours[$day])) {
                    $daySchedule = $hours[$day];
                    
                    // Check if the business should be open based on time, regardless of closed flag
                    $isOpen = $currentTime >= $daySchedule['open'] && 
                             $currentTime <= $daySchedule['close'];
                    
                    $debug[$supplier->id] = [
                        'supplier_name' => $supplier->name,
                        'day' => $day,
                        'current_time' => $currentTime,
                        'open_time' => $daySchedule['open'],
                        'close_time' => $daySchedule['close'],
                        'is_closed' => $daySchedule['closed'] ? 'yes' : 'no',
                        'is_open' => $isOpen ? 'yes' : 'no',
                        'reason' => $daySchedule['closed'] ? 'marked_closed_but_open_by_hours' : 
                                   ($currentTime < $daySchedule['open'] ? 'before_opening' : 
                                   ($currentTime > $daySchedule['close'] ? 'after_closing' : 'open'))
                    ];
                }
            }
            
            if ($isOpen) {
                $openNowCount++;
            }
        }

        // Count new this week
        $newThisWeekCount = Supplier::whereIn('status', $validStatuses)
            ->where('created_at', '>=', $startOfWeek)
            ->count();

        $response = [
            'total_businesses' => $totalBusinesses,
            'total_suppliers' => $totalBusinesses,
            'open_now' => $openNowCount,
            'new_this_week' => $newThisWeekCount,
        ];
        
        return response()->json($response);
}

/* ------------------------------------------
|  CHECK IF SUPPLIER OR ANY BRANCH IS OPEN
--------------------------------------------*/
private function isSupplierOpen($supplier, string $day, string $now)
{
    // Try main profile
    if ($supplier->profile && $this->isOpenNow($supplier->profile->working_hours, $day, $now)) {
        return true;
    }

    // Try branches
    foreach ($supplier->branches as $branch) {
        if ($this->isOpenNow($branch->working_hours, $day, $now)) {
            return true;
        }
    }

    return false;
}

/* ------------------------------------------
|  UNIVERSAL TIME CHECKER
--------------------------------------------*/
private function isOpenNow($hours, string $day, string $now)
{
    if (!$hours || !isset($hours[$day])) {
        return false;
    }

    $d = $hours[$day];

    if ($d['closed']) {
        return false;
    }

    return $now >= $d['open'] && $now <= $d['close'];
}

    
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 12);
        $perPage = max(1, min($perPage, 50));

        $suppliers = Supplier::query()
            ->with(['profile'])
            ->withAvg('approvedRatings as rating_average', 'score')
            ->withCount('approvedRatings as rating_count');

        // Always exclude the current supplier's business if they are logged in
        if (auth()->check()) {
            $suppliers->where('id', '!=', auth()->id());
        }

        $suppliers = $this->applyFilters($suppliers, $request);

        $suppliers = $this->applySorting($suppliers, $request);

        $paginator = $suppliers->paginate($perPage)->appends($request->query());

        $data = $paginator->getCollection()->map(fn (Supplier $supplier) => (new SupplierSummaryResource($supplier))->toArray(request()));

        // Get available filters for response
        $availableCategories = Supplier::whereHas('profile', function (Builder $q) {
            $q->whereNotNull('business_categories');
        })->with('profile')->get()
            ->pluck('profile.business_categories')
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $availableLocations = Supplier::whereHas('profile', function (Builder $q) {
            $q->whereNotNull('business_address');
        })->with('profile')->get()
            ->pluck('profile.business_address')
            ->filter()
            ->map(fn ($addr) => explode(',', $addr)[0] ?? $addr)
            ->unique()
            ->values()
            ->toArray();

        $availableBusinessTypes = Supplier::whereHas('profile', function (Builder $q) {
            $q->whereNotNull('business_type');
        })->with('profile')->get()
            ->pluck('profile.business_type')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage()
            ]
        ]);
    }

    public function show(string $slug)
    {
        $supplier = Supplier::query()
            ->with(['profile', 'branches', 'approvedRatings'])
            ->withAvg('approvedRatings as rating_average', 'score')
            ->withCount('approvedRatings as rating_count')
            ->whereHas('profile', fn (Builder $query) => $query->where('slug', $slug))
            ->firstOrFail();

        $supplier->load(['approvedRatings' => function ($query) {
            $query->latest();
        }]);

        return response()->json([
            'supplier' => (new \App\Http\Resources\SupplierResource($supplier))->toArray(request()),
            'reviews' => $supplier->approvedRatings->map(fn ($rating) => (new RatingResource($rating))->toArray(request()))->toArray(),
            'branches' => $supplier->branches->map(fn ($branch) => (new BranchResource($branch))->toArray(request()))->toArray(),
        ]);
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        // Support both 'query' and 'keyword' for search
        $search = $request->input('keyword') ?: $request->input('query');
        $address = $request->input('address');

        // Filter by approved status (only apply if isApproved=true is explicitly set)
        if ($request->has('isApproved') && filter_var($request->input('isApproved'), FILTER_VALIDATE_BOOLEAN)) {
            $query->whereIn('status', ['active', 'approved']);
        }

        // Filter by open now based on working hours
        if ($request->has('isOpenNow') && filter_var($request->input('isOpenNow'), FILTER_VALIDATE_BOOLEAN)) {
            $now = now();
            $dayOfWeek = strtolower($now->format('l'));
            $currentTime = $now->format('H:i:s');
            
            $query->whereHas('profile', function($q) use ($dayOfWeek, $currentTime) {
                $q->whereJsonContains('working_hours->' . $dayOfWeek . '->is_open', true)
                  ->where('working_hours->' . $dayOfWeek . '->opening_time', '<=', $currentTime)
                  ->where('working_hours->' . $dayOfWeek . '->closing_time', '>=', $currentTime);
            });
        }

        // Search by keyword (excludes address)
        if ($search) {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('suppliers.name', 'like', "%{$search}%")
                    ->orWhereHas('profile', function (Builder $profileQuery) use ($search) {
                        $profileQuery->where('business_name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhereJsonContains('keywords', $search)
                            ->orWhereJsonContains('services_offered', $search);
                    })
                    ->orWhereHas('products', function (Builder $productQuery) use ($search) {
                        $productQuery->where('product_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('services', function (Builder $serviceQuery) use ($search) {
                        $serviceQuery->where('service_name', 'like', "%{$search}%");
                    });
            });
        }

        // Search by address (separate parameter)
        if ($address) {
            $query->whereHas('profile', function (Builder $profileQuery) use ($address) {
                $profileQuery->where('business_address', 'like', "%{$address}%");
            });
        }

        if ($category = $request->input('category')) {
            $query->whereHas('profile', function (Builder $profileQuery) use ($category) {
                $profileQuery->where(function (Builder $builder) use ($category) {
                    $builder->whereJsonContains('business_categories', $category)
                        ->orWhere('business_categories', 'like', "%{$category}%")
                        ->orWhere('business_type', 'like', "%{$category}%");
                });
            });
        }

        if ($categories = $request->input('categories')) {
            $categories = is_array($categories) ? $categories : explode(',', (string) $categories);
            $query->whereHas('profile', function (Builder $profileQuery) use ($categories) {
                $profileQuery->where(function (Builder $builder) use ($categories) {
                    foreach ($categories as $category) {
                        $builder->orWhereJsonContains('business_categories', $category)
                            ->orWhere('business_categories', 'like', "%{$category}%");
                    }
                });
            });
        }

        if ($location = $request->input('location')) {
            $query->whereHas('profile', function (Builder $profileQuery) use ($location) {
                $profileQuery->where('business_address', 'like', "%{$location}%");
            });
        }

        if ($businessType = $request->input('businessType')) {
            $query->whereHas('profile', function (Builder $profileQuery) use ($businessType) {
                $profileQuery->where('business_type', $businessType);
            });
        }

        // Support both 'rating' and 'minRating'
        $minRating = $request->input('minRating') ?: $request->input('rating');
        if ($minRating !== null) {
            $minRating = (int) $minRating;
            $query->having('rating_average', '>=', $minRating);
        }

        // Support both 'distance' and 'serviceDistance'
        $serviceDistance = $request->input('serviceDistance') ?: $request->input('distance');
        if ($serviceDistance !== null) {
            $serviceDistance = (int) $serviceDistance;
            $query->whereHas('profile', function (Builder $profileQuery) use ($serviceDistance) {
                $profileQuery->where(function (Builder $nested) use ($serviceDistance) {
                    $nested->whereNull('service_distance')
                        ->orWhere(function($q) use ($serviceDistance) {
                            $q->whereRaw("CAST(REPLACE(service_distance, 'km', '') AS DECIMAL(10,2)) <= ?", [$serviceDistance]);
                        });
                });
            });
        }

        // Filter by target customer
        if ($targetCustomer = $request->input('targetCustomer')) {
            $query->whereHas('profile', function (Builder $profileQuery) use ($targetCustomer) {
                $profileQuery->whereJsonContains('target_market', $targetCustomer);
            });
        }

        return $query;
    }

    private function applySorting(Builder $query, Request $request): Builder
    {
        $sort = $request->input('sort', 'relevance');

        switch ($sort) {
            case 'rating':
                // Sort by highest rating (descending)
                return $query->orderByDesc('rating_average')
                    ->orderByDesc('rating_count');

            case 'distance':
                // Sort by nearest distance (ascending)
                return $query->orderByRaw('CAST(REPLACE((select service_distance from supplier_profiles where supplier_profiles.supplier_id = suppliers.id), \'km\', \'\') AS DECIMAL(10,2)) ASC')
                    ->orderBy('suppliers.name');

            case 'reviews':
                // Sort by most reviews (descending)
                return $query->orderByDesc('rating_count')
                    ->orderByDesc('rating_average');

            case 'name':
                // Sort alphabetically by business name
                default:
                return $query->orderByRaw('(SELECT business_name FROM supplier_profiles WHERE supplier_profiles.supplier_id = suppliers.id)');
        }
    }
}
