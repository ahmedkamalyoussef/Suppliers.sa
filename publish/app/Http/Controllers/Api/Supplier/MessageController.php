<?php

namespace App\Http\Controllers\Api\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    /**
     * Helper method to get supplier email (contact email or supplier email)
     */
    private function getSupplierEmail($supplier)
    {
        // Try to get contact email from profile first
        if ($supplier->profile && $supplier->profile->contact_email) {
            return $supplier->profile->contact_email;
        }
        
        // Fallback to supplier email
        return $supplier->email;
    }

    /**
     * Helper method to find supplier by email (check both supplier.email and profile.contact_email)
     */
    private function findSupplierByEmail($email)
    {
        // First try to find by supplier email
        $supplier = Supplier::where('email', $email)->first();
        
        if ($supplier) {
            return $supplier;
        }
        
        // Then try to find by profile contact email
        $supplier = Supplier::whereHas('profile', function($query) use ($email) {
            $query->where('contact_email', $email);
        })->first();
        
        return $supplier;
    }
    /**
     * Get all messages for the authenticated supplier (sent and received)
     */
    public function index(Request $request): JsonResponse
    {
        $supplier = Auth::user();
        
        $messages = Message::with(['sender', 'receiver'])
            ->where(function($query) use ($supplier) {
                $query->where('sender_supplier_id', $supplier->id)
                      ->orWhere('receiver_supplier_id', $supplier->id);
            })
            ->latest()
            ->get();

        return response()->json([
            'data' => MessageResource::collection($messages)
        ]);
    }

    /**
     * Send a new message to another supplier
     */
    public function store(Request $request): JsonResponse
    {
        $supplier = Auth::user();
        
        $validated = $request->validate([
            'receiver_email' => 'required|email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        // Find receiver supplier by email (check both supplier.email and profile.contact_email)
        $receiverSupplier = $this->findSupplierByEmail($validated['receiver_email']);
        
        if (!$receiverSupplier) {
            return response()->json([
                'message' => 'Supplier not found with this email address'
            ], 404);
        }

        // Prevent sending message to self
        if ($supplier->id == $receiverSupplier->id) {
            return response()->json([
                'message' => 'You cannot send a message to yourself'
            ], 422);
        }

        // Get sender and receiver emails
        $senderEmail = $this->getSupplierEmail($supplier);
        $receiverEmail = $this->getSupplierEmail($receiverSupplier);

        $message = Message::create([
            'sender_supplier_id' => $supplier->id,
            'sender_email' => $senderEmail,
            'receiver_supplier_id' => $receiverSupplier->id,
            'receiver_email' => $receiverEmail,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'is_read' => false,
            'type' => 'message'
        ]);

        // Here you could add email notification logic using $receiverEmail
        // For now, we'll just return the created message

        return response()->json([
            'message' => 'Message sent successfully',
            'data' => new MessageResource($message->load('sender', 'receiver'))
        ], 201);
    }

    /**
     * Mark a message as read
     */
    public function markAsRead(Message $message): JsonResponse
    {
        $supplier = Auth::user();
        
        // Check if the authenticated user is the receiver
        if ($supplier->id !== $message->receiver_supplier_id) {
            return response()->json([
                'message' => 'Unauthorized: Only the receiver can mark the message as read.'
            ], 403);
        }
        
        // Only update if not already read
        if (!$message->is_read) {
            $message->update(['is_read' => true]);
        }
        
        return response()->json([
            'message' => 'Message marked as read successfully.'
        ]);
    }

    /**
     * Get unread messages count
     */
    public function unreadCount(): JsonResponse
    {
        $count = Message::where('receiver_supplier_id', Auth::id())
            ->where('is_read', false)
            ->count();
            
        return response()->json(['unread_count' => $count]);
    }
}
