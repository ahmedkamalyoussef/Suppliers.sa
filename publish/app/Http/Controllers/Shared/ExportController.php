<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessReview;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ExportController extends Controller
{
    /**
     * Export user data
     */
    public function users(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|string|in:csv,xlsx,pdf',
            'filters' => 'nullable|array',
            'filters.plan' => 'nullable|string|in:Basic,Premium,Enterprise',
            'filters.status' => 'nullable|string|in:active,inactive,suspended',
            'filters.date_from' => 'nullable|date',
            'filters.date_to' => 'nullable|date|after_or_equal:filters.date_from',
            'fields' => 'nullable|array',
            'fields.*' => 'string|in:id,name,email,phone,business_name,plan,status,join_date,last_active,revenue,profile_completion',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $query = User::query();

            // Apply filters
            if ($request->has('filters')) {
                $filters = $request->filters;

                if (! empty($filters['plan'])) {
                    $query->where('plan', $filters['plan']);
                }

                if (! empty($filters['status'])) {
                    $query->where('status', $filters['status']);
                }

                if (! empty($filters['date_from'])) {
                    $query->whereDate('created_at', '>=', $filters['date_from']);
                }

                if (! empty($filters['date_to'])) {
                    $query->whereDate('created_at', '<=', $filters['date_to']);
                }
            }

            $users = $query->get();

            // Select fields to export
            $fields = $request->fields ?? ['id', 'name', 'email', 'business_name', 'plan', 'status', 'join_date'];

            $data = $users->map(function ($user) use ($fields) {
                $row = [];
                foreach ($fields as $field) {
                    $row[$field] = match ($field) {
                        'join_date' => $user->created_at->format('Y-m-d H:i:s'),
                        'last_active' => $user->last_active?->format('Y-m-d H:i:s'),
                        'profile_completion' => $user->profile_completion.'%',
                        default => $user->$field,
                    };
                }

                return $row;
            });

            $filename = 'users_export_'.date('Y-m-d_H-i-s').".{$request->format}";
            $filepath = "exports/{$filename}";

            // Generate file based on format
            switch ($request->format) {
                case 'csv':
                    $this->generateCsv($data, $filepath);
                    break;
                case 'xlsx':
                    $this->generateXlsx($data, $filepath);
                    break;
                case 'pdf':
                    $this->generatePdf($data, $filepath, 'Users Export');
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Users data exported successfully',
                'download_url' => Storage::url($filepath),
                'filename' => $filename,
                'total_records' => $data->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export business data
     */
    public function businesses(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|string|in:csv,xlsx,pdf',
            'filters' => 'nullable|array',
            'filters.category' => 'nullable|string|max:255',
            'filters.business_type' => 'nullable|string|in:Supplier,Service Provider,Manufacturer',
            'filters.status' => 'nullable|string|in:active,inactive',
            'filters.verified' => 'nullable|boolean',
            'filters.date_from' => 'nullable|date',
            'filters.date_to' => 'nullable|date|after_or_equal:filters.date_from',
            'fields' => 'nullable|array',
            'fields.*' => 'string|in:id,business_name,category,business_type,description,phone,email,website,location,rating,reviews,verified,status,created_at',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $query = Business::query();

            // Apply filters
            if ($request->has('filters')) {
                $filters = $request->filters;

                if (! empty($filters['category'])) {
                    $query->byCategory($filters['category']);
                }

                if (! empty($filters['business_type'])) {
                    $query->byType($filters['business_type']);
                }

                if (! empty($filters['status'])) {
                    $query->where('status', $filters['status']);
                }

                if (isset($filters['verified'])) {
                    $query->where('verified', $filters['verified']);
                }

                if (! empty($filters['date_from'])) {
                    $query->whereDate('created_at', '>=', $filters['date_from']);
                }

                if (! empty($filters['date_to'])) {
                    $query->whereDate('created_at', '<=', $filters['date_to']);
                }
            }

            $businesses = $query->get();

            // Select fields to export
            $fields = $request->fields ?? ['id', 'business_name', 'category', 'business_type', 'phone', 'email', 'rating', 'reviews', 'verified', 'status'];

            $data = $businesses->map(function ($business) use ($fields) {
                $row = [];
                foreach ($fields as $field) {
                    $row[$field] = match ($field) {
                        'location' => $business->location['address'] ?? '',
                        'reviews' => $business->reviews_count,
                        'verified' => $business->verified ? 'Yes' : 'No',
                        'created_at' => $business->created_at->format('Y-m-d H:i:s'),
                        default => $business->$field,
                    };
                }

                return $row;
            });

            $filename = 'businesses_export_'.date('Y-m-d_H-i-s').".{$request->format}";
            $filepath = "exports/{$filename}";

            // Generate file based on format
            switch ($request->format) {
                case 'csv':
                    $this->generateCsv($data, $filepath);
                    break;
                case 'xlsx':
                    $this->generateXlsx($data, $filepath);
                    break;
                case 'pdf':
                    $this->generatePdf($data, $filepath, 'Businesses Export');
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Businesses data exported successfully',
                'download_url' => Storage::url($filepath),
                'filename' => $filename,
                'total_records' => $data->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export reviews data
     */
    public function reviews(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|string|in:csv,xlsx,pdf',
            'filters' => 'nullable|array',
            'filters.rating' => 'nullable|integer|min:1|max:5',
            'filters.status' => 'nullable|string|in:approved,pending,rejected',
            'filters.verified' => 'nullable|boolean',
            'filters.date_from' => 'nullable|date',
            'filters.date_to' => 'nullable|date|after_or_equal:filters.date_from',
            'fields' => 'nullable|array',
            'fields.*' => 'string|in:id,business_id,customer_name,customer_email,rating,title,comment,review_status,verified,submission_date,approval_date,helpful_count',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $query = BusinessReview::with('business');

            // Apply filters
            if ($request->has('filters')) {
                $filters = $request->filters;

                if (! empty($filters['rating'])) {
                    $query->where('rating', $filters['rating']);
                }

                if (! empty($filters['status'])) {
                    $query->where('review_status', $filters['status']);
                }

                if (isset($filters['verified'])) {
                    $query->where('verified', $filters['verified']);
                }

                if (! empty($filters['date_from'])) {
                    $query->whereDate('created_at', '>=', $filters['date_from']);
                }

                if (! empty($filters['date_to'])) {
                    $query->whereDate('created_at', '<=', $filters['date_to']);
                }
            }

            $reviews = $query->get();

            // Select fields to export
            $fields = $request->fields ?? ['id', 'business_id', 'customer_name', 'rating', 'title', 'review_status', 'verified', 'submission_date'];

            $data = $reviews->map(function ($review) use ($fields) {
                $row = [];
                foreach ($fields as $field) {
                    $row[$field] = match ($field) {
                        'business_id' => $review->business->business_name,
                        'verified' => $review->verified ? 'Yes' : 'No',
                        'submission_date' => $review->submission_date->format('Y-m-d H:i:s'),
                        'approval_date' => $review->approval_date?->format('Y-m-d H:i:s'),
                        default => $review->$field,
                    };
                }

                return $row;
            });

            $filename = 'reviews_export_'.date('Y-m-d_H-i-s').".{$request->format}";
            $filepath = "exports/{$filename}";

            // Generate file based on format
            switch ($request->format) {
                case 'csv':
                    $this->generateCsv($data, $filepath);
                    break;
                case 'xlsx':
                    $this->generateXlsx($data, $filepath);
                    break;
                case 'pdf':
                    $this->generatePdf($data, $filepath, 'Reviews Export');
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Reviews data exported successfully',
                'download_url' => Storage::url($filepath),
                'filename' => $filename,
                'total_records' => $data->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export analytics data
     */
    public function analytics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|string|in:csv,xlsx,pdf',
            'type' => 'required|string|in:user_growth,business_growth,revenue_summary,top_categories',
            'period' => 'required|string|in:today,week,month,quarter,year',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $type = $request->type;
            $period = $request->period;
            $dateRange = $this->getDateRange($period);

            $data = match ($type) {
                'user_growth' => $this->getUserGrowthData($dateRange),
                'business_growth' => $this->getBusinessGrowthData($dateRange),
                'revenue_summary' => $this->getRevenueSummaryData($dateRange),
                'top_categories' => $this->getTopCategoriesData($dateRange),
                default => [],
            };

            $filename = "{$type}_export_".date('Y-m-d_H-i-s').".{$request->format}";
            $filepath = "exports/{$filename}";

            // Generate file based on format
            switch ($request->format) {
                case 'csv':
                    $this->generateCsv($data, $filepath);
                    break;
                case 'xlsx':
                    $this->generateXlsx($data, $filepath);
                    break;
                case 'pdf':
                    $this->generatePdf($data, $filepath, ucfirst(str_replace('_', ' ', $type)).' Export');
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Analytics data exported successfully',
                'download_url' => Storage::url($filepath),
                'filename' => $filename,
                'total_records' => $data->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get export history
     */
    public function history(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // This would typically come from a database table tracking exports
        // For now, we'll return a mock response
        $exports = [
            [
                'id' => 1,
                'type' => 'users',
                'format' => 'csv',
                'filename' => 'users_export_2024-01-15_10-30-00.csv',
                'records' => 150,
                'status' => 'completed',
                'created_at' => '2024-01-15T10:30:00Z',
                'expires_at' => '2024-01-22T10:30:00Z',
            ],
            [
                'id' => 2,
                'type' => 'businesses',
                'format' => 'xlsx',
                'filename' => 'businesses_export_2024-01-14_15-45-00.xlsx',
                'records' => 75,
                'status' => 'completed',
                'created_at' => '2024-01-14T15:45:00Z',
                'expires_at' => '2024-01-21T15:45:00Z',
            ],
        ];

        return response()->json([
            'success' => true,
            'exports' => $exports,
            'total' => count($exports),
        ]);
    }

    /**
     * Download exported file
     */
    public function download(Request $request, $filename): JsonResponse
    {
        $filepath = "exports/{$filename}";

        if (! Storage::exists($filepath)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found or has expired',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'download_url' => Storage::url($filepath),
            'filename' => $filename,
        ]);
    }

    private function generateCsv($data, $filepath): void
    {
        $csv = \League\Csv\Writer::createFromStream(Storage::put($filepath, ''));

        if ($data->isNotEmpty()) {
            $csv->insertOne(array_keys($data->first()));

            foreach ($data as $row) {
                $csv->insertOne(array_values($row));
            }
        }
    }

    private function generateXlsx($data, $filepath): void
    {
        // This would typically use Laravel Excel package
        // For now, we'll create a simple CSV as placeholder
        $this->generateCsv($data, $filepath);
    }

    private function generatePdf($data, $filepath, $title): void
    {
        // This would typically use DOMPDF or similar package
        // For now, we'll create a simple text file as placeholder
        $content = "{$title}\n\n";

        if ($data->isNotEmpty()) {
            $content .= implode("\t", array_keys($data->first()))."\n";

            foreach ($data as $row) {
                $content .= implode("\t", array_values($row))."\n";
            }
        }

        Storage::put($filepath, $content);
    }

    private function getDateRange(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
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

    private function getUserGrowthData(array $dateRange): \Illuminate\Support\Collection
    {
        return User::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'Date' => $item->date,
                    'New Users' => $item->count,
                ];
            });
    }

    private function getBusinessGrowthData(array $dateRange): \Illuminate\Support\Collection
    {
        return Business::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'Date' => $item->date,
                    'New Businesses' => $item->count,
                ];
            });
    }

    private function getRevenueSummaryData(array $dateRange): \Illuminate\Support\Collection
    {
        // This would typically come from payment records
        // For now, returning empty collection
        return collect([]);
    }

    private function getTopCategoriesData(array $dateRange): \Illuminate\Support\Collection
    {
        return Business::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                return [
                    'Category' => $item->category,
                    'Count' => $item->count,
                ];
            });
    }
}
