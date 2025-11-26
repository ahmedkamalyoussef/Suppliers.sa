<?php

namespace App\Http\Controllers\Api\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierInquiryRequest;
use App\Http\Resources\SupplierInquiryResource;
use App\Models\SupplierToSupplierInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierInquiryController extends Controller
{
    /**
     * Get all inquiries for the authenticated supplier
     */
    public function index(Request $request): JsonResponse
    {
        $supplier = Auth::user();
        
        $inquiries = SupplierToSupplierInquiry::with(['sender', 'receiver', 'replies'])
            ->where('receiver_supplier_id', $supplier->id)
            ->whereNull('parent_id') // Only parent inquiries
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => SupplierInquiryResource::collection($inquiries),
            'pagination' => [
                'total' => $inquiries->total(),
                'per_page' => $inquiries->perPage(),
                'current_page' => $inquiries->currentPage(),
                'last_page' => $inquiries->lastPage(),
            ]
        ]);
    }

    /**
     * Get a specific conversation thread
     */
    public function show(SupplierToSupplierInquiry $inquiry): JsonResponse
    {
        $supplier = Auth::user();
        
        // Ensure the supplier is part of this conversation
        if ($inquiry->sender_supplier_id !== $supplier->id && 
            $inquiry->receiver_supplier_id !== $supplier->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Mark as read when viewed
        if ($inquiry->receiver_supplier_id === $supplier->id && !$inquiry->is_read) {
            $inquiry->update(['is_read' => true]);
        }

        // Get the full conversation thread
        $thread = SupplierToSupplierInquiry::with(['sender', 'receiver'])
            ->where(function($query) use ($inquiry) {
                $query->where('id', $inquiry->id)
                    ->orWhere('parent_id', $inquiry->id);
            })
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => SupplierInquiryResource::collection($thread)
        ]);
    }

    /**
     * Send a new inquiry or reply
     */
    public function store(StoreSupplierInquiryRequest $request): JsonResponse
    {
        $supplier = Auth::user();
        
        $data = $request->validated();
        $data['sender_supplier_id'] = $supplier->id;
        
        // If this is a reply, ensure the parent exists and the supplier is part of the conversation
        if (isset($data['parent_id'])) {
            $parent = SupplierToSupplierInquiry::findOrFail($data['parent_id']);
            
            if ($parent->sender_supplier_id !== $supplier->id && 
                $parent->receiver_supplier_id !== $supplier->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            
            // Set the receiver to the other party in the conversation
            $data['receiver_supplier_id'] = $parent->sender_supplier_id === $supplier->id 
                ? $parent->receiver_supplier_id 
                : $parent->sender_supplier_id;
        }
        
        $inquiry = SupplierToSupplierInquiry::create($data);
        
        return response()->json([
            'message' => 'Inquiry sent successfully',
            'data' => new SupplierInquiryResource($inquiry->load('sender', 'receiver'))
        ], 201);
    }

    /**
     * Get unread inquiries count
     */
    public function unreadCount(): JsonResponse
    {
        $count = SupplierToSupplierInquiry::where('receiver_supplier_id', Auth::id())
            ->where('is_read', false)
            ->count();
            
        return response()->json(['count' => $count]);
    }
}
