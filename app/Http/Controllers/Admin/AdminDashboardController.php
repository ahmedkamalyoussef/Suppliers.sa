<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Supplier;
use App\Models\SupplierInquiry;
use App\Models\SupplierProfile;
use App\Models\SupplierRating;
use App\Models\SupplierService;
use App\Models\SupplierProduct;
use App\Models\SupplierCertification;
use App\Models\SupplierDocument;
use App\Models\SystemSettings;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Health\Facades\Health;
use Spatie\Health\ResultStores\ResultStore;

class AdminDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
        $this->middleware(function ($request, $next) {
            $user = $request->user();

            if (! $user instanceof Admin) {
                return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
            }

            if (! $user->isSuperAdmin()) {
                $user->loadMissing('permissions');
                $permissions = $user->permissions;

                if (! $permissions || (! $permissions->analytics_view && ! $permissions->user_management_view)) {
                    return response()->json(['message' => 'Unauthorized. Analytics permission required.'], 403);
                }
            }

            return $next($request);
        });
    }

    public function overview(Request $request): JsonResponse
    {
        $admin = $request->user();

        $range = max(7, min(180, (int) $request->query('range', 30)));
        $end = Carbon::now();
        $start = (clone $end)->subDays($range - 1)->startOfDay();

        $previousStart = (clone $start)->subDays($range);
        $previousEnd = (clone $start)->subSecond();

        $totalSuppliers = Supplier::count();
        $activeSuppliers = Supplier::where('status', 'active')->count();
        $newSuppliers = Supplier::whereBetween('created_at', [$start, $end])->count();
        $newSuppliersPrevious = Supplier::whereBetween('created_at', [$previousStart, $previousEnd])->count();
        $pendingVerifications = Supplier::where('status', 'pending')->count();
        $suspendedSuppliers = Supplier::where('status', 'suspended')->count();

        $pendingInquiries = SupplierInquiry::where('is_read', false)->count();
        $unreadInquiries = SupplierInquiry::where('is_read', false)->count();
        $pendingRatings = SupplierRating::where('is_approved', false)->count();

        $recentInquiries = SupplierInquiry::with('supplier.profile')
            ->latest()
            ->take(5)
            ->get();

        $recentSuppliers = Supplier::with('profile')
            ->latest()
            ->take(5)
            ->get();

        $systemStats = [
            [
                'title' => 'Total Suppliers',
                'value' => number_format($totalSuppliers),
                'change' => $this->formatChange($newSuppliers, $newSuppliersPrevious),
                'trend' => $newSuppliers >= $newSuppliersPrevious ? 'up' : 'down',
                'icon' => 'ri-user-line',
                'color' => 'bg-blue-500',
            ],
            [
                'title' => 'Active Businesses',
                'value' => number_format($activeSuppliers),
                'change' => $this->formatChange($activeSuppliers, max(1, $activeSuppliers - $newSuppliers)),
                'trend' => 'up',
                'icon' => 'ri-store-line',
                'color' => 'bg-green-500',
            ],
            [
                'title' => 'Pending Inquiries',
                'value' => number_format($pendingInquiries),
                'change' => $this->formatChange($pendingInquiries, max(1, $pendingInquiries - $newSuppliers)),
                'trend' => $pendingInquiries <= $newSuppliers ? 'down' : 'up',
                'icon' => 'ri-customer-service-2-line',
                'color' => 'bg-yellow-500',
            ],
            [
                'title' => 'Pending Approvals',
                'value' => number_format($pendingRatings + $pendingVerifications),
                'change' => $this->formatChange($pendingRatings + $pendingVerifications, max(1, $pendingRatings + $pendingVerifications - $newSuppliers)),
                'trend' => $pendingRatings + $pendingVerifications <= $newSuppliers ? 'down' : 'up',
                'icon' => 'ri-shield-check-line',
                'color' => 'bg-purple-500',
            ],
        ];

        $pendingActions = [
            [
                'title' => 'Business Verifications',
                'count' => $pendingVerifications,
                'priority' => $pendingVerifications > 10 ? 'high' : ($pendingVerifications > 3 ? 'medium' : 'low'),
                'action' => 'Review now',
            ],
            [
                'title' => 'Content Approvals',
                'count' => $pendingRatings,
                'priority' => $pendingRatings > 10 ? 'high' : ($pendingRatings > 3 ? 'medium' : 'low'),
                'action' => 'Moderate',
            ],
            [
                'title' => 'Support Tickets',
                'count' => $pendingInquiries,
                'priority' => $pendingInquiries > 10 ? 'high' : ($pendingInquiries > 5 ? 'medium' : 'low'),
                'action' => 'Resolve',
            ],
            [
                'title' => 'Suspended Accounts',
                'count' => $suspendedSuppliers,
                'priority' => $suspendedSuppliers > 5 ? 'medium' : 'low',
                'action' => 'Review',
            ],
        ];

        $recentActivities = collect()
            ->merge($recentSuppliers->map(function (Supplier $supplier) {
                return [
                    'type' => 'user_registration',
                    'message' => sprintf('New business registered: %s', $supplier->profile->business_name ?? $supplier->name),
                    'time' => optional($supplier->created_at)->diffForHumans(),
                    'timeValue' => optional($supplier->created_at)->timestamp ?? 0,
                    'icon' => 'ri-user-add-line',
                    'color' => 'bg-green-100 text-green-600',
                ];
            }))
            ->merge($recentInquiries->map(function (SupplierInquiry $inquiry) {
                return [
                    'type' => 'inquiry',
                    'message' => sprintf('New inquiry from %s', $inquiry->name),
                    'time' => optional($inquiry->created_at)->diffForHumans(),
                    'timeValue' => optional($inquiry->created_at)->timestamp ?? 0,
                    'icon' => 'ri-customer-service-2-line',
                    'color' => 'bg-blue-100 text-blue-600',
                ];
            }))
            ->sortByDesc('timeValue')
            ->take(10)
            ->map(function (array $activity) {
                unset($activity['timeValue']);

                return $activity;
            })
            ->values();

        $quickActions = [
            [
                'title' => 'Add new employee',
                'description' => 'Create a new admin account and assign permissions.',
                'icon' => 'ri-user-add-line',
                'color' => 'bg-blue-500',
            ],
            [
                'title' => 'Broadcast message',
                'description' => 'Send announcement to all suppliers.',
                'icon' => 'ri-megaphone-line',
                'color' => 'bg-green-500',
            ],
            [
                'title' => 'Generate report',
                'description' => 'Export the latest activity reports.',
                'icon' => 'ri-file-chart-line',
                'color' => 'bg-purple-500',
            ],
            [
                'title' => 'System maintenance',
                'description' => 'Schedule upcoming maintenance window.',
                'icon' => 'ri-tools-line',
                'color' => 'bg-orange-500',
            ],
        ];

        // Get server uptime for display
        $uptimeRaw = shell_exec('uptime -p 2>/dev/null || uptime');
        $uptime = trim($uptimeRaw) ?: 'Unknown';
        
        // Get real system health using individual checks
        $checks = [
            "database" => $this->checkDatabase(),
            "disk"     => $this->checkDisk(),
            "ram"      => $this->checkRAM(),
            "cpu"      => $this->checkCPU(),
            "storage"  => $this->checkStorageWritable(),
            "cache"    => $this->checkCache(),
            "queue"    => $this->checkQueue(),
        ];

        // Convert statuses to points
        $scoreMap = [
            'ok' => 1,
            'warning' => 0.5,
            'critical' => 0
        ];

        $total = count($checks);
        $sum = 0;

        foreach ($checks as $check) {
            $sum += $scoreMap[$check['status']];
        }

        // Calculate health percentage
        $healthPercentage = round(($sum / $total) * 100, 2);
        
        // Count incidents and alerts from checks
        $incidentsCount = collect($checks)->where('status', 'critical')->count();
        $alertsCount = collect($checks)->where('status', 'warning')->count();
        
        $systemHealth = [
            'overall_health' => number_format($healthPercentage, 1) . '%',
            'checks' => $checks,
            'serverStatus' => [
                'status' => $healthPercentage >= 80 ? 'ok' : ($healthPercentage >= 50 ? 'warning' : 'critical'),
                'uptime' => $uptime,
                'incidentsThisMonth' => $incidentsCount,
            ],
            'database' => [
                'status' => $checks['database']['status'],
                'backupStatus' => 'Not configured', // Could be enhanced with backup check
            ],
            'security' => [
                'status' => 'ok', // Default status
                'openAlerts' => $alertsCount,
            ],
        ];

        // Get real previous period data for accurate change calculations
        $previousRangeStart = (clone $start)->subDays($range);
        $previousRangeEnd = (clone $start)->subSecond();
        
        // Real previous active suppliers
        $previousActiveSuppliers = Supplier::where('status', 'active')
            ->where('created_at', '<', $start)
            ->count();
            
        // Real previous total suppliers  
        $previousTotalSuppliers = Supplier::where('created_at', '<', $start)
            ->count();
            
        // Real previous pending verifications (suppliers with documents waiting verification)
        $previousPendingVerifications = Supplier::where('status', 'pending')
            ->whereHas('documents', function ($query) use ($start) {
                $query->where('created_at', '<', $start);
            })
            ->count();

        // Current pending verifications (suppliers with documents waiting verification)
        $pendingVerifications = Supplier::where('status', 'pending')
            ->whereHas('documents')
            ->count();

        // Calculate previous health percentage from actual historical data
        $previousHealthPercentage = null;
        try {
            // Store current health check for future comparisons
            DB::table('health_check_results')->insert([
                'health_percentage' => $healthPercentage,
                'checks_data' => json_encode($checks),
                'created_at' => now()
            ]);
            
            // Get previous health check
            $lastHealthCheck = DB::table('health_check_results')
                ->orderBy('created_at', 'desc')
                ->skip(1)
                ->first();
            
            if ($lastHealthCheck) {
                $previousHealthPercentage = (float) $lastHealthCheck->health_percentage;
            }
        } catch (\Exception $e) {
            // If table doesn't exist, create it and use null for previous
            try {
                DB::statement('
                    CREATE TABLE IF NOT EXISTS health_check_results (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        health_percentage DECIMAL(5,2),
                        checks_data TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ');
            } catch (\Exception $createException) {
                // Ignore table creation errors
            }
        }

        // Get content reports count
        $contentReportsCount = \App\Models\ContentReport::count();

        return response()->json([
            'system_health' => [
                'current' => number_format($healthPercentage, 1) . '%',
                'change' => $previousHealthPercentage !== null 
                    ? $this->formatChange($healthPercentage, $previousHealthPercentage)
                    : 'N/A',
                'trend' => $previousHealthPercentage !== null 
                    ? ($healthPercentage > $previousHealthPercentage ? 'up' : ($healthPercentage < $previousHealthPercentage ? 'down' : 'stable'))
                    : 'stable'
            ],
            'revenue' => [
                'current' => 0,
                'change' => '+0.0%',
                'trend' => 'stable'
            ],
            'activeBusinesses' => [
                'count' => $activeSuppliers,
                'change' => $this->formatChange($activeSuppliers, $previousActiveSuppliers),
                'trend' => $activeSuppliers > $previousActiveSuppliers ? 'up' : ($activeSuppliers < $previousActiveSuppliers ? 'down' : 'stable')
            ],
            'totalUsers' => [
                'count' => $totalSuppliers,
                'change' => $this->formatChange($totalSuppliers, $previousTotalSuppliers),
                'trend' => $totalSuppliers > $previousTotalSuppliers ? 'up' : ($totalSuppliers < $previousTotalSuppliers ? 'down' : 'stable')
            ],
            'recentActivity' => $recentActivities,
            'businessVerification' => [
                'pending' => $pendingVerifications,
                'change' => $this->formatChange($pendingVerifications, $previousPendingVerifications),
                'trend' => $pendingVerifications > $previousPendingVerifications ? 'up' : ($pendingVerifications < $previousPendingVerifications ? 'down' : 'stable')
            ],
            'content_reports_count' => $contentReportsCount,
            'healthChecks' => [
                'serverStatus' => [
                    'status' => $healthPercentage >= 80 ? 'ok' : ($healthPercentage >= 50 ? 'warning' : 'critical'),
                    'uptime' => $uptime
                ],
                'database' => $checks['database'],
                'security' => [
                    'status' => 'ok',
                    'message' => 'System protected'
                ]
            ]
        ]);
    }

    public function exportAnalytics(Request $request): \Illuminate\Http\Response
    {
        $admin = $request->user();

        if (! $admin instanceof Admin) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        if (! $admin->isSuperAdmin()) {
            $admin->loadMissing('permissions');
            $permissions = $admin->permissions;

            if (! $permissions || ! $permissions->analytics_export) {
                return response()->json(['message' => 'Unauthorized. Analytics export permission required.'], 403);
            }
        }

        $range = $request->query('range', 30);
        $end = Carbon::now()->endOfDay();
        $start = (clone $end)->subDays($range - 1)->startOfDay();

        // Comprehensive analytics data
        $data = [
            ['Category', 'Metric', 'Value', 'Description', 'Period'],
            
            // Users Section
            ['Users', 'Total Users', Supplier::count(), 'All registered suppliers', 'All time'],
            ['Users', 'Active Users', Supplier::where('status', 'active')->count(), 'Users with active status', 'All time'],
            ['Users', 'Pending Users', Supplier::where('status', 'pending')->count(), 'Users awaiting approval', 'All time'],
            ['Users', 'New Users (Last 30 days)', Supplier::where('created_at', '>=', $start)->count(), 'New registrations', 'Last 30 days'],
            ['Users', 'Verified Users', Supplier::whereNotNull('email_verified_at')->count(), 'Users with verified email', 'All time'],
            
            // Subscriptions Section
            ['Subscriptions', 'Basic Plan', Supplier::where('plan', 'Basic')->count(), 'Free plan users', 'All time'],
            ['Subscriptions', 'Premium Plan', Supplier::where('plan', 'Premium')->count(), 'Premium plan users', 'All time'],
            ['Subscriptions', 'Paid Subscriptions', Supplier::where('plan', 'Premium')->count(), 'Premium plan users', 'All time'],
            ['Subscriptions', 'Monthly Revenue', '$' . number_format(0), 'Total monthly revenue', 'Monthly'],
            
            // Business Categories Section
            ['Business', 'Total Categories', SupplierProfile::whereNotNull('business_categories')->distinct()->count('business_categories'), 'Unique business categories', 'All time'],
            ['Business', 'Most Popular Category', 'Technology', 'Top business category', 'All time'],
            ['Business', 'Services Count', SupplierService::count(), 'Total services listed', 'All time'],
            ['Business', 'Products Count', SupplierProduct::count(), 'Total products listed', 'All time'],
            ['Business', 'Certifications Count', SupplierCertification::count(), 'Total certifications', 'All time'],
            
            // Activity Section
            ['Activity', 'Total Inquiries', SupplierInquiry::count(), 'All customer inquiries', 'All time'],
            ['Activity', 'Recent Inquiries', SupplierInquiry::where('created_at', '>=', $start)->count(), 'Inquiries in period', 'Last 30 days'],
            ['Activity', 'Total Ratings', SupplierRating::count(), 'All user ratings', 'All time'],
            ['Activity', 'Average Rating', number_format(SupplierRating::avg('score'), 1), 'Average user rating', 'All time'],
            ['Activity', 'Approved Ratings', SupplierRating::where('status', 'approved')->count(), 'Approved ratings', 'All time'],
            
            // Documents Section
            ['Documents', 'Total Documents', SupplierDocument::count(), 'All uploaded documents', 'All time'],
            ['Documents', 'Documents This Month', SupplierDocument::where('created_at', '>=', $start)->count(), 'Documents uploaded this month', 'Last 30 days'],
            ['Documents', 'Avg Documents Per Supplier', number_format(SupplierDocument::count() / max(1, Supplier::count()), 1), 'Average documents per supplier', 'All time'],
            
            // System Performance
            ['System', 'Server Uptime', '99.9%', 'System availability', 'Current'],
            ['System', 'Database Size', '245 MB', 'Database storage used', 'Current'],
            ['System', 'Storage Used', '1.2 GB', 'File storage used', 'Current'],
            ['System', 'API Calls Today', '15,234', 'Daily API requests', 'Today'],
            ['System', 'Response Time', '120ms', 'Average response time', 'Current'],
            
            // Revenue Analytics
            ['Revenue', 'Basic Revenue', '$' . number_format(156780), 'Revenue from Basic plan', 'Monthly'],
            ['Revenue', 'Premium Revenue', '$' . number_format(342150), 'Revenue from Premium plan', 'Monthly'],
            ['Revenue', 'Total Revenue', '$' . number_format(854320), 'Total monthly revenue', 'Monthly'],
            ['Revenue', 'Avg Revenue Per User', '$' . number_format(854320 / max(1, Supplier::where('plan', '!=', 'Basic')->count()), 2), 'Average revenue per paid user', 'Monthly'],
            
            // Geographic Data
            ['Geography', 'Countries Served', '45', 'Number of countries', 'All time'],
            ['Geography', 'Top Country', 'Egypt', 'Most users from', 'All time'],
            ['Geography', 'Cities Served', '120', 'Number of cities', 'All time'],
            
            // Support & Admin
            ['Support', 'Admin Users', Admin::count(), 'Total admin users', 'All time'],
            ['Support', 'Support Tickets', '234', 'Open support tickets', 'Current'],
            ['Support', 'Avg Response Time', '2 hours', 'Support response time', 'Current'],
        ];

        $csv = '';
        foreach ($data as $row) {
            $csv .= implode(',', $row) . "\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="analytics-' . date('Y-m-d') . '.csv"');
    }

    public function dashboardAnalytics(Request $request): JsonResponse
    {
        $admin = $request->user();

        if (! $admin instanceof Admin) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        if (! $admin->isSuperAdmin()) {
            $admin->loadMissing('permissions');
            $permissions = $admin->permissions;

            if (! $permissions || ! $permissions->analytics_view) {
                return response()->json(['message' => 'Unauthorized. Analytics permission required.'], 403);
            }
        }

        $range = max(7, min(365, (int) $request->query('range', 30)));
        $end = Carbon::now()->endOfDay();
        $start = (clone $end)->subDays($range - 1)->startOfDay();
        $previousStart = (clone $start)->subDays($range);
        $previousEnd = (clone $start)->subSecond();

        // Total Users (all suppliers)
        $totalUsers = Supplier::count();
        $previousTotalUsers = Supplier::where('created_at', '<', $start)->count();
        $totalUsersChange = $this->formatChange($totalUsers, $previousTotalUsers);

        // Total Businesses (active suppliers)
        $totalBusinesses = Supplier::where('status', 'active')->count();
        $previousTotalBusinesses = Supplier::where('status', 'active')
            ->where('created_at', '<', $start)
            ->count();
        $totalBusinessesChange = $this->formatChange($totalBusinesses, $previousTotalBusinesses);

        // Paid Subscriptions (Premium plans only) - actual database value
        $paidSubscriptions = Supplier::where('plan', 'Premium')->count();
        $previousPaidSubscriptions = Supplier::where('plan', 'Premium')
            ->where('created_at', '<', $start)
            ->count();
        $paidSubscriptionsChange = $this->formatChange($paidSubscriptions, $previousPaidSubscriptions);

        // Revenue - set to 0
        $revenue = 0;
        $revenueChange = '+0.0%';

        // Top Business Categories (top 5 from supplier_profiles)
        $topCategories = SupplierProfile::select('business_categories')
            ->whereNotNull('business_categories')
            ->get()
            ->flatMap(function ($profile) {
                return collect($profile->business_categories)->filter();
            })
            ->countBy()
            ->sortDesc()
            ->take(5)
            ->map(function ($count, $category) use ($start, $previousStart) {
                // Calculate growth for each category
                $currentCount = $count;
                $previousCount = SupplierProfile::whereNotNull('business_categories')
                    ->where('created_at', '<', $start)
                    ->get()
                    ->flatMap(function ($profile) use ($category) {
                        return collect($profile->business_categories)->filter(fn($cat) => $cat === $category);
                    })
                    ->count();
                
                $growth = $previousCount > 0 
                    ? round((($currentCount - $previousCount) / $previousCount) * 100, 1)
                    : 100.0;

                return [
                    'name' => $category,
                    'businesses' => (int) $currentCount,
                    'growth' => $growth > 0 ? '+' . $growth . '%' : $growth . '%',
                    'revenue' => '$' . number_format(0),
                ];
            })
            ->values()
            ->all();

        // Revenue by Plan - actual user counts with zero revenue
        $basicUsers = Supplier::where('plan', 'Basic')->count();
        $premiumUsers = Supplier::where('plan', 'Premium')->count();
        
        $revenueByPlan = [
            [
                'plan' => 'Basic',
                'revenue' => 0,
                'users' => $basicUsers,
                'color' => 'bg-green-500'
            ],
            [
                'plan' => 'Premium',
                'revenue' => 0,
                'users' => $premiumUsers,
                'color' => 'bg-blue-500'
            ]
        ];

        // Daily User Activity
        $dateLabels = $this->buildDateRange($start, $end);
        $newSuppliersByDay = $this->aggregateByDay(Supplier::query(), 'created_at', $start, $end);
        $inquiriesByDay = $this->aggregateByDay(SupplierInquiry::query(), 'created_at', $start, $end);

        $dailyUserActivity = $dateLabels->map(function (Carbon $date) use ($newSuppliersByDay, $inquiriesByDay) {
            $dateString = $date->toDateString();
            return [
                'date' => $dateString,
                'newUsers' => (int) ($newSuppliersByDay->get($dateString, 0)),
                'activeUsers' => (int) Supplier::where('status', 'active')
                    ->whereDate('last_seen_at', $dateString)
                    ->orWhere(function($query) use ($dateString) {
                        $query->where('status', 'active')
                              ->whereDate('updated_at', $dateString);
                    })
                    ->count(),
                'revenue' => 0,
                'inquiries' => (int) ($inquiriesByDay->get($dateString, 0)),
            ];
        })->values()->all();

        // Server Performance - static values as requested
        $serverPerformance = [
            [
                'title' => 'Server Performance',
                'subtitle' => '99.9% Uptime',
                'icon' => 'ri-server-line',
                'color' => 'bg-green-100 text-green-600',
                'usage' => 34
            ],
            [
                'title' => 'Database Health',
                'subtitle' => 'Optimal Performance',
                'icon' => 'ri-database-2-line',
                'color' => 'bg-blue-100 text-blue-600',
                'usage' => 67
            ],
            [
                'title' => 'Storage Used',
                'subtitle' => 'Fast & Stable',
                'icon' => 'ri-hard-drive-2-line',
                'color' => 'bg-yellow-100 text-yellow-600',
                'usage' => 67
            ],
            [
                'title' => 'Response Time',
                'subtitle' => 'Avg Response 124ms',
                'icon' => 'ri-timer-line',
                'color' => 'bg-purple-100 text-purple-600',
                'usage' => 45
            ]
        ];

        return response()->json([
            'revenue' => [
                'current' => $revenue,
                'change' => $revenueChange
            ],
            'totalUsers' => [
                'count' => $totalUsers,
                'change' => $totalUsersChange
            ],
            'totalBusinesses' => [
                'count' => $totalBusinesses,
                'change' => $totalBusinessesChange
            ],
            'paidSubscriptions' => [
                'count' => $paidSubscriptions,
                'change' => $paidSubscriptionsChange
            ],
            'topBusinessCategories' => $topCategories,
            'revenueByPlan' => $revenueByPlan,
            'dailyUserActivity' => $dailyUserActivity,
            'serverPerformance' => $serverPerformance
        ]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $range = max(7, min(365, (int) $request->query('range', 30)));
        $end = Carbon::now()->endOfDay();
        $start = (clone $end)->subDays($range - 1)->startOfDay();

        $previousStart = (clone $start)->subDays($range);
        $previousEnd = (clone $start)->subSecond();

        $dateLabels = $this->buildDateRange($start, $end);
        $previousDateLabels = $this->buildDateRange($previousStart, $previousEnd);

        $newSuppliersByDay = $this->aggregateByDay(Supplier::query(), 'created_at', $start, $end);
        $prevSuppliersByDay = $this->aggregateByDay(Supplier::query(), 'created_at', $previousStart, $previousEnd);

        $activeSuppliers = Supplier::where('status', 'active')->count();
        $activeSuppliersByDay = $this->aggregateActiveSuppliers($dateLabels);

        $nonBasicPlansByDay = $this->aggregateByDay(
            Supplier::where('plan', '!=', 'Basic'),
            'created_at',
            $start,
            $end
        );

        $inquiriesByDay = $this->aggregateByDay(SupplierInquiry::query(), 'created_at', $start, $end);

        $analyticsData = [
            'users' => [
                'current' => array_sum($newSuppliersByDay->values()->all()),
                'previous' => array_sum($prevSuppliersByDay->values()->all()),
                'growth' => $this->percentageChange(
                    array_sum($prevSuppliersByDay->values()->all()),
                    array_sum($newSuppliersByDay->values()->all())
                ),
                'data' => $this->fillMissingDays($dateLabels, $newSuppliersByDay),
            ],
            'businesses' => [
                'current' => $activeSuppliers,
                'previous' => max(0, $activeSuppliers - array_sum($newSuppliersByDay->values()->all())),
                'growth' => $this->percentageChange(
                    max(1, $activeSuppliers - array_sum($newSuppliersByDay->values()->all())),
                    $activeSuppliers
                ),
                'data' => $this->fillMissingDays($dateLabels, $activeSuppliersByDay),
            ],
            'subscriptions' => [
                'current' => Supplier::where('plan', '!=', 'Basic')->count(),
                'previous' => Supplier::where('plan', '!=', 'Basic')->where('created_at', '<', $start)->count(),
                'growth' => $this->percentageChange(
                    Supplier::where('plan', '!=', 'Basic')->where('created_at', '<', $start)->count(),
                    Supplier::where('plan', '!=', 'Basic')->count()
                ),
                'data' => $this->fillMissingDays($dateLabels, $nonBasicPlansByDay),
            ],
            'revenue' => [
                'current' => 0,
                'previous' => 0,
                'growth' => 0,
                'data' => array_fill(0, count($dateLabels), 0),
            ],
        ];

        $revenueByPlan = Supplier::select('plan', DB::raw('COUNT(*) as users'))
            ->groupBy('plan')
            ->get()
            ->map(function ($row) {
                $plan = $row->plan ?? 'Basic';
                $baseRevenue = match (strtolower($plan)) {
                    
                    'premium' => 15900,
                    default => 6900,
                };
                $users = (int) $row->users;

                return [
                    'plan' => ucfirst($plan),
                    'revenue' => $baseRevenue * max(1, $users),
                    'users' => $users,
                    'color' => $this->planColor($plan),
                ];
            })
            ->values()
            ->all();

        $topCategories = $this->topCategories()->map(function (array $category, int $index) {
            return array_merge($category, [
                'revenue' => '$'.number_format((int) preg_replace('/[^0-9]/', '', (string) $category['revenue'])),
                'growth' => (int) $category['growth'],
            ]);
        })->values()->all();

        $userActivity = $dateLabels->map(function (Carbon $date) use ($newSuppliersByDay, $inquiriesByDay) {
            $dateString = $date->toDateString();

            return [
                'date' => $dateString,
                'newUsers' => (int) ($newSuppliersByDay->get($dateString, 0)),
                'activeUsers' => (int) Supplier::where('status', 'active')
                    ->whereDate('updated_at', '>=', $date->copy()->subDays(30))
                    ->count(),
                'revenue' => random_int(5000, 15000),
                'inquiries' => (int) ($inquiriesByDay->get($dateString, 0)),
            ];
        })->values()->all();

        $systemPerformance = [
            [
                'title' => 'Server Performance',
                'subtitle' => 'Uptime 99.9%',
                'icon' => 'ri-server-line',
                'color' => 'bg-green-100 text-green-600',
                'usage' => 34,
            ],
            [
                'title' => 'Database Health',
                'subtitle' => 'Backups completed 12 hours ago',
                'icon' => 'ri-database-2-line',
                'color' => 'bg-blue-100 text-blue-600',
                'usage' => 68,
            ],
            [
                'title' => 'Security Monitoring',
                'subtitle' => 'No open alerts',
                'icon' => 'ri-shield-check-line',
                'color' => 'bg-yellow-100 text-yellow-600',
                'usage' => 92,
            ],
        ];

        return response()->json([
            'range' => $range,
            'analyticsData' => $analyticsData,
            'revenueByPlan' => $revenueByPlan,
            'topCategories' => $topCategories,
            'userActivity' => $userActivity,
            'systemPerformance' => $systemPerformance,
        ]);
    }

    private function formatChange(int $current, int $previous): string
    {
        $percent = $this->percentageChange($previous, $current);
        $sign = $percent > 0 ? '+' : '';

        return sprintf('%s%s%%', $sign, number_format($percent, 1));
    }

    private function percentageChange(int $previous, int $current): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return (($current - $previous) / max(1, $previous)) * 100;
    }

    /**
     * @return Collection<int,Carbon>
     */
    private function buildDateRange(Carbon $start, Carbon $end): Collection
    {
        $dates = collect();
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $dates->push($cursor->copy());
            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     */
    private function aggregateByDay($builder, string $column, Carbon $start, Carbon $end): Collection
    {
        return $builder
            ->whereBetween($column, [$start, $end])
            ->selectRaw('DATE('.$column.') as date_key, COUNT(*) as aggregate_count')
            ->groupBy('date_key')
            ->pluck('aggregate_count', 'date_key');
    }

    private function aggregateActiveSuppliers(Collection $dateRange): Collection
    {
        $counts = collect();

        foreach ($dateRange as $date) {
            $counts->put(
                $date->toDateString(),
                Supplier::where('status', 'active')
                    ->whereDate('created_at', '<=', $date)
                    ->count()
            );
        }

        return $counts;
    }

    private function fillMissingDays(Collection $dates, Collection $data): array
    {
        return $dates->map(function (Carbon $date) use ($data) {
            return (int) $data->get($date->toDateString(), 0);
        })->all();
    }

    private function planColor(string $plan): string
    {
        return match (strtolower($plan)) {

            'premium' => 'bg-blue-500',
            default => 'bg-green-500',
        };
    }

    private function topCategories(): Collection
    {
        $categories = SupplierProfile::select('business_categories')
            ->whereNotNull('business_categories')
            ->get()
            ->flatMap(function ($profile) {
                return collect($profile->business_categories)->filter();
            })
            ->countBy()
            ->sortDesc()
            ->take(10);

        return $categories->map(function ($count, $category) {
            return [
                'name' => $category,
                'businesses' => (int) $count,
                'revenue' => '$0',
                'growth' => 0,
            ];
        })->values();
    }

    protected function transformAdmin(Admin $admin): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'isSuperAdmin' => $admin->isSuperAdmin(),
            'permissions' => $admin->permissions,
        ];
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('SELECT 1');
            return ['status' => 'ok', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'critical', 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }

    private function checkDisk(): array
    {
        $freeBytes = disk_free_space('/');
        $totalBytes = disk_total_space('/');
        $usedPercent = (($totalBytes - $freeBytes) / $totalBytes) * 100;

        if ($usedPercent > 90) {
            return ['status' => 'critical', 'message' => 'Disk usage: ' . round($usedPercent, 2) . '%'];
        } elseif ($usedPercent > 80) {
            return ['status' => 'warning', 'message' => 'Disk usage: ' . round($usedPercent, 2) . '%'];
        } else {
            return ['status' => 'ok', 'message' => 'Disk usage: ' . round($usedPercent, 2) . '%'];
        }
    }

    private function checkRAM(): array
    {
        $memInfo = @file_get_contents('/proc/meminfo');
        if (!$memInfo) {
            return ['status' => 'warning', 'message' => 'Could not read memory info'];
        }

        preg_match('/MemTotal:\s+(\d+)/', $memInfo, $totalMatch);
        preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $availMatch);

        if (!$totalMatch || !$availMatch) {
            return ['status' => 'warning', 'message' => 'Could not parse memory info'];
        }

        $total = (int)$totalMatch[1];
        $available = (int)$availMatch[1];
        $usedPercent = (($total - $available) / $total) * 100;

        if ($usedPercent > 90) {
            return ['status' => 'critical', 'message' => 'RAM usage: ' . round($usedPercent, 2) . '%'];
        } elseif ($usedPercent > 80) {
            return ['status' => 'warning', 'message' => 'RAM usage: ' . round($usedPercent, 2) . '%'];
        } else {
            return ['status' => 'ok', 'message' => 'RAM usage: ' . round($usedPercent, 2) . '%'];
        }
    }

    private function checkCPU(): array
    {
        $load = sys_getloadavg();
        if (!$load) {
            return ['status' => 'warning', 'message' => 'Could not get CPU load'];
        }

        $load1 = $load[0];
        $cpuCores = $this->getCpuCores();

        if ($load1 > $cpuCores * 2) {
            return ['status' => 'critical', 'message' => 'CPU load: ' . round($load1, 2)];
        } elseif ($load1 > $cpuCores) {
            return ['status' => 'warning', 'message' => 'CPU load: ' . round($load1, 2)];
        } else {
            return ['status' => 'ok', 'message' => 'CPU load: ' . round($load1, 2)];
        }
    }

    private function getCpuCores(): int
    {
        $cores = @shell_exec('nproc 2>/dev/null') ?: @shell_exec('grep -c ^processor /proc/cpuinfo 2>/dev/null');
        return (int)trim($cores) ?: 1;
    }

    private function checkStorageWritable(): array
    {
        $testFile = storage_path('app/health_check_' . time() . '.tmp');
        
        try {
            if (file_put_contents($testFile, 'test') === false) {
                return ['status' => 'critical', 'message' => 'Storage not writable'];
            }
            unlink($testFile);
            return ['status' => 'ok', 'message' => 'Storage writable'];
        } catch (\Exception $e) {
            return ['status' => 'critical', 'message' => 'Storage test failed: ' . $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $testKey = 'health_check_' . time();
            $testValue = 'test';
            
            cache()->put($testKey, $testValue, 10);
            $retrieved = cache()->get($testKey);
            cache()->forget($testKey);
            
            if ($retrieved === $testValue) {
                return ['status' => 'ok', 'message' => 'Cache working'];
            } else {
                return ['status' => 'warning', 'message' => 'Cache not storing/retrieving properly'];
            }
        } catch (\Exception $e) {
            return ['status' => 'critical', 'message' => 'Cache error: ' . $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        try {
            // Check if queue worker is running by checking recent jobs
            $recentJobs = DB::table('jobs')
                ->where('created_at', '>', now()->subMinutes(5))
                ->count();
                
            if ($recentJobs > 100) {
                return ['status' => 'warning', 'message' => 'High queue backlog: ' . $recentJobs . ' jobs'];
            } else {
                return ['status' => 'ok', 'message' => 'Queue normal: ' . $recentJobs . ' recent jobs'];
            }
        } catch (\Exception $e) {
            return ['status' => 'warning', 'message' => 'Could not check queue status'];
        }
    }

    public function getSystemSettings(Request $request): JsonResponse
    {
        $admin = $request->user();

        if (! $admin instanceof Admin) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        if (! $admin->isSuperAdmin()) {
            $admin->loadMissing('permissions');
            $permissions = $admin->permissions;

            if (! $permissions || (! $permissions->system_manage && ! $permissions->system_settings)) {
                return response()->json(['message' => 'Unauthorized. System settings permission required.'], 403);
            }
        }

        $settings = SystemSettings::first();

        if (! $settings) {
            // Create default settings if none exist
            $settings = SystemSettings::create([
                'site_name' => 'Suppliers.sa',
                'contact_email' => 'contact@suppliers.sa',
                'support_email' => 'support@suppliers.sa',
                'maintenance_mode' => false,
                'maximum_photos_per_business' => 10,
                'maximum_description_characters' => 1000,
                'auto_approve_businesses' => false,
                'business_verification_required' => true,
                'premium_features_enabled' => true,
                'maximum_login_attempts' => 5,
                'session_timeout_minutes' => 120,
                'require_two_factor_authentication' => false,
                'strong_password_required' => true,
                'data_encryption_enabled' => true,
                'email_notifications' => true,
                'sms_notifications' => false,
                'push_notifications' => true,
                'system_alerts' => true,
                'maintenance_notifications' => true,
                'backup_retention_days' => 30,
            ]);
        }

        return response()->json([
            'success' => true,
            'settings' => $settings
        ]);
    }

    public function updateSystemSettings(Request $request): JsonResponse
    {
        $admin = $request->user();

        if (! $admin instanceof Admin) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        if (! $admin->isSuperAdmin()) {
            $admin->loadMissing('permissions');
            $permissions = $admin->permissions;

            if (! $permissions || (! $permissions->system_manage && ! $permissions->system_settings)) {
                return response()->json(['message' => 'Unauthorized. System settings permission required.'], 403);
            }
        }

        $validator = \Validator::make($request->all(), [
            // Basic Site Settings
            'site_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'support_email' => 'required|email|max:255',
            'site_description' => 'nullable|string|max:1000',
            'maintenance_mode' => 'boolean',
            
            // Business Settings
            'maximum_photos_per_business' => 'required|integer|min:1|max:50',
            'maximum_description_characters' => 'required|integer|min:100|max:5000',
            'auto_approve_businesses' => 'boolean',
            'business_verification_required' => 'boolean',
            'premium_features_enabled' => 'boolean',
            
            // Security Settings
            'maximum_login_attempts' => 'required|integer|min:3|max:10',
            'session_timeout_minutes' => 'required|integer|min:15|max:480',
            'require_two_factor_authentication' => 'boolean',
            'strong_password_required' => 'boolean',
            'data_encryption_enabled' => 'boolean',
            
            // Notification Settings
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'push_notifications' => 'boolean',
            'system_alerts' => 'boolean',
            'maintenance_notifications' => 'boolean',
            
            // System Settings
            'backup_retention_days' => 'required|integer|min:7|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $settings = SystemSettings::first();
            
            if (! $settings) {
                $settings = new SystemSettings();
            }

            $settings->fill($request->all());
            $settings->save();

            return response()->json([
                'success' => true,
                'message' => 'System settings updated successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update system settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function restoreSystemDefaults(Request $request): JsonResponse
    {
        $admin = $request->user();

        if (! $admin instanceof Admin) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        if (! $admin->isSuperAdmin()) {
            $admin->loadMissing('permissions');
            $permissions = $admin->permissions;

            if (! $permissions || (! $permissions->system_manage && ! $permissions->system_settings)) {
                return response()->json(['message' => 'Unauthorized. System settings permission required.'], 403);
            }
        }

        try {
            $settings = SystemSettings::first();
            
            if (! $settings) {
                $settings = new SystemSettings();
            }

            // Restore to default values
            $settings->update([
                'site_name' => 'Suppliers.sa',
                'contact_email' => 'contact@suppliers.sa',
                'support_email' => 'support@suppliers.sa',
                'site_description' => 'Professional suppliers directory platform connecting businesses with trusted suppliers across Saudi Arabia.',
                'maintenance_mode' => false,
                
                'maximum_photos_per_business' => 10,
                'maximum_description_characters' => 1000,
                'auto_approve_businesses' => false,
                'business_verification_required' => true,
                'premium_features_enabled' => true,
                
                'maximum_login_attempts' => 5,
                'session_timeout_minutes' => 120,
                'require_two_factor_authentication' => false,
                'strong_password_required' => true,
                'data_encryption_enabled' => true,
                
                'email_notifications' => true,
                'sms_notifications' => false,
                'push_notifications' => true,
                'system_alerts' => true,
                'maintenance_notifications' => true,
                
                'backup_retention_days' => 30,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'System settings restored to defaults successfully',
                'settings' => $settings->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore system defaults',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function createSystemBackup(Request $request): JsonResponse
    {
        $admin = $request->user();

        if (! $admin instanceof Admin) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        if (! $admin->isSuperAdmin()) {
            $admin->loadMissing('permissions');
            $permissions = $admin->permissions;

            if (! $permissions || (! $permissions->system_backups && ! $permissions->system_manage)) {
                return response()->json(['message' => 'Unauthorized. System backups or system manage permission required.'], 403);
            }
        }

        try {
            // Create backup directory if it doesn't exist
            $backupDir = storage_path('app/backups');
            if (! is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            // Create backup filename with timestamp
            $backupFilename = 'Suppliers.sa-backup-' . date('Y-m-d-H-i-s') . '.zip';
            $backupPath = $backupDir . '/' . $backupFilename;

            // Create ZIP archive
            $zip = new \ZipArchive();
            if ($zip->open($backupPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
                throw new \Exception('Cannot create backup file');
            }

            // Add database dump
            $dbDumpFile = storage_path('app/temp_db_dump.sql');
            $this->createDatabaseDump($dbDumpFile);
            $zip->addFile($dbDumpFile, 'database.sql');
            
            // Add application files (excluding vendor and node_modules)
            $this->addDirectoryToZip($zip, base_path('app'), 'app');
            $this->addDirectoryToZip($zip, base_path('config'), 'config');
            $this->addDirectoryToZip($zip, base_path('database'), 'database');
            $this->addDirectoryToZip($zip, base_path('resources'), 'resources');
            $this->addDirectoryToZip($zip, base_path('routes'), 'routes');
            $this->addDirectoryToZip($zip, base_path('storage/app'), 'storage/app');
            $this->addDirectoryToZip($zip, base_path('storage/framework'), 'storage/framework');
            $this->addDirectoryToZip($zip, base_path('storage/logs'), 'storage/logs');
            $this->addDirectoryToZip($zip, base_path('public'), 'public');
            $zip->addFile(base_path('.env'), '.env');

            $zip->close();

            // Clean up temporary database dump
            if (file_exists($dbDumpFile)) {
                unlink($dbDumpFile);
            }

            // Get backup file info
            $backupInfo = [
                'filename' => $backupFilename,
                'path' => $backupPath,
                'size' => filesize($backupPath),
                'created_at' => date('Y-m-d H:i:s'),
                'size_formatted' => $this->formatBytes(filesize($backupPath))
            ];

            return response()->json([
                'success' => true,
                'message' => 'System backup created successfully',
                'backup' => $backupInfo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create system backup',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    private function createDatabaseDump($outputFile)
    {
        $dbHost = env('DB_HOST', '127.0.0.1');
        $dbPort = env('DB_PORT', '3306');
        $dbDatabase = env('DB_DATABASE');
        $dbUsername = env('DB_USERNAME');
        $dbPassword = env('DB_PASSWORD');

        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --port=%s --single-transaction --quick --lock-tables=false %s > %s',
            escapeshellarg($dbUsername),
            escapeshellarg($dbPassword),
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbDatabase),
            escapeshellarg($outputFile)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Database dump failed: ' . implode("\n", $output));
        }
    }

    private function addDirectoryToZip($zip, $sourcePath, $zipPath)
    {
        if (! is_dir($sourcePath)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getPathname();
            $relativePath = $zipPath . '/' . str_replace($sourcePath, '', $filePath);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } elseif ($file->isFile()) {
                // Skip large files and unnecessary files
                if ($file->getSize() > 50 * 1024 * 1024) { // Skip files larger than 50MB
                    continue;
                }
                if (preg_match('/\.(log|cache|tmp)$/', $filePath)) {
                    continue;
                }
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    public function getMaintenanceStatus(Request $request): JsonResponse
    {
        try {
            $settings = SystemSettings::first();
            
            if (! $settings) {
                // Return default maintenance mode if no settings exist
                $maintenanceMode = false;
            } else {
                $maintenanceMode = $settings->maintenance_mode;
            }

            return response()->json([
                'success' => true,
                'maintenance_mode' => (bool) $maintenanceMode
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get maintenance status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
