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
            // Show inbox (received) or sent based on query parameter
            $box = $request->query('box', 'inbox');
            
            if ($box === 'sent') {
                // Show messages sent by current supplier
                $query = SupplierInquiry::where('sender_id', $user->id);
            } else {
                // Show messages received by current supplier (default)
                $query = SupplierInquiry::where('receiver_id', $user->id);
            }
        } else {
            // Admin can see all inquiries, especially those with null receiver_id (general admin inquiries)
            $query = SupplierInquiry::where(function($q) {
                $q->whereNull('receiver_id')
                  ->orWhereNotNull('receiver_id');
            });
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

        $paginator = $query->latest()->paginate($perPage);

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

        // Allow null user for anonymous submissions
        if ($authUser && !($authUser instanceof Supplier) && !($authUser instanceof \App\Models\Admin)) {
            abort(403, 'Only suppliers and admins can manage inquiries.');
        }

        return $authUser;
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string',
            'receiver_id' => 'nullable|exists:suppliers,id',
            'type' => 'nullable|in:inquiry,reply',
        ]);

        // Handle both authenticated and anonymous users
        if ($user instanceof Supplier) {
            // Authenticated supplier
            $senderId = $user->id;
            $from = 'supplier';
            $supplierId = $user->id;
        } else {
            // Anonymous user
            $senderId = 0;
            $from = 'anonymous';
            $supplierId = null;
        }

        $inquiry = SupplierInquiry::create([
            'sender_id' => $senderId,
            'receiver_id' => $validated['receiver_id'] ?? null,
            'full_name' => $validated['name'],
            'email_address' => $validated['email'],
            'phone_number' => $validated['phone'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'type' => $validated['type'] ?? 'inquiry',
            'is_read' => false,
            'from' => $from,
            'supplier_id' => $supplierId,
            'is_guest' => !$user instanceof Supplier,
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
            'sender_id' => $inquiry->sender_id,
            'receiver_id' => $inquiry->receiver_id,
            'full_name' => $inquiry->full_name,
            'email_address' => $inquiry->email_address,
            'phone_number' => $inquiry->phone_number,
            'subject' => $inquiry->subject,
            'message' => $inquiry->message,
            'admin_response' => $inquiry->admin_response,
            'admin_responded_at' => $inquiry->admin_responded_at?->format('Y-m-d H:i:s'),
            'is_read' => $inquiry->is_read,
            'from' => $inquiry->from,
            'type' => $inquiry->type,
            'supplier_id' => $inquiry->supplier_id,
            'admin_id' => $inquiry->admin_id,
            'created_at' => $inquiry->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $inquiry->updated_at->format('Y-m-d H:i:s'),
            'sender' => $inquiry->sender ? [
                'id' => $inquiry->sender->id,
                'name' => $inquiry->sender->name,
                'email' => $inquiry->sender->email,
            ] : null,
            'receiver' => $inquiry->receiver ? [
                'id' => $inquiry->receiver->id,
                'name' => $inquiry->receiver->name,
                'email' => $inquiry->receiver->email,
            ] : null,
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
