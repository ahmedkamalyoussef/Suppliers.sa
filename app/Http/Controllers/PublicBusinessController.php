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

        return response()->json([
            'data' => $data,
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
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
        if ($search = $request->input('query')) {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('suppliers.name', 'like', "%{$search}%")
                    ->orWhereHas('profile', function (Builder $profileQuery) use ($search) {
                        $profileQuery->where('business_name', 'like', "%{$search}%")
                            ->orWhere('business_address', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
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

        if (($rating = $request->input('rating')) !== null) {
            $rating = (int) $rating;
            $query->having('rating_average', '>=', $rating);
        }

        if (($distance = $request->input('distance')) !== null) {
            $distance = (int) $distance;
            $query->whereHas('profile', function (Builder $profileQuery) use ($distance) {
                $profileQuery->where(function (Builder $nested) use ($distance) {
                    $nested->whereNull('service_distance')
                        ->orWhere('service_distance', '<=', $distance);
                });
            });
        }

        return $query;
    }

    private function applySorting(Builder $query, Request $request): Builder
    {
        $sort = $request->input('sort', 'rating');

        return match ($sort) {
            'name' => $query->orderBy('suppliers.name'),
            'distance' => $query->orderByRaw('COALESCE((select service_distance from supplier_profiles where supplier_profiles.supplier_id = suppliers.id), 999999)'),
            'recent' => $query->latest('suppliers.created_at'),
            default => $query->orderByDesc('rating_average')->orderByDesc('rating_count'),
        };
    }
}
