<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierInquiry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierInquiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $supplier = $this->resolveSupplier($request);

        $query = $supplier->inquiries()->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($request->boolean('unread')) {
            $query->where('is_unread', true);
        }

        if ($search = $request->query('search')) {
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->query('perPage', 15);
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(
                fn (SupplierInquiry $inquiry) => $this->transformInquiry($inquiry)
            ),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, SupplierInquiry $inquiry): JsonResponse
    {
        $supplier = $this->resolveSupplier($request);

        if ($inquiry->supplier_id !== $supplier->id) {
            abort(404);
        }

        return response()->json([
            'data' => $this->transformInquiry($inquiry),
        ]);
    }

    public function markRead(Request $request, SupplierInquiry $inquiry): JsonResponse
    {
        $supplier = $this->resolveSupplier($request);

        if ($inquiry->supplier_id !== $supplier->id) {
            abort(404);
        }

        if ($inquiry->is_unread) {
            $inquiry->forceFill(['is_unread' => false])->save();
        }

        return response()->json([
            'message' => 'Inquiry marked as read.',
            'data' => $this->transformInquiry($inquiry->refresh()),
        ]);
    }

    public function updateStatus(Request $request, SupplierInquiry $inquiry): JsonResponse
    {
        $supplier = $this->resolveSupplier($request);

        if ($inquiry->supplier_id !== $supplier->id) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:pending,in_progress,responded,closed,archived',
        ]);

        $inquiry->status = $validated['status'];
        $inquiry->save();

        return response()->json([
            'message' => 'Inquiry status updated.',
            'data' => $this->transformInquiry($inquiry->refresh()),
        ]);
    }

    public function reply(Request $request, SupplierInquiry $inquiry): JsonResponse
    {
        $supplier = $this->resolveSupplier($request);

        if ($inquiry->supplier_id !== $supplier->id) {
            abort(404);
        }

        $validated = $request->validate([
            'message' => 'required|string',
            'subject' => 'nullable|string|max:255',
        ]);

        $inquiry->forceFill([
            'last_response' => $validated['message'],
            'last_response_at' => now(),
            'status' => $inquiry->status === 'closed' ? 'closed' : 'responded',
            'is_unread' => false,
        ])->save();

        // Update subject if provided
        if (isset($validated['subject'])) {
            $inquiry->subject = $validated['subject'];
            $inquiry->save();
        }

        return response()->json([
            'message' => 'Response recorded successfully.',
            'data' => $this->transformInquiry($inquiry->refresh()),
        ]);
    }

    private function resolveSupplier(Request $request): Supplier
    {
        /** @var \Illuminate\Contracts\Auth\Authenticatable|null $authUser */
        $authUser = $request->user();

        if (! $authUser instanceof Supplier) {
            abort(403, 'Only suppliers can manage inquiries.');
        }

        return $authUser;
    }
}
