<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    /**
     * Create a new review
     */
    public function store(Request $request, Business $business): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'required|string|max:255',
            'comment' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if user already reviewed this business
        $existingReview = BusinessReview::where('business_id', $business->id)
            ->where('customer_email', $request->customer_email)
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this business',
            ], 422);
        }

        $review = BusinessReview::create([
            'business_id' => $business->id,
            'customer_name' => $request->customer_name,
            'customer_email' => $request->customer_email,
            'rating' => $request->rating,
            'title' => $request->title,
            'comment' => $request->comment,
            'review_status' => 'pending_approval',
            'helpful_count' => 0,
            'verified' => false,
            'submission_date' => now(),
        ]);

        // Update business rating and review count
        $this->updateBusinessRating($business);

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully. It will be visible after approval.',
            'review' => [
                'id' => $review->id,
                'customer_name' => $review->customer_name,
                'rating' => $review->rating,
                'title' => $review->title,
                'comment' => $review->comment,
                'date' => $review->submission_date->toISOString(),
                'status' => $review->review_status,
            ],
        ], 201);
    }

    /**
     * Get business reviews
     */
    public function index(Request $request, Business $business): JsonResponse
    {
        $query = $business->reviews()->approved();

        // Apply filters
        if ($request->rating) {
            $query->byRating($request->rating);
        }

        if ($request->sort === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $reviews = $query->paginate($request->limit ?? 10);

        return response()->json([
            'success' => true,
            'reviews' => $reviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'customer_name' => $review->customer_name,
                    'rating' => $review->rating,
                    'title' => $review->title,
                    'comment' => $review->comment,
                    'date' => $review->created_at->toISOString(),
                    'helpful' => $review->helpful_count ?? 0,
                    'verified' => $review->verified,
                ];
            }),
            'pagination' => [
                'page' => $reviews->currentPage(),
                'limit' => $reviews->perPage(),
                'total' => $reviews->total(),
                'totalPages' => $reviews->lastPage(),
            ],
            'summary' => [
                'averageRating' => $business->rating,
                'totalReviews' => $business->reviews_count,
                'ratingDistribution' => $this->getRatingDistribution($business),
            ],
        ]);
    }

    /**
     * Mark review as helpful
     */
    public function helpful(Request $request, BusinessReview $review): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if user already marked this review as helpful
        // This would require a separate table for tracking helpful votes
        // For now, we'll just increment the count
        $review->markHelpful();

        return response()->json([
            'success' => true,
            'message' => 'Review marked as helpful',
            'helpful_count' => $review->helpful_count,
        ]);
    }

    /**
     * Report review (admin only)
     */
    public function report(Request $request, BusinessReview $review): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
            'reporter_email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Here you would typically:
        // 1. Create a report record in the database
        // 2. Send notification to admin
        // 3. Update review status if needed

        return response()->json([
            'success' => true,
            'message' => 'Review reported successfully. We will review it shortly.',
        ]);
    }

    /**
     * Approve review (admin only)
     */
    public function approve(BusinessReview $review): JsonResponse
    {
        $review->update([
            'review_status' => 'approved',
            'approval_date' => now(),
        ]);

        // Update business rating
        $this->updateBusinessRating($review->business);

        return response()->json([
            'success' => true,
            'message' => 'Review approved successfully',
        ]);
    }

    /**
     * Reject review (admin only)
     */
    public function reject(BusinessReview $review): JsonResponse
    {
        $review->update([
            'review_status' => 'rejected',
        ]);

        // Update business rating
        $this->updateBusinessRating($review->business);

        return response()->json([
            'success' => true,
            'message' => 'Review rejected successfully',
        ]);
    }

    /**
     * Get pending reviews (admin only)
     */
    public function pending(Request $request): JsonResponse
    {
        $reviews = BusinessReview::pending()
            ->with('business')
            ->orderBy('submission_date', 'desc')
            ->paginate($request->limit ?? 20);

        return response()->json([
            'success' => true,
            'reviews' => $reviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'customer_name' => $review->customer_name,
                    'customer_email' => $review->customer_email,
                    'rating' => $review->rating,
                    'title' => $review->title,
                    'comment' => $review->comment,
                    'submission_date' => $review->submission_date->toISOString(),
                    'business' => [
                        'id' => $review->business->id,
                        'name' => $review->business->business_name,
                    ],
                ];
            }),
            'pagination' => [
                'page' => $reviews->currentPage(),
                'limit' => $reviews->perPage(),
                'total' => $reviews->total(),
                'totalPages' => $reviews->lastPage(),
            ],
        ]);
    }

    /**
     * Get review statistics (admin only)
     */
    public function statistics(Request $request): JsonResponse
    {
        $totalReviews = BusinessReview::count();
        $pendingReviews = BusinessReview::pending()->count();
        $approvedReviews = BusinessReview::approved()->count();
        $rejectedReviews = BusinessReview::where('review_status', 'rejected')->count();

        $averageRating = BusinessReview::approved()->avg('rating') ?? 0;

        $recentReviews = BusinessReview::with('business')
            ->orderBy('submission_date', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($review) {
                return [
                    'id' => $review->id,
                    'customer_name' => $review->customer_name,
                    'rating' => $review->rating,
                    'business_name' => $review->business->business_name,
                    'submission_date' => $review->submission_date->toISOString(),
                    'status' => $review->review_status,
                ];
            });

        return response()->json([
            'success' => true,
            'statistics' => [
                'total' => $totalReviews,
                'pending' => $pendingReviews,
                'approved' => $approvedReviews,
                'rejected' => $rejectedReviews,
                'averageRating' => round($averageRating, 2),
            ],
            'recentReviews' => $recentReviews,
        ]);
    }

    private function updateBusinessRating(Business $business): void
    {
        $approvedReviews = $business->reviews()->approved();

        $averageRating = $approvedReviews->avg('rating') ?? 0;
        $reviewCount = $approvedReviews->count();

        $business->update([
            'rating' => round($averageRating, 2),
            'reviews_count' => $reviewCount,
        ]);
    }

    private function getRatingDistribution(Business $business): array
    {
        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        $business->reviews()->approved()->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->each(function ($count, $rating) use (&$distribution) {
                $distribution[$rating] = $count;
            });

        return $distribution;
    }
}
