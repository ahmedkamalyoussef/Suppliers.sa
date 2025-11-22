<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PublicBusinessController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 12);
        $perPage = max(1, min($perPage, 50));

        $suppliers = Supplier::query()
            ->with(['profile'])
            ->withAvg('approvedRatings as rating_average', 'score')
            ->withCount('approvedRatings as rating_count');

        $suppliers = $this->applyFilters($suppliers, $request);

        $suppliers = $this->applySorting($suppliers, $request);

        $paginator = $suppliers->paginate($perPage)->appends($request->query());

        $data = $paginator->getCollection()->map(fn (Supplier $supplier) => $this->transformSupplierSummary($supplier));

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
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => [
                'categories' => $availableCategories,
                'locations' => $availableLocations,
                'businessTypes' => $availableBusinessTypes,
            ],
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
            'supplier' => $this->transformSupplier($supplier),
            'reviews' => $supplier->approvedRatings->map(fn ($rating) => $this->transformRating($rating))->toArray(),
            'branches' => $supplier->branches->map(fn ($branch) => $this->transformBranch($branch))->toArray(),
        ]);
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        // Support both 'query' and 'keyword' for search
        $search = $request->input('keyword') ?: $request->input('query');
        
        if ($search) {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('suppliers.name', 'like', "%{$search}%")
                    ->orWhereHas('profile', function (Builder $profileQuery) use ($search) {
                        $profileQuery->where('business_name', 'like', "%{$search}%")
                            ->orWhere('business_address', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhereJsonContains('keywords', $search);
                    });
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
                        ->orWhere('service_distance', '<=', $serviceDistance);
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

        return match ($sort) {
            'relevance' => $query->orderByDesc('rating_average')->orderByDesc('rating_count'),
            'rating' => $query->orderByDesc('rating_average')->orderByDesc('rating_count'),
            'reviews' => $query->orderByDesc('rating_count')->orderByDesc('rating_average'),
            'newest' => $query->latest('suppliers.created_at'),
            'name' => $query->orderBy('suppliers.name'),
            'distance' => $query->orderByRaw('COALESCE((select service_distance from supplier_profiles where supplier_profiles.supplier_id = suppliers.id), 999999)'),
            default => $query->orderByDesc('rating_average')->orderByDesc('rating_count'),
        };
    }
}
