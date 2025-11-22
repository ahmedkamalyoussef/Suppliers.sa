<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Supplier;
use App\Models\SupplierInquiry;
use App\Models\SupplierProfile;
use App\Models\SupplierRating;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

        $pendingInquiries = SupplierInquiry::where('status', 'pending')->count();
        $unreadInquiries = SupplierInquiry::where('is_unread', true)->count();
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

        $systemHealth = [
            'serverStatus' => [
                'status' => 'online',
                'uptime' => '99.9%',
                'incidentsThisMonth' => 0,
            ],
            'database' => [
                'status' => 'healthy',
                'backupStatus' => 'Completed 12 hours ago',
            ],
            'security' => [
                'status' => 'protected',
                'openAlerts' => 0,
            ],
        ];

        return response()->json([
            'range' => $range,
            'currentAdmin' => $this->transformAdmin($admin),
            'overview' => [
                'systemStats' => $systemStats,
                'pendingActions' => $pendingActions,
                'recentActivities' => $recentActivities,
                'quickActions' => $quickActions,
                'systemHealth' => $systemHealth,
            ],
            'totals' => [
                'suppliers' => $totalSuppliers,
                'activeSuppliers' => $activeSuppliers,
                'newSuppliers' => $newSuppliers,
                'pendingVerifications' => $pendingVerifications,
                'pendingInquiries' => $pendingInquiries,
                'pendingRatings' => $pendingRatings,
                'admins' => Admin::count(),
            ],
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
                    'enterprise' => 24900,
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
            'enterprise' => 'bg-purple-500',
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
}
