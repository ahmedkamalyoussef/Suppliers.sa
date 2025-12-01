<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Supplier\DocumentResource;
use App\Models\Admin;
use App\Models\SupplierDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDocumentController extends Controller
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
            if (! $user->permissions || 
                (! $user->hasPermission('content_management_view') && ! $user->hasPermission('content_management_supervise'))) {
                return response()->json(['message' => 'Unauthorized. Content management permission required.'], 403);
            }

            return $next($request);
        });
    }

    public function index(Request $request): JsonResponse
    {
        $query = SupplierDocument::query()->with(['supplier.profile', 'reviewer']);

        if ($status = $request->query('status')) {
            if ($status === 'all') {
                $query->whereIn('status', ['rejected', 'pending']);
            } else {
                $query->where('status', $status);
            }
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

        $perPage = (int) $request->query('per_page', 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;

        // Debug: Get all documents first to see what's there
        $allDocuments = SupplierDocument::with('supplier.profile')->get();
        \Log::info('All documents count: ' . $allDocuments->count());
        \Log::info('Requested status: ' . $request->query('status'));
        \Log::info('Documents with their statuses:');
        foreach ($allDocuments as $doc) {
            \Log::info('Doc ID: ' . $doc->id . ', Status: ' . $doc->status . ', Supplier: ' . ($doc->supplier ? $doc->supplier->name : 'No supplier'));
        }

        $documents = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $documents->getCollection()->map(function (SupplierDocument $document) use ($request) {
                $supplier = $document->supplier;
                $profile = $supplier->profile;
                
                $data = [
                    'id' => $document->id,
                    'status' => $document->status,
                    'notes' => $document->notes,
                    'reviewed_at' => $document->reviewed_at,
                    'created_at' => $document->created_at,
                    'document_link' => $document->file_path ? url($document->file_path) : null,
                    
                    // Supplier information
                    'supplier' => [
                        'id' => $supplier->id,
                        'name' => $supplier->name,
                        'email' => $supplier->email,
                        'phone' => $supplier->phone,
                        'status' => $supplier->status,
                        'profile_image' => $supplier->profile_image ? url($supplier->profile_image) : null,
                        'business_name' => $profile?->business_name,
                        'created_at' => $supplier->created_at,
                    ]
                ];
                
                // Add non-null document fields
                if ($document->document_type) {
                    $data['document_type'] = $document->document_type;
                }
                if ($document->reference_number) {
                    $data['reference_number'] = $document->reference_number;
                }
                if ($document->issue_date) {
                    $data['issue_date'] = $document->issue_date;
                }
                if ($document->expiry_date) {
                    $data['expiry_date'] = $document->expiry_date;
                }
                
                return $data;
            })->values(),
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
            'data' => (new \App\Http\Resources\DocumentResource($document))->toArray(request()),
            'supplier' => (new \App\Http\Resources\SupplierResource($document->supplier))->toArray(request()),
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

        // Update supplier status to active when document is approved
        $supplier = $document->supplier;
        if ($supplier->status !== 'active') {
            $supplier->forceFill(['status' => 'active'])->save();
        }

        return response()->json([
            'message' => 'Document approved and supplier status updated to active.',
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

        $supplier = $document->supplier;

        return response()->json([
            'message' => 'Document rejected.',
            'data' => [
                'id' => $document->id,
                'document_type' => $document->document_type,
                'reference_number' => $document->reference_number,
                'issue_date' => $document->issue_date,
                'expiry_date' => $document->expiry_date,
                'status' => $document->status,
                'notes' => $document->notes,
                'reviewed_at' => $document->reviewed_at,
                'created_at' => $document->created_at,
                'document_link' => $document->file_path ? url($document->file_path) : null,
                
                // Supplier information
                'supplier' => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'email' => $supplier->email,
                    'phone' => $supplier->phone,
                    'status' => $supplier->status,
                    'profile_image' => $supplier->profile_image ? url($supplier->profile_image) : null,
                    'business_name' => $supplier->profile?->business_name,
                    'created_at' => $supplier->created_at,
                ]
            ]
        ]);
    }

    public function requestResubmission(Request $request, SupplierDocument $document): JsonResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $document->forceFill([
            'status' => 'pending',
            'notes' => $validated['notes'] ?? $document->notes,
            'reviewed_by_admin_id' => $admin->id,
            'reviewed_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Resubmission requested.',
            'data' => (new DocumentResource($document->fresh()))->toArray(request()),
        ]);
    }

    public function approvedToday(): JsonResponse
    {
        $today = now()->startOfDay();
        
        $approvedDocuments = SupplierDocument::where('status', 'verified')
            ->whereDate('reviewed_at', $today)
            ->count();
            
        $approvedReviews = \App\Models\SupplierRating::where('status', 'approved')
            ->whereDate('moderated_at', $today)
            ->count();

        return response()->json([
            'approvedToday' => $approvedDocuments + $approvedReviews
        ]);
    }
}
