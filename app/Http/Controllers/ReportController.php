<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessReview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Get dashboard overview
     */
    public function dashboard(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period' => 'nullable|string|in:today,week,month,quarter,year',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $period = $request->period ?? 'month';
        $dateRange = $this->getDateRange($period);

        $data = [
            'overview' => $this->getOverviewStats($dateRange),
            'userStats' => $this->getUserStats($dateRange),
            'businessStats' => $this->getBusinessStats($dateRange),
            'reviewStats' => $this->getReviewStats($dateRange),
            'revenueStats' => $this->getRevenueStats($dateRange),
            'topCategories' => $this->getTopCategories($dateRange),
            'recentActivity' => $this->getRecentActivity(),
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get user analytics
     */
    public function users(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period' => 'nullable|string|in:today,week,month,quarter,year',
            'group_by' => 'nullable|string|in:day,week,month',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $period = $request->period ?? 'month';
        $groupBy = $request->group_by ?? 'day';
        $dateRange = $this->getDateRange($period);

        $data = [
            'totalUsers' => User::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->count(),
            'activeUsers' => User::whereBetween('last_active', [$dateRange['start'], $dateRange['end']])->count(),
            'newUsers' => User::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->count(),
            'usersByPlan' => $this->getUsersByPlan($dateRange),
            'usersByStatus' => $this->getUsersByStatus($dateRange),
            'userGrowth' => $this->getUserGrowth($dateRange, $groupBy),
            'topUsers' => $this->getTopUsers($dateRange),
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get business analytics
     */
    public function businesses(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period' => 'nullable|string|in:today,week,month,quarter,year',
            'group_by' => 'nullable|string|in:day,week,month',
            'category' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $period = $request->period ?? 'month';
        $groupBy = $request->group_by ?? 'day';
        $category = $request->category;
        $dateRange = $this->getDateRange($period);

        $query = Business::whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        
        if ($category) {
            $query->byCategory($category);
        }

        $data = [
            'totalBusinesses' => $query->count(),
            'activeBusinesses' => $query->where('status', 'active')->count(),
            'verifiedBusinesses' => $query->where('verified', true)->count(),
            'businessesByCategory' => $this->getBusinessesByCategory($dateRange),
            'businessesByType' => $this->getBusinessesByType($dateRange),
            'businessGrowth' => $this->getBusinessGrowth($dateRange, $groupBy, $category),
            'topRatedBusinesses' => $this->getTopRatedBusinesses($dateRange),
            'businessesByLocation' => $this->getBusinessesByLocation($dateRange),
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get review analytics
     */
    public function reviews(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period' => 'nullable|string|in:today,week,month,quarter,year',
            'group_by' => 'nullable|string|in:day,week,month',
            'status' => 'nullable|string|in:approved,pending,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $period = $request->period ?? 'month';
        $groupBy = $request->group_by ?? 'day';
        $status = $request->status;
        $dateRange = $this->getDateRange($period);

        $query = BusinessReview::whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        
        if ($status) {
            $query->where('review_status', $status);
        }

        $data = [
            'totalReviews' => $query->count(),
            'averageRating' => $query->avg('rating') ?? 0,
            'reviewsByStatus' => $this->getReviewsByStatus($dateRange),
            'reviewsByRating' => $this->getReviewsByRating($dateRange),
            'reviewGrowth' => $this->getReviewGrowth($dateRange, $groupBy),
            'ratingDistribution' => $this->getRatingDistribution($dateRange),
            'recentReviews' => $this->getRecentReviews($dateRange),
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get revenue analytics
     */
    public function revenue(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period' => 'nullable|string|in:today,week,month,quarter,year',
            'group_by' => 'nullable|string|in:day,week,month',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $period = $request->period ?? 'month';
        $groupBy = $request->group_by ?? 'day';
        $dateRange = $this->getDateRange($period);

        $data = [
            'totalRevenue' => $this->getTotalRevenue($dateRange),
            'revenueByPlan' => $this->getRevenueByPlan($dateRange),
            'revenueGrowth' => $this->getRevenueGrowth($dateRange, $groupBy),
            'averageRevenuePerUser' => $this->getAverageRevenuePerUser($dateRange),
            'revenueByMonth' => $this->getRevenueByMonth($dateRange),
            'topPayingCustomers' => $this->getTopPayingCustomers($dateRange),
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Export reports
     */
    public function export(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:users,businesses,reviews,revenue',
            'format' => 'required|string|in:csv,xlsx,pdf',
            'period' => 'nullable|string|in:today,week,month,quarter,year',
            'filters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $type = $request->type;
            $format = $request->format;
            $period = $request->period ?? 'month';
            $filters = $request->filters ?? [];
            $dateRange = $this->getDateRange($period);

            // Generate report based on type
            $data = match($type) {
                'users' => $this->exportUsers($dateRange, $filters),
                'businesses' => $this->exportBusinesses($dateRange, $filters),
                'reviews' => $this->exportReviews($dateRange, $filters),
                'revenue' => $this->exportRevenue($dateRange, $filters),
                default => [],
            };

            // Generate file path and name
            $filename = "{$type}_report_{$period}_" . date('Y-m-d_H-i-s') . ".{$format}";
            $filepath = "exports/{$filename}";

            // Here you would typically use Laravel Excel or similar package to generate the file
            // For now, we'll just return the data structure

            return response()->json([
                'success' => true,
                'message' => 'Report generated successfully',
                'download_url' => route('reports.download', ['filename' => $filename]),
                'filename' => $filename,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Report generation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getDateRange(string $period): array
    {
        $now = Carbon::now();
        
        return match($period) {
            'today' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            'week' => [
                'start' => $now->copy()->startOfWeek(),
                'end' => $now->copy()->endOfWeek(),
            ],
            'month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ],
            'quarter' => [
                'start' => $now->copy()->startOfQuarter(),
                'end' => $now->copy()->endOfQuarter(),
            ],
            'year' => [
                'start' => $now->copy()->startOfYear(),
                'end' => $now->copy()->endOfYear(),
            ],
            default => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ],
        };
    }

    private function getOverviewStats(array $dateRange): array
    {
        return [
            'totalUsers' => User::count(),
            'totalBusinesses' => Business::count(),
            'totalReviews' => BusinessReview::count(),
            'totalRevenue' => '0', // This would come from payment records
            'newUsers' => User::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->count(),
            'newBusinesses' => Business::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->count(),
            'newReviews' => BusinessReview::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->count(),
            'revenue' => '0', // This would come from payment records
        ];
    }

    private function getUserStats(array $dateRange): array
    {
        return [
            'total' => User::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->count(),
            'active' => User::whereBetween('last_active', [$dateRange['start'], $dateRange['end']])->count(),
            'byPlan' => $this->getUsersByPlan($dateRange),
            'growth' => $this->getUserGrowth($dateRange, 'day'),
        ];
    }

    private function getBusinessStats(array $dateRange): array
    {
        return [
            'total' => Business::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->count(),
            'active' => Business::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->where('status', 'active')->count(),
            'verified' => Business::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->where('verified', true)->count(),
            'byCategory' => $this->getBusinessesByCategory($dateRange),
            'growth' => $this->getBusinessGrowth($dateRange, 'day'),
        ];
    }

    private function getReviewStats(array $dateRange): array
    {
        return [
            'total' => BusinessReview::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->count(),
            'average' => BusinessReview::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->avg('rating') ?? 0,
            'byStatus' => $this->getReviewsByStatus($dateRange),
            'byRating' => $this->getReviewsByRating($dateRange),
        ];
    }

    private function getRevenueStats(array $dateRange): array
    {
        return [
            'total' => $this->getTotalRevenue($dateRange),
            'byPlan' => $this->getRevenueByPlan($dateRange),
            'growth' => $this->getRevenueGrowth($dateRange, 'day'),
        ];
    }

    private function getUsersByPlan(array $dateRange): array
    {
        return User::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('plan, COUNT(*) as count')
            ->groupBy('plan')
            ->pluck('count', 'plan')
            ->toArray();
    }

    private function getUsersByStatus(array $dateRange): array
    {
        return User::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    private function getUserGrowth(array $dateRange, string $groupBy): array
    {
        $groupBySql = match($groupBy) {
            'day' => 'DATE(created_at)',
            'week' => 'WEEK(created_at)',
            'month' => 'MONTH(created_at)',
            default => 'DATE(created_at)',
        };

        return User::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw("{$groupBySql} as period, COUNT(*) as count")
            ->groupBy('period')
            ->orderBy('period')
            ->pluck('count', 'period')
            ->toArray();
    }

    private function getTopUsers(array $dateRange): array
    {
        return User::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->withCount('businesses')
            ->orderBy('businesses_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'business_name' => $user->business_name,
                    'plan' => $user->plan,
                    'businesses_count' => $user->businesses_count,
                    'join_date' => $user->created_at->toISOString(),
                ];
            })
            ->toArray();
    }

    private function getBusinessesByCategory(array $dateRange): array
    {
        return Business::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->pluck('count', 'category')
            ->toArray();
    }

    private function getBusinessesByType(array $dateRange): array
    {
        return Business::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('business_type, COUNT(*) as count')
            ->groupBy('business_type')
            ->pluck('count', 'business_type')
            ->toArray();
    }

    private function getBusinessGrowth(array $dateRange, string $groupBy, ?string $category = null): array
    {
        $groupBySql = match($groupBy) {
            'day' => 'DATE(created_at)',
            'week' => 'WEEK(created_at)',
            'month' => 'MONTH(created_at)',
            default => 'DATE(created_at)',
        };

        $query = Business::whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        
        if ($category) {
            $query->byCategory($category);
        }

        return $query->selectRaw("{$groupBySql} as period, COUNT(*) as count")
            ->groupBy('period')
            ->orderBy('period')
            ->pluck('count', 'period')
            ->toArray();
    }

    private function getTopRatedBusinesses(array $dateRange): array
    {
        return Business::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->where('rating', '>', 0)
            ->orderBy('rating', 'desc')
            ->orderBy('reviews_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($business) {
                return [
                    'id' => $business->id,
                    'name' => $business->business_name,
                    'category' => $business->category,
                    'rating' => $business->rating,
                    'reviews_count' => $business->reviews_count,
                    'verified' => $business->verified,
                ];
            })
            ->toArray();
    }

    private function getBusinessesByLocation(array $dateRange): array
    {
        return Business::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('JSON_EXTRACT(location, "$.city") as city, COUNT(*) as count')
            ->groupBy('city')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->pluck('count', 'city')
            ->toArray();
    }

    private function getReviewsByStatus(array $dateRange): array
    {
        return BusinessReview::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('review_status, COUNT(*) as count')
            ->groupBy('review_status')
            ->pluck('count', 'review_status')
            ->toArray();
    }

    private function getReviewsByRating(array $dateRange): array
    {
        return BusinessReview::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->orderBy('rating')
            ->pluck('count', 'rating')
            ->toArray();
    }

    private function getReviewGrowth(array $dateRange, string $groupBy): array
    {
        $groupBySql = match($groupBy) {
            'day' => 'DATE(created_at)',
            'week' => 'WEEK(created_at)',
            'month' => 'MONTH(created_at)',
            default => 'DATE(created_at)',
        };

        return BusinessReview::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw("{$groupBySql} as period, COUNT(*) as count")
            ->groupBy('period')
            ->orderBy('period')
            ->pluck('count', 'period')
            ->toArray();
    }

    private function getRatingDistribution(array $dateRange): array
    {
        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        
        BusinessReview::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->each(function ($count, $rating) use (&$distribution) {
                $distribution[$rating] = $count;
            });

        return $distribution;
    }

    private function getRecentReviews(array $dateRange): array
    {
        return BusinessReview::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->with('business')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($review) {
                return [
                    'id' => $review->id,
                    'customer_name' => $review->customer_name,
                    'rating' => $review->rating,
                    'title' => $review->title,
                    'business_name' => $review->business->business_name,
                    'date' => $review->created_at->toISOString(),
                    'status' => $review->review_status,
                ];
            })
            ->toArray();
    }

    private function getTotalRevenue(array $dateRange): string
    {
        // This would typically come from payment records
        // For now, returning placeholder value
        return '0';
    }

    private function getRevenueByPlan(array $dateRange): array
    {
        // This would typically come from payment records
        // For now, returning placeholder values
        return [
            'Basic' => 0,
            'Premium' => 0,
            'Enterprise' => 0,
        ];
    }

    private function getRevenueGrowth(array $dateRange, string $groupBy): array
    {
        // This would typically come from payment records
        // For now, returning empty array
        return [];
    }

    private function getAverageRevenuePerUser(array $dateRange): string
    {
        // This would typically come from payment records
        // For now, returning placeholder value
        return '0';
    }

    private function getRevenueByMonth(array $dateRange): array
    {
        // This would typically come from payment records
        // For now, returning empty array
        return [];
    }

    private function getTopPayingCustomers(array $dateRange): array
    {
        // This would typically come from payment records
        // For now, returning empty array
        return [];
    }

    private function getTopCategories(array $dateRange): array
    {
        return Business::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->pluck('count', 'category')
            ->toArray();
    }

    private function getRecentActivity(): array
    {
        $activities = [];

        // Recent user registrations
        $recentUsers = User::orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($user) {
                return [
                    'type' => 'user_registered',
                    'message' => "New user {$user->name} registered",
                    'timestamp' => $user->created_at->toISOString(),
                    'data' => [
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                ];
            });

        // Recent business creations
        $recentBusinesses = Business::orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($business) {
                return [
                    'type' => 'business_created',
                    'message' => "New business {$business->business_name} added",
                    'timestamp' => $business->created_at->toISOString(),
                    'data' => [
                        'business_id' => $business->id,
                        'name' => $business->business_name,
                        'category' => $business->category,
                    ],
                ];
            });

        // Recent reviews
        $recentReviews = BusinessReview::orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($review) {
                return [
                    'type' => 'review_submitted',
                    'message' => "New review submitted for {$review->business->business_name}",
                    'timestamp' => $review->created_at->toISOString(),
                    'data' => [
                        'review_id' => $review->id,
                        'rating' => $review->rating,
                        'business_name' => $review->business->business_name,
                    ],
                ];
            });

        $activities = $recentUsers
            ->merge($recentBusinesses)
            ->merge($recentReviews)
            ->sortByDesc('timestamp')
            ->values()
            ->take(10)
            ->toArray();

        return $activities;
    }

    private function exportUsers(array $dateRange, array $filters): array
    {
        $query = User::whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        
        // Apply filters
        if (!empty($filters['plan'])) {
            $query->where('plan', $filters['plan']);
        }
        
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get()->map(function ($user) {
            return [
                'ID' => $user->id,
                'Name' => $user->name,
                'Email' => $user->email,
                'Phone' => $user->phone,
                'Business Name' => $user->business_name,
                'Plan' => $user->plan,
                'Status' => $user->status,
                'Join Date' => $user->created_at->format('Y-m-d H:i:s'),
                'Last Active' => $user->last_active?->format('Y-m-d H:i:s'),
                'Profile Completion' => $user->profile_completion . '%',
            ];
        })->toArray();
    }

    private function exportBusinesses(array $dateRange, array $filters): array
    {
        $query = Business::whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        
        // Apply filters
        if (!empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }
        
        if (!empty($filters['business_type'])) {
            $query->byType($filters['business_type']);
        }

        return $query->get()->map(function ($business) {
            return [
                'ID' => $business->id,
                'Name' => $business->business_name,
                'Category' => $business->category,
                'Type' => $business->business_type,
                'Description' => $business->description,
                'Phone' => $business->phone,
                'Email' => $business->email,
                'Website' => $business->website,
                'City' => $business->location['city'] ?? '',
                'Region' => $business->location['region'] ?? '',
                'Rating' => $business->rating,
                'Reviews' => $business->reviews_count,
                'Verified' => $business->verified ? 'Yes' : 'No',
                'Status' => $business->status,
                'Created Date' => $business->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    private function exportReviews(array $dateRange, array $filters): array
    {
        $query = BusinessReview::whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        
        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('review_status', $filters['status']);
        }
        
        if (!empty($filters['rating'])) {
            $query->where('rating', $filters['rating']);
        }

        return $query->with('business')->get()->map(function ($review) {
            return [
                'ID' => $review->id,
                'Business Name' => $review->business->business_name,
                'Customer Name' => $review->customer_name,
                'Customer Email' => $review->customer_email,
                'Rating' => $review->rating,
                'Title' => $review->title,
                'Comment' => $review->comment,
                'Status' => $review->review_status,
                'Verified' => $review->verified ? 'Yes' : 'No',
                'Helpful Count' => $review->helpful_count ?? 0,
                'Submission Date' => $review->submission_date->format('Y-m-d H:i:s'),
                'Approval Date' => $review->approval_date?->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    private function exportRevenue(array $dateRange, array $filters): array
    {
        // This would typically come from payment records
        // For now, returning empty array
        return [];
    }
}
