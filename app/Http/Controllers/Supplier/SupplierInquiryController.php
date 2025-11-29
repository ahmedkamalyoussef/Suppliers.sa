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
        $user = $this->resolveUser($request);

        if ($user instanceof Supplier) {
            $query = $user->inquiries()->latest();
        } else {
            // Admin can see all inquiries
            $query = SupplierInquiry::latest();
        }

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
        $user = $this->resolveUser($request);

        if ($user instanceof Supplier && $inquiry->supplier_id !== $user->id) {
            abort(404);
        }

        return response()->json([
            'data' => $this->transformInquiry($inquiry),
        ]);
    }

    public function markRead(Request $request, SupplierInquiry $inquiry): JsonResponse
    {
        $user = $this->resolveUser($request);

        if ($user instanceof Supplier && $inquiry->supplier_id !== $user->id) {
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
        $user = $this->resolveUser($request);

        if ($user instanceof Supplier && $inquiry->supplier_id !== $user->id) {
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
        $user = $this->resolveUser($request);

        if ($user instanceof Supplier && $inquiry->supplier_id !== $user->id) {
            abort(404);
        }

        $validated = $request->validate([
            'message' => 'required|string',
            'subject' => 'nullable|string|max:255',
        ]);

        // Update the inquiry with admin response
        $inquiry->update([
            'admin_id' => $user instanceof \App\Models\Admin ? $user->id : null,
            'admin_response' => $validated['message'],
            'admin_responded_at' => now(),
            'is_read' => true,
        ]);

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

    private function resolveUser(Request $request)
    {
        /** @var \Illuminate\Contracts\Auth\Authenticatable|null $authUser */
        $authUser = $request->user();

        if (! $authUser instanceof Supplier && ! $authUser instanceof \App\Models\Admin) {
            abort(403, 'Only suppliers and admins can manage inquiries.');
        }

        return $authUser;
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        
        // Only suppliers can create inquiries
        if (! $user instanceof Supplier) {
            abort(403, 'Only suppliers can create inquiries.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string',
        ]);

        $inquiry = SupplierInquiry::create([
            'full_name' => $validated['name'],
            'email_address' => $validated['email'],
            'phone_number' => $validated['phone'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'type' => 'inquiry',
            'is_read' => false,
            'from' => 'admin',
            'supplier_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Inquiry created successfully.',
            'data' => $this->transformInquiry($inquiry),
        ], 201);
    }

    protected function transformInquiry(SupplierInquiry $inquiry): array
    {
        return [
            'id' => $inquiry->id,
            'full_name' => $inquiry->full_name,
            'email_address' => $inquiry->email_address,
            'phone_number' => $inquiry->phone_number,
            'subject' => $inquiry->subject,
            'message' => $inquiry->message,
            'admin_response' => $inquiry->admin_response,
            'admin_responded_at' => $inquiry->admin_responded_at?->format('Y-m-d H:i:s'),
            'is_read' => $inquiry->is_read,
            'from' => $inquiry->from,
            'supplier_id' => $inquiry->supplier_id,
            'admin_id' => $inquiry->admin_id,
            'created_at' => $inquiry->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $inquiry->updated_at->format('Y-m-d H:i:s'),
            'supplier' => $inquiry->supplier ? [
                'id' => $inquiry->supplier->id,
                'name' => $inquiry->supplier->name,
                'email' => $inquiry->supplier->email,
            ] : null,
            'admin' => $inquiry->admin ? [
                'id' => $inquiry->admin->id,
                'name' => $inquiry->admin->name,
                'email' => $inquiry->admin->email,
            ] : null,
        ];
    }
}
