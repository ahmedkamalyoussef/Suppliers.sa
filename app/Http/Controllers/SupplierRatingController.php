<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Supplier;
use App\Models\SupplierRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplierRatingController extends Controller
{
    /**
     * Get ratings received by the authenticated supplier
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!($user instanceof Supplier)) {
            return response()->json(['message' => 'Only suppliers can view ratings'], 403);
        }

        $scope = $request->query('scope', 'received'); // 'received' or 'given'
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 15);
        $status = $request->query('status', 'approved');

        $query = $scope === 'given' 
            ? $user->ratingsGiven()
            : $user->ratingsReceived();

        if ($status !== 'all') {
            if ($status === 'approved') {
                $query->where('is_approved', true)->where('status', 'approved');
            } elseif ($status === 'pending_review') {
                $query->where('is_approved', false)->where('status', 'pending_review');
            } elseif ($status === 'rejected') {
                $query->where('status', 'rejected');
            }
        }

        $query->with(['rater.profile', 'rated.profile']);

        $paginator = $query->latest()->paginate($perPage, ['*'], 'page', $page);

        $ratings = $paginator->getCollection()->map(function (SupplierRating $rating) {
            $rater = $rating->rater;
            $raterProfile = $rater?->profile;
            
            return [
                'id' => $rating->id,
                'ratedBy' => $rater ? [
                    'id' => $rater->id,
                    'name' => $rater->name,
                    'businessName' => $raterProfile?->business_name ?? $rater->name,
                    'avatar' => $this->mediaUrl($rater->profile_image),
                ] : [
                    'id' => null,
                    'name' => $rating->reviewer_name ?? 'Anonymous',
                    'businessName' => null,
                    'avatar' => null,
                ],
                'score' => (int) $rating->score,
                'comment' => $rating->comment,
                'createdAt' => optional($rating->created_at)->toIso8601String(),
                'status' => $rating->status,
                'response' => null, // TODO: Add response support if needed
            ];
        });

        // Calculate summary
        $allRatings = $user->ratingsReceived()->where('is_approved', true)->get();
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
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
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

    /**
     * Store a newly created resource in storage.
     * Supplier can rate another supplier 1..5
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!($user instanceof Supplier)) {
            return response()->json(['message' => 'Only suppliers can create ratings'], 403);
        }

        if (!$request->has('rated_supplier_id') && $request->filled('ratedSupplierId')) {
            $request->merge(['rated_supplier_id' => $request->input('ratedSupplierId')]);
        }

        $validator = Validator::make($request->all(), [
            'rated_supplier_id' => 'required|exists:suppliers,id',
            'score' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        if ((int) $request->rated_supplier_id === (int) $user->id) {
            $validator->after(function ($v) {
                $v->errors()->add('rated_supplier_id', 'You cannot rate yourself');
            });
        }

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $rating = SupplierRating::create([
                'rater_supplier_id' => $user->id,
                'rated_supplier_id' => $request->rated_supplier_id,
                'score' => $request->score,
                'comment' => $request->comment,
            'reviewer_name' => $user->name,
            'reviewer_email' => $user->email,
                'is_approved' => false,
            'status' => 'pending_review',
        ]);

        return response()->json([
            'message' => 'Rating submitted and awaiting approval',
            'rating' => $this->transformRating($rating),
        ], 201);
    }

    /**
     * Approve a rating (super admin or admins with content_management_supervise)
     */
    public function approve(Request $request, SupplierRating $rating)
    {
        $user = $request->user();

        // Super admin or admin with supervise permission
        if ($user instanceof Admin) {
            if ($user->isSuperAdmin() || $user->hasPermission('content_management_supervise')) {
                $rating->forceFill([
                    'is_approved' => true,
                    'status' => 'approved',
                    'moderated_by_admin_id' => $user->id,
                    'moderated_at' => now(),
                ])->save();

                return response()->json([
                    'message' => 'Rating approved',
                    'rating' => $this->transformRating($rating->fresh()),
                ]);
            }
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }
    
    private function mediaUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (\Illuminate\Support\Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return url($path);
    }
}
