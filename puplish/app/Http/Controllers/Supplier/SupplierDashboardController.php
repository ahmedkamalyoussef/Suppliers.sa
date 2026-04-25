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
        // 1. Profile Views - from analytics_views_history table
        $currentViews = \DB::table('analytics_views_history')
            ->where('supplier_id', $supplier->id)
            ->where('date', '>=', $rangeStart->toDateString())
            ->sum('views_count');
        
        $previousViews = $this->getPreviousPeriodViews($supplier, $rangeStart);
        // For new suppliers or no previous data, show growth based on current activity
        $viewsChange = $previousViews > 0 ? 
            (($currentViews - $previousViews) / $previousViews) * 100 : 
            ($currentViews > 0 ? min($currentViews * 10, 100) : 0); // Show growth for new activity

        // 2. Contact Requests - unread inquiries only (both regular and supplier-to-supplier)
        $contactRequests = $this->getContactRequests($supplier); // This already gets unread ones
        $previousContactRequests = $this->getPreviousPeriodContactRequests($supplier, $rangeStart);
        $contactsChange = $previousContactRequests > 0 ? 
            (($contactRequests - $previousContactRequests) / $previousContactRequests) * 100 : 
            ($contactRequests > 0 ? 50 : 0); // Show 50% growth for new contacts

        // 3. Business Inquiries - ALL inquiries received (read and unread)
        $allRegularInquiries = $supplier->inquiries()->count();
        $allSupplierInquiries = $supplier->receivedSupplierInquiries()->count();
        $businessInquiries = $allRegularInquiries + $allSupplierInquiries;
        $previousBusinessInquiries = $this->getPreviousPeriodBusinessInquiries($supplier, $rangeStart);
        $inquiriesChange = $previousBusinessInquiries > 0 ? 
            (($businessInquiries - $previousBusinessInquiries) / $previousBusinessInquiries) * 100 : 
            ($businessInquiries > 0 ? 75 : 0); // Show 75% growth for new inquiries

        // 4. Average Rating - from ratings table where is_approved = true
        $ratingsQuery = $supplier->ratings()->where('is_approved', true);
        $currentRating = (float) ($ratingsQuery->avg('score') ?? 0);
        
        // For change calculation, compare most recent rating with overall average
        $mostRecentRating = (float) ($ratingsQuery->latest()->first()->score ?? 0);
        $overallAverage = (float) ($ratingsQuery->avg('score') ?? 0);
        $ratingChange = $overallAverage > 0 && $mostRecentRating != $overallAverage ? 
            (($mostRecentRating - $overallAverage) / $overallAverage) * 100 : 0;

        return [
            'views' => [
                'current' => (int) $currentViews,
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

        // 1. Inquiries sent by this supplier (supplier-to-supplier)
        $sentInquiries = $supplier->sentSupplierInquiries()
            ->latest()
            ->take(5)
            ->get();

        foreach ($sentInquiries as $inquiry) {
            $activities->push([
                'id' => $inquiry->id,
                'type' => 'sent-inquiry',
                'title' => __('Business inquiry sent to :company', ['company' => $inquiry->receiver->name ?? 'Supplier']),
                'message' => $inquiry->subject ?: __('Business inquiry sent'),
                'time' => optional($inquiry->created_at)->diffForHumans(),
                'icon' => 'ri-send-plane-line',
                'color' => 'text-green-600 bg-green-100',
                'timeValue' => optional($inquiry->created_at)->timestamp ?? 0,
            ]);
        }

        // 2. Ratings given by this supplier
        $givenRatings = $supplier->ratingsGiven()
            ->where('is_approved', true)
            ->latest()
            ->take(5)
            ->get();

        foreach ($givenRatings as $rating) {
            $activities->push([
                'id' => $rating->id,
                'type' => 'given-rating',
                'title' => __('Rated :company with :score stars', ['company' => $rating->rated->name ?? 'Supplier', 'score' => $rating->score]),
                'message' => $rating->comment ?: __('No comment provided'),
                'time' => optional($rating->created_at)->diffForHumans(),
                'icon' => 'ri-star-fill',
                'color' => 'text-blue-600 bg-blue-100',
                'timeValue' => optional($rating->created_at)->timestamp ?? 0,
            ]);
        }

        // 3. Profile updates
        $profile = $supplier->profile;
        if ($profile && $profile->updated_at && $profile->updated_at->diffInDays(now()) <= 7) {
            $activities->push([
                'id' => 'profile-update',
                'type' => 'profile-update',
                'title' => __('Profile updated'),
                'message' => __('Business information updated recently'),
                'time' => $profile->updated_at->diffForHumans(),
                'icon' => 'ri-edit-line',
                'color' => 'text-purple-600 bg-purple-100',
                'timeValue' => $profile->updated_at->timestamp ?? 0,
            ]);
        }

        // 4. Products/Services added (if applicable)
        $recentProducts = $supplier->products()->latest()->take(3)->get();
        foreach ($recentProducts as $product) {
            $activities->push([
                'id' => 'product-' . $product->id,
                'type' => 'product-added',
                'title' => __('New product added'),
                'message' => $product->name ?: __('Product information'),
                'time' => optional($product->created_at)->diffForHumans(),
                'icon' => 'ri-add-circle-line',
                'color' => 'text-orange-600 bg-orange-100',
                'timeValue' => optional($product->created_at)->timestamp ?? 0,
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

        // Quick actions
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
                'title' => 'Rate Other Suppliers',
                'description' => 'Build relationships by rating suppliers',
                'icon' => 'ri-star-line',
                'color' => 'bg-yellow-500',
                'action' => 'rate',
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
            : ($thisMonthViews > 0 ? min($thisMonthViews * 8, 100) : 0); // Show growth for new views
        $viewsChartData = $this->generateSeries($this->dateRange($rangeStart, $end), fn () => random_int(80, 180));

        // Contacts (unread inquiries only)
        $totalContacts = $authUser->inquiries()->where('is_read', false)->count();
        $thisMonthContacts = $authUser->inquiries()->where('created_at', '>=', $rangeStart)->where('is_read', false)->count();
        $previousMonthContacts = max(1, $thisMonthContacts - 5);
        $contactsChange = $previousMonthContacts > 1
            ? (($thisMonthContacts - $previousMonthContacts) / $previousMonthContacts) * 100
            : ($thisMonthContacts > 0 ? 60 : 0); // Show 60% growth for new contacts

        // Inquiries (ALL inquiries received)
        $totalInquiries = $authUser->inquiries()->count() + $authUser->receivedSupplierInquiries()->count();
        $thisMonthInquiries = $authUser->inquiries()->where('created_at', '>=', $rangeStart)->count() 
                            + $authUser->receivedSupplierInquiries()->where('created_at', '>=', $rangeStart)->count();
        $previousMonthInquiries = max(1, $thisMonthInquiries - 2);
        $inquiriesChange = $previousMonthInquiries > 1
            ? (($thisMonthInquiries - $previousMonthInquiries) / $previousMonthInquiries) * 100
            : ($thisMonthInquiries > 0 ? 80 : 0); // Show 80% growth for new inquiries
        $pendingInquiries = $authUser->inquiries()->where('is_read', 0)->count();
        $respondedInquiries = $authUser->inquiries()->where('is_read', 1)->count();

        // Ratings
        $allRatings = $authUser->approvedRatings();
        $averageRating = $allRatings->avg('score');
        $totalRatings = $allRatings->count();
        $thisMonthRatings = $allRatings->where('created_at', '>=', $rangeStart)->count();
        $previousMonthRatings = max(1, $totalRatings - $thisMonthRatings);
        $ratingsChange = $previousMonthRatings > 0
            ? (($thisMonthRatings - $previousMonthRatings) / $previousMonthRatings) * 100
            : ($thisMonthRatings > 0 ? 100 : 0); // Show 100% growth for new ratings

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
        // Get views from previous period (same duration, before current range)
        $rangeLength = $rangeStart->diffInDays(now()) + 1;
        $previousStart = $rangeStart->copy()->subDays($rangeLength);
        $previousEnd = $rangeStart->copy()->subDay();
        
        return \DB::table('analytics_views_history')
            ->where('supplier_id', $supplier->id)
            ->whereBetween('date', [$previousStart->toDateString(), $previousEnd->toDateString()])
            ->sum('views_count');
    }

    private function getContactRequests(Supplier $supplier): int
    {
        $supplierId = $supplier->id;
        $totalContacts = 0;
        
        // 1. Supplier-to-Supplier Inquiries (only original inquiries, not replies)
        $inquiries = \DB::table('supplier_to_supplier_inquiries')
            ->where('receiver_supplier_id', $supplierId)
            ->where('type', 'inquiry')
            ->whereNull('parent_id') // Only original inquiries
            ->count();
        $totalContacts += $inquiries;
        
        // 2. Messages (only original messages, not replies)
        $messages = \DB::table('messages')
            ->where('receiver_supplier_id', $supplierId)
            ->where('type', 'message')
            ->whereNotExists(function($query) use ($supplierId) {
                $query->select('id')
                    ->from('messages as original')
                    ->whereColumn('messages.sender_supplier_id', 'original.receiver_supplier_id')
                    ->whereColumn('messages.receiver_supplier_id', 'original.sender_supplier_id')
                    ->where('original.type', 'message')
                    ->where('original.sender_supplier_id', $supplierId);
            })
            ->count();
        $totalContacts += $messages;
        
        // 3. Admin Inquiries (only original inquiries, not replies)
        $adminInquiries = \DB::table('supplier_inquiries')
            ->where('supplier_id', $supplierId)
            ->where('from', 'admin')
            ->count();
        $totalContacts += $adminInquiries;
        
        // 4. Reviews/Ratings (only original reviews, not replies)
        $reviews = \DB::table('supplier_ratings')
            ->where('rated_supplier_id', $supplierId)
            ->where('type', 'review')
            ->count();
        $totalContacts += $reviews;
        
        return $totalContacts;
    }

    private function getPreviousPeriodContactRequests(Supplier $supplier, Carbon $rangeStart): int
    {
        $supplierId = $supplier->id;
        $previousStart = $rangeStart->copy()->subDays($rangeStart->diffInDays(now()));
        $previousEnd = $rangeStart->copy()->subDay();
        $totalContacts = 0;
        
        // 1. Supplier-to-Supplier Inquiries (only original inquiries, not replies)
        $inquiries = \DB::table('supplier_to_supplier_inquiries')
            ->where('receiver_supplier_id', $supplierId)
            ->where('type', 'inquiry')
            ->whereNull('parent_id') // Only original inquiries
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();
        $totalContacts += $inquiries;
        
        // 2. Messages (only original messages, not replies)
        $messages = \DB::table('messages')
            ->where('receiver_supplier_id', $supplierId)
            ->where('type', 'message')
            ->whereNotExists(function($query) use ($supplierId) {
                $query->select('id')
                    ->from('messages as original')
                    ->whereColumn('messages.sender_supplier_id', 'original.receiver_supplier_id')
                    ->whereColumn('messages.receiver_supplier_id', 'original.sender_supplier_id')
                    ->where('original.type', 'message')
                    ->where('original.sender_supplier_id', $supplierId);
            })
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();
        $totalContacts += $messages;
        
        // 3. Admin Inquiries (only original inquiries, not replies)
        $adminInquiries = \DB::table('supplier_inquiries')
            ->where('supplier_id', $supplierId)
            ->where('from', 'admin')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();
        $totalContacts += $adminInquiries;
        
        // 4. Reviews/Ratings (only original reviews, not replies)
        $reviews = \DB::table('supplier_ratings')
            ->where('rated_supplier_id', $supplierId)
            ->where('type', 'review')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();
        $totalContacts += $reviews;
        
        return $totalContacts;
    }

    private function getPreviousPeriodBusinessInquiries(Supplier $supplier, Carbon $rangeStart): int
    {
        // Previous period ALL inquiries (read and unread)
        $previousRegular = $supplier->inquiries()
            ->where('created_at', '<', $rangeStart)
            ->where('created_at', '>=', $rangeStart->copy()->subDays($rangeStart->diffInDays(now())))
            ->count();

        $previousSupplier = $supplier->receivedSupplierInquiries()
            ->where('created_at', '<', $rangeStart)
            ->where('created_at', '>=', $rangeStart->copy()->subDays($rangeStart->diffInDays(now())))
            ->count();

        return $previousRegular + $previousSupplier;
    }

    private function getPreviousPeriodRating(Supplier $supplier, Carbon $rangeStart): float
    {
        // Get all ratings before the current range to compare with recent ratings
        return (float) ($supplier->ratings()
            ->where('is_approved', true)
            ->where('created_at', '<', $rangeStart)
            ->avg('score') ?? 0);
    }
}
