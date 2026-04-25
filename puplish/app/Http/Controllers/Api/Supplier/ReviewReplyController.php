<?php

namespace App\Http\Controllers\Api\Supplier;

use App\Http\Controllers\Controller;
use App\Models\ReviewReply;
use App\Models\SupplierRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewReplyController extends Controller
{
    /**
     * Store a new reply to a review
     */
    public function store(Request $request, SupplierRating $rating): JsonResponse
    {
        $supplier = Auth::user();
        
        // Check if user is an active supplier
        if (! $supplier || $supplier->status !== 'active') {
            return response()->json(['message' => 'Only active suppliers can reply to reviews'], 403);
        }
        
        // Ensure the supplier is the one being reviewed
        if ($rating->rated_supplier_id !== $supplier->id) {
            return response()->json([
                'message' => 'Unauthorized: You can only reply to reviews about your business.'
            ], 403);
        }

        // Check if already replied
        if ($rating->reply) {
            return response()->json([
                'message' => 'You have already replied to this review.'
            ], 422);
        }

        $request->validate([
            'reply' => 'required|string|max:1000',
        ]);

        $reply = ReviewReply::create([
            'supplier_rating_id' => $rating->id,
            'supplier_id' => $supplier->id,
            'reply' => $request->reply,
        ]);

        return response()->json([
            'message' => 'Reply posted successfully',
            'data' => $reply->load('supplier')
        ], 201);
    }

    /**
     * Update an existing reply
     */
    public function update(Request $request, ReviewReply $reply): JsonResponse
    {
        $supplier = Auth::user();
        
        // Check if user is an active supplier
        if (! $supplier || $supplier->status !== 'active') {
            return response()->json(['message' => 'Only active suppliers can update review replies'], 403);
        }
        
        // Ensure the supplier owns this reply
        if ($reply->supplier_id !== $supplier->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'reply' => 'required|string|max:1000',
        ]);

        $reply->update([
            'reply' => $request->reply,
        ]);

        return response()->json([
            'message' => 'Reply updated successfully',
            'data' => $reply->load('supplier')
        ]);
    }

    /**
     * Delete a reply
     */
    public function destroy(ReviewReply $reply): JsonResponse
    {
        $supplier = Auth::user();
        
        // Check if user is an active supplier
        if (! $supplier || $supplier->status !== 'active') {
            return response()->json(['message' => 'Only active suppliers can delete review replies'], 403);
        }
        
        // Ensure the supplier owns this reply
        if ($reply->supplier_id !== $supplier->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $reply->delete();

        return response()->json([
            'message' => 'Reply deleted successfully'
        ]);
    }
}
