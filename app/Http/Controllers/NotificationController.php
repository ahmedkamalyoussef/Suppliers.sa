<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get user notifications
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|string|in:system,business,reviews,payments',
            'status' => 'nullable|string|in:read,unread',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $query = $user->notifications();

        // Apply filters
        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->status === 'read') {
            $query->whereNotNull('read_at');
        } elseif ($request->status === 'unread') {
            $query->whereNull('read_at');
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate($request->limit ?? 20);

        return response()->json([
            'success' => true,
            'notifications' => $notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->data['title'] ?? '',
                    'message' => $notification->data['message'] ?? '',
                    'icon' => $notification->data['icon'] ?? 'bell',
                    'action_url' => $notification->data['action_url'] ?? null,
                    'read' => $notification->read_at !== null,
                    'created_at' => $notification->created_at->toISOString(),
                ];
            }),
            'unread_count' => $user->unreadNotifications()->count(),
            'pagination' => [
                'page' => $notifications->currentPage(),
                'limit' => $notifications->perPage(),
                'total' => $notifications->total(),
                'totalPages' => $notifications->lastPage(),
            ]
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->findOrFail($id);

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'unread_count' => 0,
        ]);
    }

    /**
     * Delete notification
     */
    public function delete(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->findOrFail($id);

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully',
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Get notification settings
     */
    public function settings(Request $request): JsonResponse
    {
        $user = $request->user();

        $settings = [
            'email_notifications' => $user->notification_settings['email_notifications'] ?? true,
            'push_notifications' => $user->notification_settings['push_notifications'] ?? true,
            'sms_notifications' => $user->notification_settings['sms_notifications'] ?? false,
            'business_updates' => $user->notification_settings['business_updates'] ?? true,
            'review_notifications' => $user->notification_settings['review_notifications'] ?? true,
            'payment_notifications' => $user->notification_settings['payment_notifications'] ?? true,
            'system_notifications' => $user->notification_settings['system_notifications'] ?? true,
            'marketing_emails' => $user->notification_settings['marketing_emails'] ?? false,
        ];

        return response()->json([
            'success' => true,
            'settings' => $settings
        ]);
    }

    /**
     * Update notification settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email_notifications' => 'required|boolean',
            'push_notifications' => 'required|boolean',
            'sms_notifications' => 'required|boolean',
            'business_updates' => 'required|boolean',
            'review_notifications' => 'required|boolean',
            'payment_notifications' => 'required|boolean',
            'system_notifications' => 'required|boolean',
            'marketing_emails' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        
        $settings = [
            'email_notifications' => $request->email_notifications,
            'push_notifications' => $request->push_notifications,
            'sms_notifications' => $request->sms_notifications,
            'business_updates' => $request->business_updates,
            'review_notifications' => $request->review_notifications,
            'payment_notifications' => $request->payment_notifications,
            'system_notifications' => $request->system_notifications,
            'marketing_emails' => $request->marketing_emails,
        ];

        $user->update(['notification_settings' => $settings]);

        return response()->json([
            'success' => true,
            'message' => 'Notification settings updated successfully',
            'settings' => $settings
        ]);
    }

    /**
     * Send notification to user (internal use)
     */
    public function sendNotification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'type' => 'required|string|in:system,business,reviews,payments',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'icon' => 'nullable|string|max:50',
            'action_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = \App\Models\User::find($request->user_id);

        $notification = $user->notify(new \App\Notifications\CustomNotification([
            'type' => $request->type,
            'title' => $request->title,
            'message' => $request->message,
            'icon' => $request->icon ?? 'bell',
            'action_url' => $request->action_url,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Notification sent successfully',
        ]);
    }

    /**
     * Send bulk notifications (admin only)
     */
    public function sendBulk(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'recipients' => 'required|array',
            'recipients.*' => 'exists:users,id',
            'type' => 'required|string|in:system,business,reviews,payments',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'icon' => 'nullable|string|max:50',
            'action_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $users = \App\Models\User::whereIn('id', $request->recipients)->get();

        foreach ($users as $user) {
            $user->notify(new \App\Notifications\CustomNotification([
                'type' => $request->type,
                'title' => $request->title,
                'message' => $request->message,
                'icon' => $request->icon ?? 'bell',
                'action_url' => $request->action_url,
            ]));
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulk notifications sent successfully',
            'sent_count' => $users->count(),
        ]);
    }

    /**
     * Get notification statistics (admin only)
     */
    public function statistics(Request $request): JsonResponse
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

        $totalNotifications = \Illuminate\Notifications\DatabaseNotification::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->count();
        $readNotifications = \Illuminate\Notifications\DatabaseNotification::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->whereNotNull('read_at')->count();
        $unreadNotifications = $totalNotifications - $readNotifications;

        $notificationsByType = \Illuminate\Notifications\DatabaseNotification::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        return response()->json([
            'success' => true,
            'statistics' => [
                'total' => $totalNotifications,
                'read' => $readNotifications,
                'unread' => $unreadNotifications,
                'read_rate' => $totalNotifications > 0 ? round(($readNotifications / $totalNotifications) * 100, 2) : 0,
                'by_type' => $notificationsByType,
            ]
        ]);
    }

    /**
     * Get notification templates (admin only)
     */
    public function templates(): JsonResponse
    {
        $templates = [
            'welcome' => [
                'type' => 'system',
                'title' => 'Welcome to Suppliers.sa!',
                'message' => 'Thank you for joining Suppliers.sa. Get started by adding your first business.',
                'icon' => 'gift',
            ],
            'business_verified' => [
                'type' => 'business',
                'title' => 'Business Verified!',
                'message' => 'Congratulations! Your business has been verified and is now featured.',
                'icon' => 'check-circle',
            ],
            'new_review' => [
                'type' => 'reviews',
                'title' => 'New Review Received',
                'message' => 'Your business has received a new review. Check it out now!',
                'icon' => 'star',
            ],
            'payment_successful' => [
                'type' => 'payments',
                'title' => 'Payment Successful',
                'message' => 'Your payment has been processed successfully. Thank you for your subscription.',
                'icon' => 'credit-card',
            ],
            'plan_expiring' => [
                'type' => 'payments',
                'title' => 'Plan Expiring Soon',
                'message' => 'Your subscription plan will expire soon. Renew to continue enjoying premium features.',
                'icon' => 'clock',
            ],
            'business_limit_reached' => [
                'type' => 'business',
                'title' => 'Business Limit Reached',
                'message' => 'You have reached the maximum number of businesses for your current plan. Upgrade to add more.',
                'icon' => 'alert-circle',
            ],
        ];

        return response()->json([
            'success' => true,
            'templates' => $templates
        ]);
    }

    private function getDateRange(string $period): array
    {
        $now = \Carbon\Carbon::now();
        
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
}
