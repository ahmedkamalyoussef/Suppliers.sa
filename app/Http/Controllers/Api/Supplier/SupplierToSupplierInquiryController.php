<?php

namespace App\Http\Controllers\Api\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierInquiryResource;
use App\Models\SupplierToSupplierInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierToSupplierInquiryController extends Controller
{
    /**
     * Mark an inquiry as read by the receiver
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function markAsRead($id): JsonResponse
    {
        $inquiry = SupplierToSupplierInquiry::findOrFail($id);
        
        // Check if the authenticated user is the receiver
        if (Auth::id() !== $inquiry->receiver_supplier_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only the receiver can mark the inquiry as read.'
            ], 403);
        }
        
        // Only update if not already read
        if (is_null($inquiry->read_at)) {
            $inquiry->update(['read_at' => now()]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Inquiry marked as read successfully.'
        ]);
    }
    
    /**
     * Get all supplier-to-supplier inquiries
     */
    public function index(): JsonResponse
    {
        $supplier = Auth::user();
        
        // Get only inquiries where the authenticated user is the receiver
        $inquiries = SupplierToSupplierInquiry::with(['sender', 'receiver', 'replies'])
            ->where('receiver_supplier_id', $supplier->id)
            ->latest()
            ->get();

        return response()->json([
            'data' => SupplierInquiryResource::collection($inquiries),
        ]);
    }

    /**
     * Get a specific conversation thread
     */
    public function show(SupplierToSupplierInquiry $inquiry): JsonResponse
    {
        $supplier = Auth::user();
        
        // Ensure the supplier is part of this conversation (either sender or receiver)
        if ($inquiry->sender_supplier_id != $supplier->id && 
            $inquiry->receiver_supplier_id != $supplier->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Mark as read when viewed by the receiver
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
     * Send a new inquiry to another supplier
     */
    public function store(Request $request): JsonResponse
    {
        $supplier = Auth::user();
        
        $validated = $request->validate([
            'receiver_supplier_id' => 'required|exists:suppliers,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'company' => 'required|string|max:255',
        ]);

        // Prevent sending inquiry to self
        if ($supplier->id == $validated['receiver_supplier_id']) {
            return response()->json([
                'message' => 'You cannot send an inquiry to yourself'
            ], 422);
        }

        $inquiry = SupplierToSupplierInquiry::create([
            'sender_supplier_id' => $supplier->id,
            'receiver_supplier_id' => $validated['receiver_supplier_id'],
            'sender_name' => $supplier->name,
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'company' => $validated['company'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'is_read' => false,
            'type' => 'inquiry'
        ]);

        return response()->json([
            'message' => 'Inquiry sent successfully',
            'data' => new SupplierInquiryResource($inquiry->load('receiver'))
        ], 201);
    }

    /**
     * Reply to an inquiry
     */
    public function reply(Request $request, SupplierToSupplierInquiry $inquiry): JsonResponse
    {
        $supplier = Auth::user();
        
        // Ensure the supplier is the receiver of the original inquiry
        if ($inquiry->receiver_supplier_id !== $supplier->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $request->validate([
            'message' => 'required|string|max:2000',
        ]);
        
        // Create the reply
        $reply = SupplierToSupplierInquiry::create([
            'sender_supplier_id' => $supplier->id,
            'receiver_supplier_id' => $inquiry->sender_supplier_id,
            'sender_name' => $supplier->name,
            'email' => $supplier->email,
            'phone' => $supplier->phone,
            'subject' => 'Re: ' . $inquiry->subject,
            'message' => $request->message,
            'parent_id' => $inquiry->id,
            'is_read' => false,
            'company' => $supplier->profile->company_name ?? null,
            'type' => 'reply'
        ]);
        
        // Mark the original inquiry as read
        if (!$inquiry->is_read) {
            $inquiry->update(['is_read' => true]);
        }
        
        return response()->json([
            'message' => 'Reply sent successfully',
            'data' => new SupplierInquiryResource($reply->load('receiver'))
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
            
        return response()->json(['unread_count' => $count]);
    }
}
