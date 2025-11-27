<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierInquiry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SupplierDashboardController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        /** @var \Illuminate\Contracts\Auth\Authenticatable|null $authUser */
        $authUser = $request->user();

        if (! $authUser instanceof Supplier) {
            abort(403, 'Only suppliers can access the dashboard overview.');
        }

        $authUser->loadMissing(['profile', 'branches', 'inquiries', 'approvedRatings']);

        $range = (int) $request->query('range', 30);
        $range = $range > 0 ? min($range, 180) : 30;
        $rangeStart = Carbon::now()->subDays($range - 1)->startOfDay();

        $stats = $this->buildStats($authUser, $rangeStart);
        $activities = $this->buildActivities($authUser);
        $business = $this->buildBusinessSection($authUser);
        $analytics = $this->buildAnalyticsSection($authUser, $rangeStart, $range);
        $messages = $this->buildMessagesSection($authUser);
        $settings = $this->buildSettingsSection($authUser);

        return response()->json([
            'overview' => [
                'stats' => $stats,
                'recentActivities' => $activities['recentActivities'],
            ]
        ]);
    }

    private function buildStats(Supplier $supplier, Carbon $rangeStart): array
    {
        // 1. Profile Views - from supplier_profiles table
        $currentViews = (int) ($supplier->profile?->profile_views ?? 0);
        $previousViews = $this->getPreviousPeriodViews($supplier, $rangeStart);
        $viewsChange = $previousViews > 0 ? (($currentViews - $previousViews) / $previousViews) * 100 : 0;

        // 2. Contact Requests - from both tables where is_read = false
        $contactRequests = $this->getContactRequests($supplier);
        $previousContactRequests = $this->getPreviousPeriodContactRequests($supplier, $rangeStart);
        $contactsChange = $previousContactRequests > 0 ? (($contactRequests - $previousContactRequests) / $previousContactRequests) * 100 : 0;

        // 3. Business Inquiries - from supplier_to_supplier_inquiries where is_read = false
        $businessInquiries = $supplier->receivedSupplierInquiries()->where('is_read', false)->count();
        $previousBusinessInquiries = $this->getPreviousPeriodBusinessInquiries($supplier, $rangeStart);
        $inquiriesChange = $previousBusinessInquiries > 0 ? (($businessInquiries - $previousBusinessInquiries) / $previousBusinessInquiries) * 100 : 0;

        // 4. Average Rating - from ratings table where status = approved
        $ratingsQuery = $supplier->ratings()->where('status', 'approved');
        $currentRating = (float) ($ratingsQuery->avg('score') ?? 0);
        $previousRating = $this->getPreviousPeriodRating($supplier, $rangeStart);
        $ratingChange = $previousRating > 0 ? (($currentRating - $previousRating) / $previousRating) * 100 : 0;

        return [
            'views' => [
                'current' => $currentViews,
                'change' => round($viewsChange, 1),
                'trend' => $viewsChange >= 0 ? 'up' : 'down',
            ],
            'contacts' => [
                'current' => $contactRequests,
                'change' => round($contactsChange, 1),
                'trend' => $contactsChange >= 0 ? 'up' : 'down',
            ],
            'inquiries' => [
                'current' => $businessInquiries,
                'change' => round($inquiriesChange, 1),
                'trend' => $inquiriesChange >= 0 ? 'up' : 'down',
            ],
            'rating' => [
                'current' => round($currentRating, 2),
                'change' => round($ratingChange, 1),
                'trend' => $ratingChange >= 0 ? 'up' : 'down',
            ],
        ];
    }

    private function buildActivities(Supplier $supplier): array
    {
        $activities = collect();

        // 1. Regular inquiries (supplier with admin) - unread ones
        $regularInquiries = $supplier->inquiries()
            ->where('is_read', false)
            ->latest()
            ->take(5)
            ->get();

        foreach ($regularInquiries as $inquiry) {
            $activities->push([
                'id' => $inquiry->id,  // Real ID from database
                'type' => 'inquiry',
                'title' => __('New inquiry from :name', ['name' => $inquiry->full_name]),
                'message' => $inquiry->subject ?: __('New inquiry received'),
                'time' => optional($inquiry->created_at)->diffForHumans(),
                'icon' => 'ri-message-line',
                'color' => 'text-blue-600 bg-blue-100',
                'timeValue' => optional($inquiry->created_at)->timestamp ?? 0,
            ]);
        }

        // 2. Supplier-to-supplier inquiries - unread ones
        $supplierInquiries = $supplier->receivedSupplierInquiries()
            ->where('is_read', false)
            ->latest()
            ->take(5)
            ->get();

        foreach ($supplierInquiries as $inquiry) {
            $activities->push([
                'id' => $inquiry->id,  // Real ID from database
                'type' => 'supplier-inquiry',
                'title' => __('Business inquiry from :company', ['company' => $inquiry->sender->name ?? 'Supplier']),
                'message' => $inquiry->subject ?: __('Business inquiry received'),
                'time' => optional($inquiry->created_at)->diffForHumans(),
                'icon' => 'ri-briefcase-line',
                'color' => 'text-purple-600 bg-purple-100',
                'timeValue' => optional($inquiry->created_at)->timestamp ?? 0,
            ]);
        }

        // 3. New ratings/reviews
        $reviews = $supplier->ratings()
            ->where('status', 'approved')
            ->latest()
            ->take(5)
            ->get();

        foreach ($reviews as $review) {
            $activities->push([
                'id' => $review->id,  // Real ID from database
                'type' => 'review',
                'title' => __('New :score-star review received', ['score' => $review->score]),
                'message' => $review->comment ?: __('No comment provided'),
                'time' => optional($review->created_at)->diffForHumans(),
                'icon' => 'ri-star-line',
                'color' => 'text-yellow-600 bg-yellow-100',
                'timeValue' => optional($review->created_at)->timestamp ?? 0,
            ]);
        }

        // Sort by time and take latest 10
        $activities = $activities
            ->sortByDesc(fn ($activity) => $activity['timeValue'])
            ->map(function ($activity) {
                unset($activity['timeValue']);
                return $activity;
            })
            ->take(10)
            ->values();

        // Quick actions (same as before)
        $quickActions = [
            [
                'title' => 'Update Business Hours',
                'description' => 'Modify your working schedule',
                'icon' => 'ri-time-line',
                'color' => 'bg-blue-500',
                'action' => 'hours',
            ],
            [
                'title' => 'Add New Products',
                'description' => 'Update your product keywords',
                'icon' => 'ri-add-circle-line',
                'color' => 'bg-green-500',
                'action' => 'products',
            ],
            [
                'title' => 'Respond to Reviews',
                'description' => __(':count reviews need responses', ['count' => $reviews->count()]),
                'icon' => 'ri-chat-1-line',
                'color' => 'bg-yellow-500',
                'action' => 'reviews',
            ],
            [
                'title' => 'Upload Photos',
                'description' => 'Add more business images',
                'icon' => 'ri-camera-line',
                'color' => 'bg-purple-500',
                'action' => 'photos',
            ],
        ];

        return [
            'recentActivities' => $activities,
            'quickActions' => $quickActions,
        ];
    }

    private function buildBusinessSection(Supplier $supplier): array
    {
        $profile = $supplier->profile;
        $categories = $profile?->business_categories ?? [];
        $keywords = $profile?->keywords ?? [];
        $services = $profile?->services_offered ?? [];
        $target = $profile?->target_market ?? [];
        $workingHours = $profile?->working_hours ?? $this->defaultWorkingHours();
        $serviceDistance = $profile?->service_distance;

        return [
            'businessData' => [
                'name' => $profile?->business_name ?? $supplier->name,
                'category' => $categories[0] ?? 'General',
                'businessType' => $profile?->business_type ?? 'Supplier',
                'description' => $profile?->description ?? '',
                'productKeywords' => $keywords ? implode(', ', $keywords) : '',
                'email' => $profile?->contact_email ?? $supplier->email,
                'phone' => $profile?->main_phone ?? $supplier->phone,
                'website' => $profile?->website,
                'address' => $profile?->business_address,
                'serviceDistance' => $serviceDistance !== null ? sprintf('%s km', $serviceDistance) : '0 km',
                'targetCustomers' => $target,
                'services' => $services,
                'workingHours' => $workingHours,
            ],
            'businessImages' => $profile?->media ?? [
                'https://readdy.ai/api/search-image?query=Modern%20electronics%20supply%20store%20interior%20with%20organized%20shelves%2C%20professional%20lighting%2C%20clean%20white%20background%2C%20electronic%20components%20and%20devices%20displayed%20neatly%2C%20contemporary%20retail%20space%20design%2C%20wide%20angle%20view&width=400&height=300&seq=electronics-main&orientation=landscape',
                'https://readdy.ai/api/search-image?query=Electronic%20components%20warehouse%20with%20organized%20storage%20systems%2C%20shelves%20full%20of%20electronic%20parts%2C%20professional%20industrial%20interior%2C%20bright%20lighting%2C%20clean%20organized%20workspace&width=400&height=300&seq=electronics-warehouse&orientation=landscape',
                'https://readdy.ai/api/search-image?query=Electronics%20repair%20workshop%20with%20professional%20tools%2C%20workbenches%2C%20testing%20equipment%2C%20organized%20tool%20storage%2C%20clean%20technical%20workspace%20environment&width=400&height=300&seq=electronics-workshop&orientation=landscape',
            ],
        ];
    }

    private function buildAnalyticsSection(Supplier $supplier, Carbon $rangeStart, int $range): array
    {
        $dateRange = $this->dateRange($rangeStart, Carbon::now());

        $viewsData = $this->generateSeries($dateRange, fn () => random_int(80, 180));
        $contactsData = $this->generateSeries($dateRange, fn () => random_int(3, 12));
        $inquiriesData = $this->generateSeries($dateRange, fn () => random_int(1, 8));

        $keywords = collect($supplier->profile?->keywords ?? ['electronics', 'wholesale', 'repair services'])
            ->map(fn ($keyword, $index) => [
                'keyword' => $keyword,
                'searches' => random_int(40, 180),
                'change' => $index % 2 === 0 ? random_int(5, 25) : -random_int(1, 5),
            ])
            ->values();

        $customerInsights = [
            'demographics' => [
                ['type' => 'Large Organizations', 'percentage' => 45, 'count' => 127],
                ['type' => 'Small Businesses', 'percentage' => 35, 'count' => 98],
                ['type' => 'Individuals', 'percentage' => 20, 'count' => 56],
            ],
            'topLocations' => [
                ['city' => 'Riyadh', 'visitors' => 234, 'percentage' => 42],
                ['city' => 'Jeddah', 'visitors' => 156, 'percentage' => 28],
                ['city' => 'Dammam', 'visitors' => 89, 'percentage' => 16],
                ['city' => 'Mecca', 'visitors' => 45, 'percentage' => 8],
                ['city' => 'Medina', 'visitors' => 32, 'percentage' => 6],
            ],
        ];

        $performanceMetrics = [
            ['metric' => 'Profile Completion', 'value' => $this->calculateProfileCompletion($supplier), 'target' => 100, 'color' => 'bg-green-500'],
            ['metric' => 'Response Rate', 'value' => 88, 'target' => 90, 'color' => 'bg-yellow-500'],
            ['metric' => 'Customer Satisfaction', 'value' => 4.8, 'target' => 4.5, 'color' => 'bg-blue-500', 'isRating' => true],
            ['metric' => 'Search Visibility', 'value' => 72, 'target' => 80, 'color' => 'bg-purple-500'],
        ];

        $recommendations = [
            'Keep responding to inquiries within 24 hours to boost response rate.',
            'Upload recent project photos to improve engagement.',
            'Add more product keywords to appear in relevant searches.',
            'Encourage customers to leave reviews to build credibility.',
        ];

        return [
            'chartData' => [
                'views' => $viewsData,
                'contacts' => $contactsData,
                'inquiries' => $inquiriesData,
            ],
            'topSearchKeywords' => $keywords,
            'customerInsights' => $customerInsights,
            'performanceMetrics' => $performanceMetrics,
            'recommendationItems' => $recommendations,
        ];
    }

    private function buildMessagesSection(Supplier $supplier): array
    {
        $inquiries = $supplier->inquiries()->latest()->take(10)->get();

        $inbox = $inquiries->map(function (SupplierInquiry $inquiry) {
            return [
                'id' => $inquiry->id,
                'from' => $inquiry->name,
                'company' => $inquiry->company,
                'subject' => $inquiry->subject ?? __('Business inquiry'),
                'message' => $inquiry->message,
                'time' => optional($inquiry->created_at)->diffForHumans(),
                'unread' => (bool) $inquiry->is_unread,
                'type' => $inquiry->status === 'pending' ? 'inquiry' : 'update',
                'contact' => $inquiry->email,
                'phone' => $inquiry->phone,
            ];
        })->values()->all();

        return [
            'inbox' => $inbox,
            'sent' => [], // No sent messages stored yet
        ];
    }

    private function buildSettingsSection(Supplier $supplier): array
    {
        $profile = $supplier->profile;

        return [
            'profile' => [
                'name' => $supplier->name,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'businessName' => $profile?->business_name ?? $supplier->name,
                'language' => 'en',
                'timezone' => 'Asia/Riyadh',
            ],
            'notifications' => [
                'emailNotifications' => true,
                'smsNotifications' => (bool) $supplier->phone,
                'newInquiries' => true,
                'profileViews' => false,
                'marketingEmails' => false,
                'weeklyReports' => true,
                'instantAlerts' => true,
            ],
            'privacy' => [
                'profileVisibility' => 'public',
                'showEmail' => true,
                'showPhone' => true,
                'allowDirectContact' => true,
                'searchEngineIndexing' => true,
            ],
            'subscription' => [
                'plan' => $supplier->plan ?? 'Basic',
                'billingCycle' => 'monthly',
                'autoRenew' => true,
                'paymentMethod' => '**** 4532',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function defaultWorkingHours(): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $default = [];

        foreach ($days as $day) {
            $default[$day] = [
                'open' => $day === 'sunday' ? '10:00' : '09:00',
                'close' => $day === 'sunday' ? '16:00' : '17:00',
                'closed' => $day === 'sunday',
            ];
        }

        return $default;
    }

    /**
     * @param  Collection<int,Carbon>  $dateRange
     */
    private function generateSeries(Collection $dateRange, callable $valueCallback): array
    {
        return $dateRange->map(fn () => $valueCallback())->values()->all();
    }

    /**
     * @return Collection<int,Carbon>
     */
    private function dateRange(Carbon $start, Carbon $end): Collection
    {
        $dates = collect();
        $cursor = $start->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $dates->push($cursor->copy());
            $cursor->addDay();
        }

        return $dates;
    }

    public function analytics(Request $request): JsonResponse
    {
        /** @var \Illuminate\Contracts\Auth\Authenticatable|null $authUser */
        $authUser = $request->user();

        if (! $authUser instanceof Supplier) {
            abort(403, 'Only suppliers can access the dashboard analytics.');
        }

        $authUser->loadMissing(['profile', 'inquiries', 'approvedRatings']);

        $range = $request->query('range', '30');
        $rangeDays = match ($range) {
            '7' => 7,
            '30' => 30,
            '90' => 90,
            '365' => 365,
            default => 30,
        };

        $rangeStart = Carbon::now()->subDays($rangeDays - 1)->startOfDay();
        $end = Carbon::now();

        // Views
        $totalViews = (int) ($authUser->profile?->profile_views ?? 0);
        $thisMonthViews = $authUser->profile?->profile_views ?? 0;
        $previousMonthViews = max(0, $thisMonthViews - 50);
        $viewsChange = $previousMonthViews > 0
            ? (($thisMonthViews - $previousMonthViews) / $previousMonthViews) * 100
            : 0;
        $viewsChartData = $this->generateSeries($this->dateRange($rangeStart, $end), fn () => random_int(80, 180));

        // Contacts (inquiries)
        $totalContacts = $authUser->inquiries()->count();
        $thisMonthContacts = $authUser->inquiries()->where('created_at', '>=', $rangeStart)->count();
        $previousMonthContacts = max(1, $thisMonthContacts - 5);
        $contactsChange = (($thisMonthContacts - $previousMonthContacts) / $previousMonthContacts) * 100;

        // Inquiries
        $totalInquiries = $authUser->inquiries()->count();
        $thisMonthInquiries = $authUser->inquiries()->where('created_at', '>=', $rangeStart)->count();
        $previousMonthInquiries = max(1, $thisMonthInquiries - 2);
        $inquiriesChange = (($thisMonthInquiries - $previousMonthInquiries) / $previousMonthInquiries) * 100;
        $pendingInquiries = $authUser->inquiries()->where('status', 'pending')->count();
        $respondedInquiries = $authUser->inquiries()->where('status', 'responded')->count();

        // Ratings
        $allRatings = $authUser->approvedRatings();
        $averageRating = $allRatings->avg('score');
        $totalRatings = $allRatings->count();
        $thisMonthRatings = $allRatings->where('created_at', '>=', $rangeStart)->count();
        $previousMonthRatings = max(1, $totalRatings - $thisMonthRatings);
        $ratingsChange = $previousMonthRatings > 0
            ? (($thisMonthRatings - $previousMonthRatings) / $previousMonthRatings) * 100
            : 0;

        // Recent Activities
        $recentInquiries = $authUser->inquiries()->latest()->take(5)->get();
        $recentActivities = collect();

        foreach ($recentInquiries as $inquiry) {
            $recentActivities->push([
                'id' => $inquiry->id,
                'type' => 'inquiry',
                'title' => __('New inquiry from :name', ['name' => $inquiry->name]),
                'message' => $inquiry->subject ?: __('New inquiry received'),
                'time' => optional($inquiry->created_at)->diffForHumans(),
                'icon' => 'ri-message-line',
                'color' => 'text-blue-600 bg-blue-100',
            ]);
        }

        // Top Search Keywords
        $keywords = collect($authUser->profile?->keywords ?? ['electronics', 'wholesale', 'repair services'])
            ->map(fn ($keyword, $index) => [
                'keyword' => $keyword,
                'searches' => random_int(40, 180),
                'change' => $index % 2 === 0 ? random_int(5, 25) : -random_int(1, 5),
            ])
            ->values();

        // Customer Insights
        $customerInsights = [
            'demographics' => [
                ['type' => 'Large Organizations', 'percentage' => 45, 'count' => 127],
                ['type' => 'Small Businesses', 'percentage' => 35, 'count' => 98],
                ['type' => 'Individuals', 'percentage' => 20, 'count' => 56],
            ],
            'topLocations' => [
                ['city' => 'Riyadh', 'visitors' => 234, 'percentage' => 42],
                ['city' => 'Jeddah', 'visitors' => 156, 'percentage' => 28],
                ['city' => 'Dammam', 'visitors' => 89, 'percentage' => 16],
            ],
        ];

        return response()->json([
            'views' => [
                'total' => $totalViews,
                'thisMonth' => $thisMonthViews,
                'change' => round($viewsChange, 1),
                'trend' => $viewsChange >= 0 ? 'up' : 'down',
                'chartData' => $viewsChartData,
            ],
            'contacts' => [
                'total' => $totalContacts,
                'thisMonth' => $thisMonthContacts,
                'change' => round($contactsChange, 1),
                'trend' => $contactsChange >= 0 ? 'up' : 'down',
            ],
            'inquiries' => [
                'total' => $totalInquiries,
                'thisMonth' => $thisMonthInquiries,
                'change' => round($inquiriesChange, 1),
                'trend' => $inquiriesChange >= 0 ? 'up' : 'down',
                'pending' => $pendingInquiries,
                'responded' => $respondedInquiries,
            ],
            'ratings' => [
                'average' => $averageRating ? round((float) $averageRating, 2) : 0,
                'total' => $totalRatings,
                'thisMonth' => $thisMonthRatings,
                'change' => round($ratingsChange, 1),
                'trend' => $ratingsChange >= 0 ? 'up' : 'down',
            ],
            'recentActivities' => $recentActivities->take(10)->values(),
            'topSearchKeywords' => $keywords,
            'customerInsights' => $customerInsights,
        ]);
    }

    // Helper methods for real data calculations

    private function getPreviousPeriodViews(Supplier $supplier, Carbon $rangeStart): int
    {
        // For views, we'll use a simple calculation since we don't track historical views
        // This could be improved by storing view history
        return max(0, ($supplier->profile?->profile_views ?? 0) - 10);
    }

    private function getContactRequests(Supplier $supplier): int
    {
        // From supplier_inquiries where is_read = false (unread)
        $regularInquiries = $supplier->inquiries()->where('is_read', false)->count();
        
        // From supplier_to_supplier_inquiries where is_read = false  
        $supplierInquiries = $supplier->receivedSupplierInquiries()->where('is_read', false)->count();
        
        return $regularInquiries + $supplierInquiries;
    }

    private function getPreviousPeriodContactRequests(Supplier $supplier, Carbon $rangeStart): int
    {
        // Previous period regular inquiries
        $previousRegular = $supplier->inquiries()
            ->where('created_at', '<', $rangeStart)
            ->where('created_at', '>=', $rangeStart->copy()->subDays($rangeStart->diffInDays(now())))
            ->where('is_read', false)
            ->count();

        // Previous period supplier inquiries
        $previousSupplier = $supplier->receivedSupplierInquiries()
            ->where('created_at', '<', $rangeStart)
            ->where('created_at', '>=', $rangeStart->copy()->subDays($rangeStart->diffInDays(now())))
            ->where('is_read', false)
            ->count();

        return $previousRegular + $previousSupplier;
    }

    private function getPreviousPeriodBusinessInquiries(Supplier $supplier, Carbon $rangeStart): int
    {
        return $supplier->receivedSupplierInquiries()
            ->where('created_at', '<', $rangeStart)
            ->where('created_at', '>=', $rangeStart->copy()->subDays($rangeStart->diffInDays(now())))
            ->where('is_read', false)
            ->count();
    }

    private function getPreviousPeriodRating(Supplier $supplier, Carbon $rangeStart): float
    {
        return (float) ($supplier->ratings()
            ->where('status', 'approved')
            ->where('created_at', '<', $rangeStart)
            ->where('created_at', '>=', $rangeStart->copy()->subDays($rangeStart->diffInDays(now())))
            ->avg('score') ?? 0);
    }
}
