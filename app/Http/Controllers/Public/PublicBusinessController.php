<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Supplier\RatingResource;
use App\Http\Resources\Supplier\SupplierSummaryResource;
use App\Models\Supplier;
use App\Services\AISearchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Http\Resources\Public\BranchResource;
use App\Models\SupplierProfile;

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
        $validStatuses = ['active', 'approved'];

        // All suppliers
        $suppliers = Supplier::whereIn('status', $validStatuses)
            ->with(['profile', 'branches'])
            ->get();

        $totalBusinesses = SupplierProfile::count();
        $totalSuppliers = Supplier::whereIn('status', $validStatuses)->count();
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
        $newThisWeekCount = Supplier::where('created_at', '>=', $startOfWeek)
            ->count();

        $response = [
            'total_businesses' => $totalBusinesses,
            'total_suppliers' => $totalSuppliers,
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

        // Exclude suppliers who don't allow search engine indexing
        $suppliers->where('allow_search_engine_indexing', true);

        // Handle AI parameter
        if ($aiPrompt = $request->input('ai')) {
            $startTime = microtime(true);
            $suppliers = $this->applyAIFilters($suppliers, $aiPrompt);
            $endTime = microtime(true);
            
            if (($endTime - $startTime) > 5) {
                \Log::warning('AI search taking too long', ['time' => $endTime - $startTime, 'prompt' => $aiPrompt]);
            }
        }

        $suppliers = $this->applyFilters($suppliers, $request);

        $suppliers = $this->applySorting($suppliers, $request);

        $paginator = $suppliers->paginate($perPage)->appends($request->query());

        try {
            $data = $paginator->getCollection()->map(fn (Supplier $supplier) => (new SupplierSummaryResource($supplier))->toArray(request()));
        } catch (\Exception $e) {
            \Log::error('Error transforming suppliers to resource in index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $data = [];
        }

        // Log each supplier that appeared in results
        $appearedSuppliers = $paginator->getCollection()->pluck('id');
        $today = now()->toDateString();
        
        // Log total searches for today
        \DB::table('total_searches')
        ->updateOrInsert(
            ['date' => $today],
            [
                'search_count' => \DB::raw('search_count + 1'),
                'updated_at' => now()
                ]
            );
            if ($appearedSuppliers->isNotEmpty()) {
            
            // Log each supplier appearance
            foreach ($appearedSuppliers as $supplierId) {
                \DB::table('search_visibility_logs')
                    ->updateOrInsert(
                        ['supplier_id' => $supplierId, 'date' => $today],
                        [
                            'appearance_count' => \DB::raw('appearance_count + 1'),
                            'updated_at' => now()
                        ]
                    );
            }
        }

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

        $response = [
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage()
            ],
            'ai_info' => [
                'prompt' => $aiPrompt ?? null,
                'applied' => !empty($aiPrompt)
            ]
        ];

        // Add filters if requested
        if ($request->has('with_filters') && filter_var($request->input('with_filters'), FILTER_VALIDATE_BOOLEAN)) {
            $response['filters'] = [
                'categories' => $availableCategories,
                'locations' => $availableLocations,
                'business_types' => $availableBusinessTypes
            ];
        }

        return response()->json($response);
    }

    /**
     * Search businesses using AI (POST method)
     */
    public function aiSearch(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:500'
        ]);

        $aiPrompt = $request->input('prompt');
        
        // Use the same logic as the index method but with AI prompt
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

        // Handle AI parameter from body
        $suppliers = $this->applyAIFilters($suppliers, $aiPrompt);

        $suppliers = $this->applyFilters($suppliers, $request);
        $suppliers = $this->applySorting($suppliers, $request);

        $paginator = $suppliers->paginate($perPage)->appends($request->query());

        $data = $paginator->getCollection()->map(fn (Supplier $supplier) => (new SupplierSummaryResource($supplier))->toArray(request()));

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage()
            ],
            'ai_info' => [
                'prompt' => $aiPrompt,
                'applied' => true,
                'method' => 'POST'
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

    private function applyAIFilters(Builder $query, string $aiPrompt): Builder
{
    try {
        \Log::info('AI Search: Starting analysis', ['prompt' => $aiPrompt]);
        
        $aiService = new AISearchService();
        $aiResult = $aiService->analyzeQuery($aiPrompt);
        
        \Log::info('AI Search: Analysis complete', ['result' => $aiResult]);
        
        // Apply AI filters - Simplified approach
        if ($aiPrompt) {
            // Apply search query with synonyms - MORE FLEXIBLE
            // Skip keyword search when only rating is specified or when rating is 0
            $skipKeywordSearch = false;
            if (isset($aiResult['minRating']) && $aiResult['minRating'] !== null) {
                // If only rating is specified (no other meaningful keywords), skip keyword search
                $keywords = array_filter(array_map('trim', explode(' ', $aiResult['query'] ?? '')));
                $genericKeywords = ['جيد', 'ممتاز', 'موثوق', 'محترم', 'good', 'excellent', 'reliable', 'مورد', 'خدمة', 'شخص', 'شركة', 'supplier', 'service', 'provider', 'business', 'تقيم', 'rating', 'stars', 'نجوم', 'نجمة'];
                $meaningfulKeywords = array_diff($keywords, $genericKeywords);
                
                \Log::info('AI Search: Keyword analysis', [
                    'all_keywords' => $keywords,
                    'meaningful_keywords' => $meaningfulKeywords,
                    'minRating' => $aiResult['minRating']
                ]);
                
                if (count($meaningfulKeywords) === 0 || $aiResult['minRating'] == 0) {
                    $skipKeywordSearch = true;
                    \Log::info('AI Search: Skipping keyword search (only rating specified)');
                }
            }
            
            if (!$skipKeywordSearch && isset($aiResult['query']) && !empty($aiResult['query'])) {
                $searchTerms = $aiResult['query'];
                $keywords = array_filter(array_map('trim', explode(' ', $searchTerms)));

                $query->where(function (Builder $builder) use ($keywords) {
                    // Search in ALL relevant fields with OR logic
                    foreach ($keywords as $keyword) {
                        if (mb_strlen($keyword) >= 2) { // Only search meaningful keywords
                            $builder->orWhere('suppliers.name', 'like', "%{$keyword}%")
                                ->orWhereHas('profile', function (Builder $profileQuery) use ($keyword) {
                                    $profileQuery->where('business_name', 'like', "%{$keyword}%")
                                        ->orWhere('description', 'like', "%{$keyword}%")
                                        ->orWhere('business_type', 'like', "%{$keyword}%")
                                        ->orWhereJsonContains('keywords', $keyword)
                                        ->orWhereJsonContains('business_categories', $keyword)
                                        ->orWhere('business_categories', 'like', "%{$keyword}%")
                                        ->orWhere(function($q) use ($keyword) {
                                            // Search in services_offered JSON
                                            $q->whereRaw('LOWER(JSON_SEARCH(services_offered, "one", ?)) IS NOT NULL', ["%".strtolower($keyword)."%"]);
                                        });
                                })
                                ->orWhereHas('services', function (Builder $servicesQuery) use ($keyword) {
                                    $servicesQuery->where('service_name', 'like', "%{$keyword}%");
                                })
                                ->orWhereHas('products', function (Builder $productsQuery) use ($keyword) {
                                    $productsQuery->where('product_name', 'like', "%{$keyword}%");
                                });
                        }
                    }
                });
            }

            // Apply category filter from AI (if found) - ADDITIONAL FILTER, NOT REQUIRED
            if (isset($aiResult['category']) && !empty($aiResult['category'])) {
                $category = $aiResult['category'];
                $query->orWhereHas('profile', function (Builder $profileQuery) use ($category) {
                    $profileQuery->whereJsonContains('business_categories', $category);
                });
            }

            // Apply location filter from AI
            if (isset($aiResult['location']) && !empty($aiResult['location'])) {
                $location = $aiResult['location'];
                $query->whereHas('profile', function (Builder $profileQuery) use ($location) {
                    $profileQuery->where('business_address', 'like', "%{$location}%");
                });
            }

            // Apply minimum rating filter from AI
            if (isset($aiResult['minRating'])) {
                $minRating = (int) $aiResult['minRating'];
                \Log::info('AI Search: Applying rating filter', ['minRating' => $minRating]);
                
                // If minRating is 0, show only unrated businesses
                if ($minRating == 0) {
                    $query->whereDoesntHave('approvedRatings');
                }
                // If minRating is 1, include null ratings (unrated businesses)
                elseif ($minRating == 1) {
                    // For rating 1, show businesses with rating <= 1 or no rating
                    $query->where(function (Builder $builder) {
                        $builder->whereDoesntHave('approvedRatings')
                            ->orWhere(function(Builder $subQuery) {
                                $subQuery->having('rating_average', '<=', 1);
                            });
                    });
                }
                // If minRating is 2 or less, it's a "bad" rating request - look for low ratings
                elseif ($minRating <= 2) {
                    // For bad ratings, show businesses with rating <= 2
                    $query->having('rating_average', '<=', $minRating);
                } else {
                    // For good ratings, show businesses with rating >= minRating
                    $query->having('rating_average', '>=', $minRating);
                }
            }

            // Apply maximum rating filter from AI (NEW)
            if (isset($aiResult['maxRating'])) {
                $maxRating = (int) $aiResult['maxRating'];
                \Log::info('AI Search: Applying max rating filter', ['maxRating' => $maxRating]);
                
                // For max rating, show businesses with rating <= maxRating
                $query->having('rating_average', '<=', $maxRating);
            }

            // Apply isOpenNow filter from AI
            if (isset($aiResult['isOpenNow']) && $aiResult['isOpenNow'] === true) {
                \Log::info('AI Search: Applying open now filter');
                
                $now = now();
                $dayOfWeek = strtolower($now->format('l'));
                $currentTime = $now->format('H:i');
                
                $query->whereHas('branches', function (Builder $branchesQuery) use ($dayOfWeek, $currentTime) {
                    $branchesQuery->where(function (Builder $dayQuery) use ($dayOfWeek, $currentTime) {
                        $dayQuery->whereRaw("JSON_EXTRACT(working_hours, '$.{$dayOfWeek}.closed') = false")
                            ->whereRaw("JSON_EXTRACT(working_hours, '$.{$dayOfWeek}.open') <= ?", [$currentTime])
                            ->whereRaw("JSON_EXTRACT(working_hours, '$.{$dayOfWeek}.close') >= ?", [$currentTime]);
                    });
                });
            }
        }
        
        \Log::info('AI Search: All filters applied successfully');
        return $query;
        
    } catch (\Exception $e) {
        \Log::error('AI Search: Exception in applyAIFilters', [
            'prompt' => $aiPrompt,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        // Fallback: just search the original query
        $query->where(function (Builder $builder) use ($aiPrompt) {
            $builder->where('suppliers.name', 'like', "%{$aiPrompt}%")
                ->orWhereHas('profile', function (Builder $profileQuery) use ($aiPrompt) {
                    $profileQuery->where('business_name', 'like', "%{$aiPrompt}%")
                        ->orWhere('description', 'like', "%{$aiPrompt}%");
                });
        });
        
        return $query;
    }
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
                $q->whereJsonLength('working_hours->' . $dayOfWeek, '>', 0)
                  ->where('working_hours->' . $dayOfWeek . '->closed', false)
                  ->where('working_hours->' . $dayOfWeek . '->open', '<=', $currentTime)
                  ->where('working_hours->' . $dayOfWeek . '->close', '>=', $currentTime);
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
                            ->orWhere(function($q) use ($search) {
                                $searchTerm = strtolower($search);
                                $q->whereRaw('LOWER(JSON_SEARCH(services_offered, "one", ?)) IS NOT NULL', ["%$searchTerm%"])
                                  ->orWhereRaw('LOWER(JSON_SEARCH(services_offered, "one", ?)) IS NOT NULL', ["%" . ucfirst($searchTerm) . "%"]);
                            });
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