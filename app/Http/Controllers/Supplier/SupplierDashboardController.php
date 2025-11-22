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
                'quickActions' => $activities['quickActions'],
            ],
            'business' => $business,
            'analytics' => $analytics,
            'messages' => $messages,
            'settings' => $settings,
        ]);
    }

    private function buildStats(Supplier $supplier, Carbon $rangeStart): array
    {
        $inquiriesQuery = $supplier->inquiries()->where('created_at', '>=', $rangeStart);
        $inquiryCount = (clone $inquiriesQuery)->count();
        $pendingInquiries = (clone $inquiriesQuery)->where('status', 'pending')->count();
        $unreadInquiries = (clone $inquiriesQuery)->where('is_unread', true)->count();

        $ratingsQuery = $supplier->approvedRatings()->where('created_at', '>=', $rangeStart);
        $newRatings = (clone $ratingsQuery)->count();
        $averageRating = (clone $ratingsQuery)->avg('score');

        if ($averageRating === null) {
            $averageRating = $supplier->approvedRatings()->avg('score');
        }

        return [
            'views' => [
                'current' => (int) ($supplier->profile?->profile_views ?? 0),
                'change' => 0.0,
                'trend' => 'up',
            ],
            'contacts' => [
                'current' => $inquiryCount,
                'change' => $inquiryCount > 0 ? 8.2 : 0.0,
                'trend' => $inquiryCount > 0 ? 'up' : 'neutral',
            ],
            'inquiries' => [
                'current' => $pendingInquiries,
                'change' => $pendingInquiries > 0 ? -3.1 : 0.0,
                'trend' => $pendingInquiries > 0 ? 'down' : 'neutral',
            ],
            'rating' => [
                'current' => $averageRating ? round((float) $averageRating, 2) : 0,
                'change' => $newRatings > 0 ? 0.2 : 0.0,
                'trend' => $newRatings > 0 ? 'up' : 'neutral',
            ],
            'totals' => [
                'pendingInquiries' => $pendingInquiries,
                'unreadInquiries' => $unreadInquiries,
                'newReviews' => $newRatings,
            ],
        ];
    }

    private function buildActivities(Supplier $supplier): array
    {
        $inquiries = $supplier->inquiries()->latest()->take(5)->get();
        $reviews = $supplier->approvedRatings()->latest()->take(5)->get();

        $activities = collect();

        foreach ($inquiries as $inquiry) {
            $activities->push([
                'id' => $inquiry->id,
                'type' => 'inquiry',
                'title' => __('New inquiry from :name', ['name' => $inquiry->name]),
                'message' => $inquiry->subject ?: __('New inquiry received'),
                'time' => optional($inquiry->created_at)->diffForHumans(),
                'icon' => 'ri-message-line',
                'color' => 'text-blue-600 bg-blue-100',
                'timeValue' => optional($inquiry->created_at)->timestamp ?? 0,
            ]);
        }

        foreach ($reviews as $review) {
            $activities->push([
                'id' => sprintf('review-%s', $review->id),
                'type' => 'review',
                'title' => __('New :score-star review received', ['score' => $review->score]),
                'message' => $review->comment ?: __('No comment provided'),
                'time' => optional($review->created_at)->diffForHumans(),
                'icon' => 'ri-star-line',
                'color' => 'text-yellow-600 bg-yellow-100',
                'timeValue' => optional($review->created_at)->timestamp ?? 0,
            ]);
        }

        $activities = $activities
            ->sortByDesc(fn ($activity) => $activity['timeValue'])
            ->map(function ($activity) {
                unset($activity['timeValue']);

                return $activity;
            })
            ->take(10)
            ->values();

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
                'description' => __(':count reviews need responses', ['count' => max(0, $reviews->count())]),
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
}
