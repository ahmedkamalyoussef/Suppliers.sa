<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupplierInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSupplierInquiryController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            
            // Check if user is admin and has permission
            if (!$user || !($user instanceof \App\Models\Admin) || (!$user->is_super_admin && !$user->hasPermission('content_management_supervise'))) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            
            return $next($request);
        });
    }
    public function reply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|exists:supplier_inquiries,id',
            'message' => 'required|string',
        ]);

        $originalInquiry = SupplierInquiry::findOrFail($validated['id']);

        $reply = SupplierInquiry::create([
            'sender_id' => auth()->id(), // Admin ID
            'receiver_id' => $originalInquiry->sender_id, // Reply to the original sender
            'full_name' => 'Admin',
            'email_address' => 'admin@system.com',
            'subject' => $originalInquiry->subject,
            'message' => $validated['message'],
            'type' => 'reply',
            'is_read' => false,
            'from' => 'admin',
            'supplier_id' => $originalInquiry->supplier_id,
        ]);

        // Mark original as read
        if (!$originalInquiry->is_read) {
            $originalInquiry->update(['is_read' => true]);
        }

        return response()->json([
            'message' => 'Reply sent successfully',
        ]);
    }
}
