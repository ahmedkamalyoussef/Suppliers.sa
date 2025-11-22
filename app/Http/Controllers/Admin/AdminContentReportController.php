<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\ContentReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminContentReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware(function ($request, $next) {
            $user = $request->user();

            if (! $user instanceof Admin) {
                return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
            }

            if ($user->isSuperAdmin()) {
                return $next($request);
            }

            $user->loadMissing('permissions');
            if (! $user->permissions || ! $user->hasPermission('content_management_supervise')) {
                return response()->json(['message' => 'Unauthorized. Content supervision permission required.'], 403);
            }

            return $next($request);
        });
    }

    public function index(Request $request): JsonResponse
    {
        $query = ContentReport::query()->with(['handler', 'reporter', 'targetSupplier.profile']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->query('reportType')) {
            $query->where('report_type', $type);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('reason', 'like', "%{$search}%")
                    ->orWhere('details', 'like', "%{$search}%")
                    ->orWhere('reported_by_name', 'like', "%{$search}%")
                    ->orWhereHas('targetSupplier.profile', function ($profileQuery) use ($search) {
                        $profileQuery->where('business_name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = (int) $request->query('perPage', 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;

        $reports = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $reports->getCollection()->map(fn (ContentReport $report) => $this->transformReport($report))->values(),
            'pagination' => [
                'currentPage' => $reports->currentPage(),
                'perPage' => $reports->perPage(),
                'total' => $reports->total(),
                'lastPage' => $reports->lastPage(),
            ],
        ]);
    }

    public function show(ContentReport $report): JsonResponse
    {
        $report->load(['handler', 'reporter', 'targetSupplier.profile']);

        return response()->json([
            'data' => $this->transformReport($report),
        ]);
    }

    public function approve(Request $request, ContentReport $report): JsonResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $report->forceFill([
            'status' => 'approved',
            'handled_by_admin_id' => $admin->id,
            'handled_at' => now(),
            'resolution_notes' => $validated['notes'] ?? $report->resolution_notes,
        ])->save();

        return response()->json([
            'message' => 'Report approved.',
            'data' => $this->transformReport($report->fresh()),
        ]);
    }

    public function dismiss(Request $request, ContentReport $report): JsonResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $report->forceFill([
            'status' => 'dismissed',
            'handled_by_admin_id' => $admin->id,
            'handled_at' => now(),
            'resolution_notes' => $validated['notes'] ?? $report->resolution_notes,
        ])->save();

        return response()->json([
            'message' => 'Report dismissed.',
            'data' => $this->transformReport($report->fresh()),
        ]);
    }

    public function takedown(Request $request, ContentReport $report): JsonResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $report->forceFill([
            'status' => 'takedown',
            'handled_by_admin_id' => $admin->id,
            'handled_at' => now(),
            'resolution_notes' => $validated['notes'] ?? $report->resolution_notes,
        ])->save();

        return response()->json([
            'message' => 'Content takedown recorded.',
            'data' => $this->transformReport($report->fresh()),
        ]);
    }

    public function updateStatus(Request $request, ContentReport $report): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'dismissed', 'takedown'])],
            'notes' => ['nullable', 'string'],
        ]);

        $admin = $request->user();

        $report->forceFill([
            'status' => $validated['status'],
            'handled_by_admin_id' => $admin->id,
            'handled_at' => now(),
            'resolution_notes' => $validated['notes'] ?? $report->resolution_notes,
        ])->save();

        return response()->json([
            'message' => 'Report status updated.',
            'data' => $this->transformReport($report->fresh()),
        ]);
    }
}
