<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Supplier\RatingResource;
use App\Models\Admin;
use App\Models\SupplierRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminRatingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware(function ($request, $next) {
            $user = $request->user();

            if (! $user instanceof Admin) {
                return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
            }

            if ($user->isSuperAdmin()) {
                return $next($request);
            }

            $user->loadMissing('permissions');
            if (! $user->permissions || 
                (! $user->hasPermission('content_management_view') && ! $user->hasPermission('content_management_supervise'))) {
                return response()->json(['message' => 'Unauthorized. Content management permission required.'], 403);
            }

            return $next($request);
        });
    }

    public function index(Request $request): JsonResponse
    {
        $query = SupplierRating::query()->with(['rater.profile', 'rated.profile', 'moderatedBy']);

        if ($status = $request->query('status')) {
            if ($status === 'all') {
                $query->whereIn('status', ['rejected', 'flagged', 'pending_review']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->boolean('flagged')) {
            $query->whereNotNull('flagged_at');
        }

        if ($search = $request->query('search')) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('comment', 'like', "%{$search}%")
                    ->orWhere('reviewer_name', 'like', "%{$search}%")
                    ->orWhereHas('rated.profile', function ($profileQuery) use ($search) {
                        $profileQuery->where('business_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('rated', function ($supplierQuery) use ($search) {
                        $supplierQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = (int) $request->query('perPage', 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;

        $ratings = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $ratings->getCollection()->map(fn (SupplierRating $rating) => (new RatingResource($rating))->toArray(request()))->values(),
            'pagination' => [
                'currentPage' => $ratings->currentPage(),
                'perPage' => $ratings->perPage(),
                'total' => $ratings->total(),
                'lastPage' => $ratings->lastPage(),
            ],
        ]);
    }

    public function show(SupplierRating $rating): JsonResponse
    {
        $rating->load(['rater.profile', 'rated.profile', 'moderatedBy', 'flaggedBy']);

        return response()->json([
            'data' => (new RatingResource($rating))->toArray(request()),
        ]);
    }

    public function approve(Request $request, SupplierRating $rating): JsonResponse
    {
        $admin = $request->user();

        $rating->forceFill([
            'is_approved' => true,
            'status' => 'approved',
            'moderated_by_admin_id' => $admin->id,
            'moderated_at' => now(),
            'moderation_notes' => $request->input('notes'),
            'flagged_at' => null,
            'flagged_by_admin_id' => null,
        ])->save();

        return response()->json([
            'message' => 'Rating approved successfully.',
            'data' => (new RatingResource($rating->fresh()))->toArray(request()),
        ]);
    }

    public function reject(Request $request, SupplierRating $rating): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $admin = $request->user();

        $rating->forceFill([
            'is_approved' => false,
            'status' => 'rejected',
            'moderated_by_admin_id' => $admin->id,
            'moderated_at' => now(),
            'moderation_notes' => $validated['notes'] ?? null,
        ])->save();

        return response()->json([
            'message' => 'Rating rejected.',
            'data' => (new RatingResource($rating->fresh()))->toArray(request()),
        ]);
    }

    public function flag(Request $request, SupplierRating $rating): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $admin = $request->user();

        $rating->forceFill([
            'status' => 'flagged',
            'flagged_at' => now(),
            'flagged_by_admin_id' => $admin->id,
            'moderation_notes' => $validated['notes'] ?? $rating->moderation_notes,
        ])->save();

        return response()->json([
            'message' => 'Rating flagged for further review.',
            'data' => (new RatingResource($rating->fresh()))->toArray(request()),
        ]);
    }

    public function restore(Request $request, SupplierRating $rating): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending_review', 'approved'])],
        ]);

        $admin = $request->user();
        $status = $validated['status'];

        $rating->forceFill([
            'status' => $status,
            'is_approved' => $status === 'approved',
            'moderated_by_admin_id' => $status === 'approved' ? $admin->id : null,
            'moderated_at' => $status === 'approved' ? now() : null,
            'flagged_at' => null,
            'flagged_by_admin_id' => null,
        ])->save();

        return response()->json([
            'message' => 'Rating status updated.',
            'data' => (new RatingResource($rating->fresh()))->toArray(request()),
        ]);
    }
}
