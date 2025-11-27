<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function charts(Request $request): JsonResponse
    {
        /** @var Supplier $supplier */
        $supplier = $request->user();
        
        $range = (int) $request->query('range', 30);
        $range = min($range, 180);
        $type = $request->query('type', 'views');
        
        $startDate = Carbon::now()->subDays($range - 1)->startOfDay();
        
        $data = match($type) {
            'views' => $this->getViewsChartData($supplier, $startDate, $range),
            'contacts' => $this->getContactsChartData($supplier, $startDate, $range),
            'inquiries' => $this->getInquiriesChartData($supplier, $startDate, $range),
            default => []
        };
        
        return response()->json([
            'type' => $type,
            'range' => $range,
            'data' => $data,
            'labels' => $this->generateDateLabels($range)
        ]);
    }
    
    public function keywords(Request $request): JsonResponse
    {
        try {
            /** @var Supplier $supplier */
            $supplier = $request->user();
            
            // Get range parameter (default 30 days, max 180)
            $range = (int) $request->query('range', 30);
            $range = min($range, 180);
            
            // Get real search keywords from analytics_search_logs table
            $keywordsData = \DB::table('analytics_search_logs')
                ->selectRaw('
                    keyword,
                    COUNT(*) as searches,
                    COUNT(CASE WHEN resulted_in_contact = 1 THEN 1 END) as contacts,
                    MAX(searched_at) as last_searched
                ')
                ->where(function($query) use ($supplier) {
                    $query->where('supplier_id', $supplier->id)
                          ->orWhere('search_type', 'supplier');
                })
                ->where('searched_at', '>=', now()->subDays($range))
                ->groupBy('keyword')
                ->orderBy('searches', 'desc')
                ->limit(10)
                ->get();
            
            // Calculate change compared to previous period
            $keywords = [];
            foreach ($keywordsData as $data) {
                // Get previous period searches for comparison
                $previousSearches = \DB::table('analytics_search_logs')
                    ->where('keyword', $data->keyword)
                    ->whereBetween('searched_at', [
                        now()->subDays($range * 2)->startOfDay(),
                        now()->subDays($range)->endOfDay()
                    ])
                    ->count();
                
                $currentSearches = (int) $data->searches;
                $change = $previousSearches > 0 
                    ? round((($currentSearches - $previousSearches) / $previousSearches) * 100, 1)
                    : 0;
                
                $keywords[] = [
                    'keyword' => $data->keyword,
                    'searches' => $currentSearches,
                    'change' => $change,
                    'contacts' => (int) $data->contacts,
                    'last_searched' => date('Y-m-d', strtotime($data->last_searched))
                ];
            }
            
            $totalSearches = array_sum(array_column($keywords, 'searches'));
            $averageChange = count($keywords) > 0 ? round(array_sum(array_column($keywords, 'change')) / count($keywords), 1) : 0;
            
            return response()->json([
                'keywords' => $keywords,
                'totalSearches' => $totalSearches,
                'averageChange' => $averageChange,
                'period' => "Last {$range} days"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch keywords',
                'message' => $e->getMessage(),
                'keywords' => [],
                'totalSearches' => 0,
                'averageChange' => 0,
                'period' => 'Last 30 days'
            ], 500);
        }
    }
    
    public function insights(Request $request): JsonResponse
    {
        try {
            /** @var Supplier $supplier */
            $supplier = $request->user();
            
            // Get range parameter (default 30 days, max 180)
            $range = (int) $request->query('range', 30);
            $range = min($range, 180);
            
            // Get real demographics from visitor logs
            $demographicsData = \DB::table('analytics_visitor_logs')
                ->selectRaw('
                    customer_type,
                    COUNT(DISTINCT ip_address) as visitors,
                    COUNT(*) as sessions
                ')
                ->where('supplier_id', $supplier->id)
                ->whereNotNull('customer_type')
                ->where('last_visit', '>=', now()->subDays($range))
                ->groupBy('customer_type')
                ->orderBy('sessions', 'desc')
                ->get();
            
            $totalSessions = $demographicsData->sum('sessions');
            $demographics = [];
            
            foreach ($demographicsData as $data) {
                $percentage = $totalSessions > 0 ? round(($data->sessions / $totalSessions) * 100, 1) : 0;
                $demographics[] = [
                    'type' => $data->customer_type,
                    'percentage' => $percentage,
                    'count' => (int) $data->visitors
                ];
            }
            
            // Get real locations from visitor logs
            $locationsData = \DB::table('analytics_visitor_logs')
                ->selectRaw('
                    location,
                    COUNT(DISTINCT ip_address) as visitors,
                    COUNT(*) as sessions
                ')
                ->where('supplier_id', $supplier->id)
                ->whereNotNull('location')
                ->where('last_visit', '>=', now()->subDays($range))
                ->groupBy('location')
                ->orderBy('sessions', 'desc')
                ->limit(5)
                ->get();
            
            $totalLocationSessions = $locationsData->sum('sessions');
            $topLocations = [];
            
            foreach ($locationsData as $data) {
                $percentage = $totalLocationSessions > 0 ? round(($data->sessions / $totalLocationSessions) * 100, 1) : 0;
                $topLocations[] = [
                    'city' => $data->location,
                    'visitors' => (int) $data->visitors,
                    'percentage' => $percentage
                ];
            }
            
            // Get total visitors and customers in the range
            $totalVisitors = \DB::table('analytics_visitor_logs')
                ->where('supplier_id', $supplier->id)
                ->where('last_visit', '>=', now()->subDays($range))
                ->distinct('ip_address')
                ->count('ip_address');
            
            $totalCustomers = \DB::table('analytics_visitor_logs')
                ->where('supplier_id', $supplier->id)
                ->whereNotNull('customer_type')
                ->where('last_visit', '>=', now()->subDays($range))
                ->distinct('ip_address')
                ->count('ip_address');
            
            return response()->json([
                'demographics' => $demographics,
                'topLocations' => $topLocations,
                'totalVisitors' => $totalVisitors,
                'totalCustomers' => $totalCustomers,
                'period' => "Last {$range} days"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch insights',
                'message' => $e->getMessage(),
                'demographics' => [],
                'topLocations' => [],
                'totalVisitors' => 0,
                'totalCustomers' => 0,
                'period' => 'Last 30 days'
            ], 500);
        }
    }
    
    public function performance(Request $request): JsonResponse
    {
        /** @var Supplier $supplier */
        $supplier = $request->user();
        
        // Calculate real metrics where possible
        $profileCompletion = $this->computeProfileCompletion($supplier);
        $responseRate = $this->computeResponseRate($supplier);
        $customerSatisfaction = $this->computeCustomerSatisfaction($supplier);
        $searchVisibility = $this->computeSearchVisibility($supplier);
        
        $metrics = [
            [
                'metric' => 'Profile Completion',
                'value' => $profileCompletion,
                'target' => 100,
                'color' => 'bg-green-500',
                'unit' => '%'
            ],
            [
                'metric' => 'Response Rate',
                'value' => $responseRate,
                'target' => 90,
                'color' => 'bg-yellow-500',
                'unit' => '%'
            ],
            [
                'metric' => 'Customer Satisfaction',
                'value' => $customerSatisfaction,
                'target' => 4.5,
                'color' => 'bg-blue-500',
                'unit' => 'stars',
                'isRating' => true
            ],
            [
                'metric' => 'Search Visibility',
                'value' => $searchVisibility,
                'target' => 80,
                'color' => 'bg-purple-500',
                'unit' => '%'
            ]
        ];
        
        return response()->json([
            'metrics' => $metrics,
            'overallScore' => round(($profileCompletion + $responseRate + ($customerSatisfaction * 20) + $searchVisibility) / 4, 1)
        ]);
    }
    
    public function recommendations(Request $request): JsonResponse
    {
        /** @var Supplier $supplier */
        $supplier = $request->user();
        
        // Calculate current metrics
        $profileCompletion = $this->computeProfileCompletion($supplier);
        $responseRate = $this->computeResponseRate($supplier);
        $customerSatisfaction = $this->computeCustomerSatisfaction($supplier);
        $searchVisibility = $this->computeSearchVisibility($supplier);
        
        $totalInquiries = $supplier->inquiries()->count();
        $totalRatings = $supplier->ratings()->where('status', 'approved')->count();
        $profileViews = \DB::table('analytics_views_history')
            ->where('supplier_id', $supplier->id)
            ->sum('views_count');
        
        // Get recent activity
        $recentViews = \DB::table('analytics_views_history')
            ->where('supplier_id', $supplier->id)
            ->where('date', '>=', now()->subDays(7))
            ->sum('views_count');
        
        $recentSearches = \DB::table('analytics_search_logs')
            ->where('supplier_id', $supplier->id)
            ->where('searched_at', '>=', now()->subDays(7))
            ->count();
        
        // Generate recommendations based on actual data
        $recommendations = [];
        
        // Profile completion recommendations
        if ($profileCompletion < 100) {
            $recommendations[] = "Complete your profile to reach 100% - add missing business details";
        }
        
        // Response rate recommendations
        if ($totalInquiries > 0 && $responseRate < 90) {
            $recommendations[] = "Respond to customer inquiries faster to improve your response rate";
        } elseif ($totalInquiries === 0) {
            $recommendations[] = "Make your profile more attractive to get customer inquiries";
        }
        
        // Customer satisfaction recommendations
        if ($customerSatisfaction < 3.5) {
            $recommendations[] = "Improve your service quality to get better customer ratings";
        } elseif ($totalRatings < 5) {
            $recommendations[] = "Encourage satisfied customers to leave reviews to build trust";
        }
        
        // Search visibility recommendations
        if ($searchVisibility < 70) {
            $recommendations[] = "Add relevant keywords to appear in more search results";
        } elseif ($recentSearches < 10) {
            $recommendations[] = "Optimize your profile with better keywords and descriptions";
        }
        
        // Profile views recommendations
        if ($profileViews < 50) {
            $recommendations[] = "Upload more photos and detailed descriptions to attract more visitors";
        } elseif ($recentViews < 20) {
            $recommendations[] = "Update your profile regularly to maintain visitor engagement";
        }
        
        // Activity-based recommendations
        if ($totalInquiries === 0 && $profileViews < 20) {
            $recommendations[] = "Promote your business profile to increase visibility and inquiries";
        }
        
        // Performance-based recommendations
        if ($profileCompletion >= 90 && $responseRate >= 90 && $customerSatisfaction >= 4) {
            $recommendations[] = "Great performance! Consider expanding your services to reach more customers";
        }
        
        // If no specific recommendations, provide general ones
        if (empty($recommendations)) {
            $recommendations = [
                "Keep up the good work! Monitor your analytics regularly",
                "Consider seasonal promotions to boost customer engagement",
                "Stay active and update your profile with latest offerings"
            ];
        }
        
        // Determine priority based on overall performance
        $overallScore = ($profileCompletion + $responseRate + ($customerSatisfaction * 20) + $searchVisibility) / 4;
        
        if ($overallScore >= 80) {
            $priority = 'low';
        } elseif ($overallScore >= 60) {
            $priority = 'medium';
        } else {
            $priority = 'high';
        }
        
        return response()->json([
            'recommendations' => array_slice($recommendations, 0, 5), // Limit to 5 recommendations
            'priority' => $priority,
            'generated_at' => now()->toISOString(),
            'based_on' => [
                'profile_completion' => $profileCompletion,
                'response_rate' => $responseRate,
                'customer_satisfaction' => $customerSatisfaction,
                'search_visibility' => $searchVisibility,
                'total_inquiries' => $totalInquiries,
                'total_ratings' => $totalRatings,
                'profile_views' => $profileViews
            ]
        ]);
    }
    
    public function export(Request $request): \Illuminate\Http\Response
    {
        /** @var Supplier $supplier */
        $supplier = $request->user();
        
        $format = $request->query('format', 'csv');
        
        // Generate unified export data
        $exportData = [
            'profile_completion' => $this->computeProfileCompletion($supplier),
            'response_rate' => $this->computeResponseRate($supplier),
            'customer_satisfaction' => $this->computeCustomerSatisfaction($supplier),
            'search_visibility' => $this->computeSearchVisibility($supplier),
            'total_inquiries' => $supplier->inquiries()->count(),
            'total_ratings' => $supplier->ratings()->where('status', 'approved')->count(),
            'profile_views' => \DB::table('analytics_views_history')
                ->where('supplier_id', $supplier->id)
                ->sum('views_count'),
            'export_date' => now()->format('Y-m-d H:i:s'),
            'supplier_info' => [
                'name' => $supplier->name,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'category' => $supplier->profile?->category
            ]
        ];
        
        // Convert to CSV if requested
        if ($format === 'csv') {
            $csvData = $this->convertToCSV($exportData);
            $filename = 'analytics_export_' . date('Y-m-d_H-i-s') . '.csv';
            
            return response($csvData)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Cache-Control', 'no-cache, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        }
        
        // Return JSON
        return response()->json($exportData);
    }
    
    public function trackView(Request $request): JsonResponse
    {
        // Get authenticated user (the one viewing the profile)
        $viewer = $request->user();
        $supplierId = $request->input('supplier_id');
        $ipAddress = $request->ip() ?: '127.0.0.1'; // Fallback IP
        $userAgent = $request->userAgent() ?: 'Unknown'; // Fallback user agent
        $location = $request->input('location', 'Unknown');
        $customerType = $request->input('customer_type'); // Large Organizations, Small Businesses, Individuals
        
        if (!$supplierId) {
            return response()->json(['message' => 'Supplier ID required'], 400);
        }
        
        // Get or create visitor session
        $sessionId = $request->input('session_id', session()->getId());
        
        // Use viewer's info if available, otherwise use defaults
        $viewerId = $viewer ? $viewer->id : null;
        $viewerType = $viewer ? get_class($viewer) : 'Anonymous';
        
        // Check if this visitor viewed this supplier today
        $today = now()->format('Y-m-d');
        $existingView = \DB::table('analytics_visitor_logs')
            ->where('supplier_id', $supplierId)
            ->where('visitor_id', $viewerId)
            ->where('visitor_type', $viewerType)
            ->whereDate('last_visit', $today)
            ->first();
        
        if ($existingView) {
            // Update existing view
            \DB::table('analytics_visitor_logs')
                ->where('id', $existingView->id)
                ->update([
                    'last_visit' => now(),
                    'page_views' => $existingView->page_views + 1,
                    'duration_seconds' => $existingView->duration_seconds + ($request->input('duration', 0)),
                    'customer_type' => $customerType ?: $existingView->customer_type,
                    'location' => $location ?: $existingView->location
                ]);
        } else {
            // Create new visitor log
            \DB::table('analytics_visitor_logs')->insert([
                'supplier_id' => $supplierId,
                'visitor_id' => $viewerId,
                'visitor_type' => $viewerType,
                'session_id' => $sessionId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'location' => $location,
                'customer_type' => $customerType,
                'first_visit' => now(),
                'last_visit' => now(),
                'page_views' => 1,
                'duration_seconds' => $request->input('duration', 0),
                'resulted_in_inquiry' => false,
                'resulted_in_contact' => false
            ]);
        }
        
        // Update daily views history
        \DB::table('analytics_views_history')
            ->where('supplier_id', $supplierId)
            ->where('date', $today)
            ->increment('views_count', 1);
        
        // Check if this is a new unique visitor for today
        $existingVisitorToday = \DB::table('analytics_visitor_logs')
            ->where('supplier_id', $supplierId)
            ->where('visitor_id', $viewerId)
            ->where('visitor_type', $viewerType)
            ->whereDate('first_visit', $today)
            ->exists();
        
        // If no record exists for today, create one
        $historyRecord = \DB::table('analytics_views_history')
            ->where('supplier_id', $supplierId)
            ->where('date', $today)
            ->first();
        
        if (!$historyRecord) {
            // Create new record for today with 1 unique visitor
            \DB::table('analytics_views_history')->insert([
                'supplier_id' => $supplierId,
                'date' => $today,
                'views_count' => 1,
                'unique_visitors' => 1
            ]);
        } elseif (!$existingVisitorToday) {
            // Increment unique visitors only if this visitor hasn't visited today
            \DB::table('analytics_views_history')
                ->where('supplier_id', $supplierId)
                ->where('date', $today)
                ->increment('unique_visitors', 1);
        }
        
        // Update supplier profile views count (commented out - no profile_views column)
        // \DB::table('supplier_profiles')
        //     ->where('supplier_id', $supplierId)
        //     ->increment('profile_views');
        
        return response()->json(['message' => 'View tracked successfully']);
    }
    
    public function trackSearch(Request $request): JsonResponse
    {
        // Get authenticated user (the one searching)
        $searcher = $request->user();
        $keyword = $request->input('keyword');
        $searchType = $request->input('search_type', 'supplier'); // supplier, product, category
        $ipAddress = $request->ip() ?: '127.0.0.1'; // Fallback IP
        $userAgent = $request->userAgent() ?: 'Unknown'; // Fallback user agent
        $location = $request->input('location', 'Unknown');
        
        if (!$keyword) {
            return response()->json(['message' => 'Keyword required'], 400);
        }
        
        // Use searcher's info if available
        $searcherId = $searcher ? $searcher->id : null;
        $searcherType = $searcher ? get_class($searcher) : 'Anonymous';
        
        // Log the search (without supplier_id - it's for general search tracking)
        \DB::table('analytics_search_logs')->insert([
            'keyword' => $keyword,
            'search_type' => $searchType,
            'supplier_id' => null, // Not needed for general search tracking
            'searcher_id' => $searcherId,
            'searcher_type' => $searcherType,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'location' => $location,
            'searched_at' => now(),
            'resulted_in_contact' => false
        ]);
        
        return response()->json(['message' => 'Search tracked successfully']);
    }
    
    // Helper methods
    
    private function convertToCSV(array $data): string
    {
        if (empty($data)) {
            return '';
        }
        
        // For single record, create flat columns
        $headers = [
            'Profile Completion (%)',
            'Response Rate (%)',
            'Customer Satisfaction (stars)',
            'Search Visibility (%)',
            'Total Inquiries',
            'Total Ratings',
            'Profile Views',
            'Export Date',
            'Supplier Name',
            'Supplier Email',
            'Supplier Phone',
            'Supplier Category'
        ];
        
        $values = [
            $data['profile_completion'] ?? 0,
            $data['response_rate'] ?? 0,
            $data['customer_satisfaction'] ?? 0,
            $data['search_visibility'] ?? 0,
            $data['total_inquiries'] ?? 0,
            $data['total_ratings'] ?? 0,
            $data['profile_views'] ?? 0,
            $data['export_date'] ?? '',
            $data['supplier_info']['name'] ?? '',
            $data['supplier_info']['email'] ?? '',
            $data['supplier_info']['phone'] ?? '',
            $data['supplier_info']['category'] ?? ''
        ];
        
        // Create CSV with proper formatting
        $csv = '';
        
        // Add headers
        $csv .= $this->formatCSVRow($headers) . "\n";
        
        // Add data row
        $csv .= $this->formatCSVRow($values) . "\n";
        
        return $csv;
    }
    
    private function formatCSVRow(array $fields): string
    {
        $formattedFields = [];
        foreach ($fields as $field) {
            // Convert to string
            $field = (string) $field;
            
            // Escape quotes by doubling them
            $field = str_replace('"', '""', $field);
            
            // If field contains comma, newline, or quote, wrap in quotes
            if (strpos($field, ',') !== false || 
                strpos($field, '"') !== false || 
                strpos($field, "\n") !== false || 
                strpos($field, "\r") !== false) {
                $field = '"' . $field . '"';
            }
            
            $formattedFields[] = $field;
        }
        
        return implode(',', $formattedFields);
    }
    
    private function getViewsChartData(Supplier $supplier, Carbon $startDate, int $range): array
    {
        // Get real daily views from analytics_views_history table
        $data = [];
        
        for ($i = 0; $i < $range; $i++) {
            $date = $startDate->copy()->addDays($i);
            
            // Check if we have real data
            $viewRecord = \DB::table('analytics_views_history')
                ->where('supplier_id', $supplier->id)
                ->where('date', $date->format('Y-m-d'))
                ->first();
            
            if ($viewRecord) {
                $data[] = (int) $viewRecord->views_count;
            } else {
                // Fallback to current profile views if no history
                $data[] = (int) ($supplier->profile?->profile_views ?? 0);
            }
        }
        
        return $data;
    }
    
    private function getContactsChartData(Supplier $supplier, Carbon $startDate, int $range): array
    {
        // Get real daily contact requests from supplier_inquiries table
        $data = [];
        
        for ($i = 0; $i < $range; $i++) {
            $date = $startDate->copy()->addDays($i);
            
            // Count inquiries created on this date
            $contactsCount = $supplier->inquiries()
                ->whereDate('created_at', $date->format('Y-m-d'))
                ->count();
            
            $data[] = $contactsCount;
        }
        
        return $data;
    }
    
    private function getInquiriesChartData(Supplier $supplier, Carbon $startDate, int $range): array
    {
        // Get real daily business inquiries from supplier_to_supplier_inquiries table
        $data = [];
        
        for ($i = 0; $i < $range; $i++) {
            $date = $startDate->copy()->addDays($i);
            
            // Count supplier-to-supplier inquiries received on this date
            $inquiriesCount = $supplier->receivedSupplierInquiries()
                ->whereDate('created_at', $date->format('Y-m-d'))
                ->count();
            
            $data[] = $inquiriesCount;
        }
        
        return $data;
    }
    
    private function generateDateLabels(int $range): array
    {
        $labels = [];
        for ($i = $range - 1; $i >= 0; $i--) {
            $labels[] = Carbon::now()->subDays($i)->format('M j');
        }
        return $labels;
    }
    
    protected function computeProfileCompletion(Supplier $supplier): float
    {
        $fields = [
            'name' => $supplier->name ? 1 : 0,
            'email' => $supplier->email ? 1 : 0,
            'phone' => $supplier->phone ? 1 : 0,
            'description' => $supplier->profile?->description ? 1 : 0,
            'address' => $supplier->profile?->business_address ? 1 : 0,  // Fixed: use business_address
            'website' => $supplier->profile?->website ? 1 : 0,
            'category' => $supplier->profile?->category ? 1 : 0,
        ];
        
        return round((array_sum($fields) / count($fields)) * 100, 1);
    }
    
    protected function computeResponseRate(Supplier $supplier): float
    {
        // Calculate based on inquiries vs responses
        $totalInquiries = $supplier->inquiries()->count();
        $respondedInquiries = $supplier->inquiries()->whereNotNull('admin_id')->count();
        
        if ($totalInquiries === 0) return 100;
        
        return round(($respondedInquiries / $totalInquiries) * 100, 1);
    }
    
    protected function computeCustomerSatisfaction(Supplier $supplier): float
    {
        // Calculate based on ratings
        $ratings = $supplier->ratings()->where('status', 'approved')->get();
        
        if ($ratings->isEmpty()) return 4.0;
        
        return round($ratings->avg('score'), 1);
    }
    
    protected function computeSearchVisibility(Supplier $supplier): float
    {
        // Base score from profile completion
        $baseScore = 50;
        
        // Bonus for profile completion
        $profileCompletion = $this->computeProfileCompletion($supplier);
        $completionBonus = ($profileCompletion / 100) * 20;
        
        // Bonus for recent activity
        $recentActivity = 0;
        $lastInquiry = $supplier->inquiries()->latest()->first();
        if ($lastInquiry && $lastInquiry->created_at->diffInDays(now()) <= 30) {
            $recentActivity = 10;
        }
        
        return round($baseScore + $completionBonus + $recentActivity, 2);
    }
}
