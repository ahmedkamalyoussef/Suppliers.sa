<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Supplier\RatingResource;
use App\Models\Supplier;
use App\Models\SupplierRating;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PublicBusinessReviewController extends Controller
{
    public function index(Request $request, string $slug)
    {
        $supplier = Supplier::whereHas('profile', fn (Builder $query) => $query->where('slug', $slug))->firstOrFail();

        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 10);
        $sort = $request->query('sort', 'newest');

        $query = $supplier->approvedRatings();

        $query = match ($sort) {
            'oldest' => $query->oldest(),
            'highest' => $query->orderByDesc('score'),
            'lowest' => $query->orderBy('score'),
            default => $query->latest(),
        };

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $ratings = $paginator->getCollection()->map(function (SupplierRating $rating) {
            return [
                'id' => $rating->id,
                'customerName' => $rating->reviewer_name ?? ($rating->rater?->name ?? 'Anonymous'),
                'rating' => (int) $rating->score,
                'comment' => $rating->comment,
                'date' => optional($rating->created_at)->toIso8601String(),
                'verified' => (bool) $rating->is_approved,
                'response' => null, // TODO: Add response support if needed
            ];
        });

        // Calculate summary
        $allRatings = $supplier->approvedRatings()->get();
        $average = $allRatings->avg('score');
        $total = $allRatings->count();
        $distribution = $allRatings->groupBy('score')->map->count()->toArray();

        return response()->json([
            'data' => $ratings,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'summary' => [
                'average' => $average ? round((float) $average, 2) : 0,
                'total' => $total,
                'distribution' => [
                    '5' => $distribution[5] ?? 0,
                    '4' => $distribution[4] ?? 0,
                    '3' => $distribution[3] ?? 0,
                    '2' => $distribution[2] ?? 0,
                    '1' => $distribution[1] ?? 0,
                ],
            ],
        ]);
    }

    public function store(Request $request, string $slug)
    {
        $supplier = Supplier::whereHas('profile', fn (Builder $query) => $query->where('slug', $slug))->firstOrFail();

        $validator = Validator::make($request->all(), [
            'score' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $rating = SupplierRating::create([
            'rater_supplier_id' => null,
            'rated_supplier_id' => $supplier->id,
            'score' => $request->score,
            'comment' => $request->comment,
            'reviewer_name' => $request->input('name'),
            'reviewer_email' => $request->input('email'),
            'is_approved' => false,
            'status' => 'pending_review',
        ]);

        return response()->json([
            'message' => 'Thank you! Your review is pending approval.',
            'rating' => (new RatingResource($rating))->toArray(request()),
        ], 201);
    }
}
