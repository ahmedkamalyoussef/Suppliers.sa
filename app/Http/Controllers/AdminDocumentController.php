<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\SupplierDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminDocumentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware(function ($request, $next) {
            $user = $request->user();

            if (!$user instanceof Admin) {
                return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
            }

            if ($user->isSuperAdmin()) {
                return $next($request);
            }

            $user->loadMissing('permissions');
            if (!$user->permissions || !$user->hasPermission('content_management_supervise')) {
                return response()->json(['message' => 'Unauthorized. Content supervision permission required.'], 403);
            }

            return $next($request);
        });
    }

    public function index(Request $request): JsonResponse
    {
        $query = SupplierDocument::query()->with(['supplier.profile', 'reviewer']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->query('documentType')) {
            $query->where('document_type', $type);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('document_type', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                        $supplierQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('supplier.profile', function ($profileQuery) use ($search) {
                        $profileQuery->where('business_name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = (int) $request->query('perPage', 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;

        $documents = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $documents->getCollection()->map(fn (SupplierDocument $document) => $this->transformDocument($document))->values(),
            'pagination' => [
                'currentPage' => $documents->currentPage(),
                'perPage' => $documents->perPage(),
                'total' => $documents->total(),
                'lastPage' => $documents->lastPage(),
            ],
        ]);
    }

    public function show(SupplierDocument $document): JsonResponse
    {
        $document->load(['supplier.profile', 'reviewer']);

        return response()->json([
            'data' => $this->transformDocument($document),
            'supplier' => $this->transformSupplier($document->supplier),
        ]);
    }

    public function approve(Request $request, SupplierDocument $document): JsonResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $document->forceFill([
            'status' => 'verified',
            'notes' => $validated['notes'] ?? $document->notes,
            'reviewed_by_admin_id' => $admin->id,
            'reviewed_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Document approved.',
            'data' => $this->transformDocument($document->fresh()),
        ]);
    }

    public function reject(Request $request, SupplierDocument $document): JsonResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $document->forceFill([
            'status' => 'rejected',
            'notes' => $validated['notes'] ?? $document->notes,
            'reviewed_by_admin_id' => $admin->id,
            'reviewed_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Document rejected.',
            'data' => $this->transformDocument($document->fresh()),
        ]);
    }

    public function requestResubmission(Request $request, SupplierDocument $document): JsonResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $document->forceFill([
            'status' => 'pending_verification',
            'notes' => $validated['notes'] ?? $document->notes,
            'reviewed_by_admin_id' => $admin->id,
            'reviewed_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Resubmission requested.',
            'data' => $this->transformDocument($document->fresh()),
        ]);
    }
}

