<?php

namespace App\Http\Controllers\Api\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Resources\InboxItemResource;
use App\Models\Message;
use App\Models\ReviewReply;
use App\Models\SupplierInquiry;
use App\Models\SupplierRating;
use App\Models\SupplierToSupplierInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class InboxController extends Controller
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
     * Get unified inbox with all communications
     */
    public function index(Request $request): JsonResponse
    {
        $supplier = Auth::user();
        $filter = $request->get('filter', 'all'); // all, sent, received

        // Collect all communications
        $allItems = new Collection();

        // 1. Supplier Inquiries
        $supplierInquiries = SupplierInquiry::with(['supplier', 'sender', 'receiver'])
            ->where(function($query) use ($supplier) {
                $query->where('sender_id', $supplier->id)      // Sent by this supplier
                      ->orWhere('receiver_id', $supplier->id);    // Received by this supplier
            })
            ->get()
            ->map(function ($item) {
                $item->resource_type = 'supplier_inquiry';
                return $item;
            });

        // 2. Supplier to Supplier Inquiries
        $supplierToSupplierInquiries = SupplierToSupplierInquiry::with(['sender', 'receiver'])
            ->where(function($query) use ($supplier) {
                $query->where('sender_supplier_id', $supplier->id)
                      ->orWhere('receiver_supplier_id', $supplier->id);
            })
            ->get()
            ->map(function ($item) {
                $item->resource_type = 'supplier_to_supplier_inquiry';
                return $item;
            });

        // 3. Messages
        $messages = Message::with(['sender', 'receiver'])
            ->where(function($query) use ($supplier) {
                $query->where('sender_supplier_id', $supplier->id)
                      ->orWhere('receiver_supplier_id', $supplier->id);
            })
            ->get()
            ->map(function ($item) {
                $item->resource_type = 'message';
                return $item;
            });

        // 4. Supplier Ratings (reviews about this supplier or by this supplier)
        $ratings = SupplierRating::with(['rater', 'rated', 'reply'])
            ->where(function($query) use ($supplier) {
                $query->where('rater_supplier_id', $supplier->id)
                      ->orWhere('rated_supplier_id', $supplier->id);
            })
            ->get()
            ->map(function ($item) {
                $item->resource_type = 'supplier_rating';
                return $item;
            });

        // 5. Review Replies (replies by this supplier or to reviews about this supplier)
        $reviewReplies = ReviewReply::with(['supplier', 'rating.rater'])
            ->where('supplier_id', $supplier->id)
            ->get()
            ->map(function ($item) {
                $item->resource_type = 'review_reply';
                return $item;
            });

        // Merge all collections
        $allItems = $allItems
            ->merge($supplierInquiries)
            ->merge($supplierToSupplierInquiries)
            ->merge($messages)
            ->merge($ratings)
            ->merge($reviewReplies);

        // Apply filter
        if ($filter !== 'all') {
            $allItems = $allItems->filter(function ($item) use ($supplier) {
                $direction = $this->getItemDirection($item, $supplier->id);
                return $direction === $filter;
            });
        }

        // Sort by created_at descending
        $sortedItems = $allItems->sortByDesc('created_at')->values();

        // Separate inbox and sent
        $inboxItems = $sortedItems->filter(function ($item) use ($supplier) {
            return $this->getItemDirection($item, $supplier->id) === 'received';
        })->values();

        $sentItems = $sortedItems->filter(function ($item) use ($supplier) {
            return $this->getItemDirection($item, $supplier->id) === 'sent';
        })->values();

        return response()->json([
            'inbox' => InboxItemResource::collection($inboxItems),
            'sent' => InboxItemResource::collection($sentItems),
            'all' => InboxItemResource::collection($sortedItems),
            'unread_count' => $this->getUnreadCount($supplier->id),
            'avg_response_time' => $this->calculateAverageResponseTime($supplier->id),
            'response_rate' => $this->calculateResponseRate($supplier->id),
        ]);
    }

    /**
     * Get unread count for all communications
     */
    private function getUnreadCount($supplierId): int
    {
        $count = 0;

        // Supplier inquiries
        $count += SupplierInquiry::where(function($query) use ($supplierId) {
                $query->where('receiver_id', $supplierId);
            })
            ->where('is_read', false)
            ->count();

        // Supplier to supplier inquiries
        $count += SupplierToSupplierInquiry::where('receiver_supplier_id', $supplierId)
            ->where('is_read', false)
            ->count();

        // Messages
        $count += Message::where('receiver_supplier_id', $supplierId)
            ->where('is_read', false)
            ->count();

        // Ratings
        $count += SupplierRating::where('rated_supplier_id', $supplierId)
            ->where('is_read', false)
            ->count();

        return $count;
    }

    /**
     * Get item direction (sent/received)
     */
    private function getItemDirection($item, $supplierId): string
    {
        switch ($item->resource_type) {
            case 'supplier_inquiry':
                return $item->sender_id == $supplierId ? 'sent' : 'received';
            case 'supplier_to_supplier_inquiry':
                return $item->sender_supplier_id == $supplierId ? 'sent' : 'received';
            case 'message':
                return $item->sender_supplier_id == $supplierId ? 'sent' : 'received';
            case 'supplier_rating':
                return $item->rater_supplier_id == $supplierId ? 'sent' : 'received';
            case 'review_reply':
                return $item->supplier_id == $supplierId ? 'sent' : 'received';
            default:
                return 'received';
        }
    }

    /**
     * Mark item as read
     */
    public function markAsRead(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:supplier_inquiry,supplier_to_supplier_inquiry,message,supplier_rating',
            'id' => 'required|integer',
        ]);

        $supplier = Auth::user();
        $type = $request->type;
        $id = $request->id;

        switch ($type) {
            case 'supplier_inquiry':
                $item = SupplierInquiry::where('id', $id)
                    ->where('supplier_id', $supplier->id)
                    ->first();
                if ($item && !$item->is_read) {
                    $item->update(['is_read' => true]);
                }
                break;

            case 'supplier_to_supplier_inquiry':
                $item = SupplierToSupplierInquiry::where('id', $id)
                    ->where('receiver_supplier_id', $supplier->id)
                    ->first();
                if ($item && !$item->is_read) {
                    $item->update(['is_read' => true]);
                }
                break;

            case 'message':
                $item = Message::where('id', $id)
                    ->where('receiver_supplier_id', $supplier->id)
                    ->first();
                if ($item && !$item->is_read) {
                    $item->update(['is_read' => true]);
                }
                break;

            case 'supplier_rating':
                $item = SupplierRating::where('id', $id)
                    ->where('rated_supplier_id', $supplier->id)
                    ->first();
                if ($item && !$item->is_read) {
                    $item->update(['is_read' => true]);
                }
                break;
        }

        return response()->json([
            'message' => 'Item marked as read successfully'
        ]);
    }

    /**
     * Unified reply endpoint for inquiries, messages, and reviews
     */
    public function reply(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:supplier_inquiry,supplier_to_supplier_inquiry,message,supplier_rating',
            'id' => 'required|integer',
            'reply' => 'required|string|max:2000',
        ]);

        $supplier = Auth::user();
        $type = $request->type;
        $id = $request->id;
        $replyText = $request->reply;

        switch ($type) {
            case 'supplier_inquiry':
                // Reply to admin inquiry (create a new inquiry as reply)
                $originalInquiry = SupplierInquiry::where('id', $id)
                    ->where(function($query) use ($supplier) {
                        $query->where('sender_id', $supplier->id)
                              ->orWhere('receiver_id', $supplier->id)
                              ->orWhereNull('receiver_id'); // General admin inquiries
                    })
                    ->firstOrFail();

                // Determine receiver: if original was from admin to supplier, reply to admin
                // If original was from supplier to admin, reply to the original sender
                $receiverId = null; // Default to admin pool
                if ($originalInquiry->sender_id && $originalInquiry->sender_id !== $supplier->id) {
                    // Original was from another supplier, reply to them
                    $receiverId = $originalInquiry->sender_id;
                }

                $reply = SupplierInquiry::create([
                    'sender_id' => $supplier->id,
                    'receiver_id' => $receiverId,
                    'supplier_id' => $supplier->id,
                    'full_name' => $supplier->name,
                    'email_address' => $supplier->email,
                    'phone_number' => $supplier->phone,
                    'subject' => $originalInquiry->subject,
                    'message' => $replyText,
                    'is_read' => false,
                    'type' => 'reply',
                    'from' => 'supplier',
                ]);

                // Mark original as read
                if (!$originalInquiry->is_read) {
                    $originalInquiry->update(['is_read' => true]);
                }

                return response()->json([
                    'message' => 'Reply sent successfully',
                    'data' => [
                        'type' => 'supplier_inquiry',
                        'id' => $reply->id,
                        'subject' => $reply->subject,
                        'message' => $reply->message,
                        'created_at' => $reply->created_at->format('Y-m-d H:i:s'),
                    ]
                ]);

            case 'supplier_to_supplier_inquiry':
                // Reply to supplier inquiry
                $originalInquiry = SupplierToSupplierInquiry::where('id', $id)
                    ->where(function($query) use ($supplier) {
                        $query->where('sender_supplier_id', $supplier->id)
                              ->orWhere('receiver_supplier_id', $supplier->id);
                    })
                    ->firstOrFail();

                // Create reply
                $reply = SupplierToSupplierInquiry::create([
                    'sender_supplier_id' => $supplier->id,
                    'receiver_supplier_id' => $originalInquiry->sender_supplier_id === $supplier->id 
                        ? $originalInquiry->receiver_supplier_id 
                        : $originalInquiry->sender_supplier_id,
                    'sender_name' => $supplier->name,
                    'email' => $supplier->email,
                    'phone' => $supplier->phone,
                    'company' => $supplier->profile->company_name ?? null,
                    'subject' => 'Re: ' . $originalInquiry->subject,
                    'message' => $replyText,
                    'parent_id' => $originalInquiry->id,
                    'is_read' => false,
                    'type' => 'reply'
                ]);

                // Mark original as read
                if (!$originalInquiry->is_read) {
                    $originalInquiry->update(['is_read' => true]);
                }

                return response()->json([
                    'message' => 'Reply sent successfully',
                    'data' => [
                        'type' => 'supplier_to_supplier_inquiry',
                        'id' => $reply->id,
                        'subject' => $reply->subject,
                        'message' => $reply->message,
                        'created_at' => $reply->created_at->format('Y-m-d H:i:s'),
                    ]
                ]);

            case 'message':
                // Reply to message (create new message)
                $originalMessage = Message::where('id', $id)
                    ->where(function($query) use ($supplier) {
                        $query->where('sender_supplier_id', $supplier->id)
                              ->orWhere('receiver_supplier_id', $supplier->id);
                    })
                    ->firstOrFail();

                // Get the original sender as receiver for the reply
                $replyReceiverSupplier = $originalMessage->sender_supplier_id === $supplier->id 
                    ? $originalMessage->receiver 
                    : $originalMessage->sender;

                // Get sender and receiver emails
                $senderEmail = $this->getSupplierEmail($supplier);
                $receiverEmail = $this->getSupplierEmail($replyReceiverSupplier);

                // Set subject without duplicating "Re:" prefix
                $subject = $originalMessage->subject;
                if (!str_starts_with($subject, 'Re: ')) {
                    $subject = 'Re: ' . $subject;
                }

                $reply = Message::create([
                    'sender_supplier_id' => $supplier->id,
                    'sender_email' => $senderEmail,
                    'receiver_supplier_id' => $replyReceiverSupplier->id,
                    'receiver_email' => $receiverEmail,
                    'subject' => $subject,
                    'message' => $replyText,
                    'is_read' => false,
                    'type' => 'message'
                ]);

                // Mark original as read
                if (!$originalMessage->is_read) {
                    $originalMessage->update(['is_read' => true]);
                }

                return response()->json([
                    'message' => 'Reply sent successfully',
                    'data' => [
                        'type' => 'message',
                        'id' => $reply->id,
                        'subject' => $reply->subject,
                        'message' => $reply->message,
                        'created_at' => $reply->created_at->format('Y-m-d H:i:s'),
                    ]
                ]);

            case 'supplier_rating':
                // Reply to review (only if supplier is being reviewed)
                $rating = SupplierRating::where('id', $id)
                    ->where('rated_supplier_id', $supplier->id)
                    ->firstOrFail();

                // Check if already replied
                if ($rating->reply) {
                    return response()->json([
                        'message' => 'You have already replied to this review.'
                    ], 422);
                }

                $reply = ReviewReply::create([
                    'supplier_rating_id' => $rating->id,
                    'supplier_id' => $supplier->id,
                    'reply' => $replyText,
                    'type' => 'reviewReply'
                ]);

                return response()->json([
                    'message' => 'Reply posted successfully',
                    'data' => [
                        'type' => 'review_reply',
                        'id' => $reply->id,
                        'reply' => $reply->reply,
                        'rating_id' => $rating->id,
                        'created_at' => $reply->created_at->format('Y-m-d H:i:s'),
                    ]
                ]);

            default:
                return response()->json([
                    'message' => 'Invalid type specified'
                ], 422);
        }
    }

    /**
     * Calculate average response time for a supplier
     */
    private function calculateAverageResponseTime($supplierId): string
    {
        $totalResponseTime = 0;
        $responsesCount = 0;

        // 1. Supplier-to-Supplier Inquiries
        $receivedInquiries = SupplierToSupplierInquiry::where('receiver_supplier_id', $supplierId)
            ->where('type', 'inquiry')
            ->whereNull('parent_id')
            ->get();

        foreach ($receivedInquiries as $inquiry) {
            $reply = SupplierToSupplierInquiry::where('parent_id', $inquiry->id)
                ->where('sender_supplier_id', $supplierId)
                ->first();

            if ($reply) {
                $responseTime = $inquiry->created_at->diffInSeconds($reply->created_at);
                $totalResponseTime += $responseTime;
                $responsesCount++;
            }
        }

        // 2. Messages
        $receivedMessages = Message::where('receiver_supplier_id', $supplierId)
            ->where('type', 'message')
            ->get();

        foreach ($receivedMessages as $message) {
            // Find reply message from this supplier to the original sender
            $reply = Message::where('subject', 'like', 'Re: ' . $message->subject)
                ->where('sender_supplier_id', $supplierId)
                ->where('receiver_supplier_id', $message->sender_supplier_id)
                ->where('created_at', '>', $message->created_at)
                ->first();

            if ($reply) {
                $responseTime = $message->created_at->diffInSeconds($reply->created_at);
                $totalResponseTime += $responseTime;
                $responsesCount++;
            }
        }

        // 3. Admin Inquiries
        $receivedAdminInquiries = SupplierInquiry::where('supplier_id', $supplierId)
            ->where('from', 'admin')
            ->get();

        foreach ($receivedAdminInquiries as $inquiry) {
            // Find reply from supplier to admin
            $reply = SupplierInquiry::where('subject', 'like', 'Re: ' . $inquiry->subject)
                ->where('supplier_id', $supplierId)
                ->where('from', 'supplier')
                ->where('created_at', '>', $inquiry->created_at)
                ->first();

            if ($reply) {
                $responseTime = $inquiry->created_at->diffInSeconds($reply->created_at);
                $totalResponseTime += $responseTime;
                $responsesCount++;
            }
        }

        // 4. Review Replies
        $receivedRatings = SupplierRating::where('rated_supplier_id', $supplierId)
            ->where('type', 'review')
            ->get();

        foreach ($receivedRatings as $rating) {
            if ($rating->reply) {
                $responseTime = $rating->created_at->diffInSeconds($rating->reply->created_at);
                $totalResponseTime += $responseTime;
                $responsesCount++;
            }
        }

        if ($responsesCount === 0) {
            return '0 seconds';
        }

        $avgSeconds = round($totalResponseTime / $responsesCount);
        
        if ($avgSeconds < 60) {
            return $avgSeconds . ' seconds';
        } elseif ($avgSeconds < 3600) {
            $minutes = round($avgSeconds / 60, 1);
            return $minutes . ' minutes';
        } elseif ($avgSeconds < 86400) {
            $hours = round($avgSeconds / 3600, 1);
            return $hours . ' hours';
        } else {
            $days = round($avgSeconds / 86400, 1);
            return $days . ' days';
        }
    }

    /**
     * Calculate response rate for a supplier
     */
    private function calculateResponseRate($supplierId): string
    {
        $totalReceived = 0;
        $respondedCount = 0;

        // 1. Supplier-to-Supplier Inquiries
        $totalInquiries = SupplierToSupplierInquiry::where('receiver_supplier_id', $supplierId)
            ->where('type', 'inquiry')
            ->whereNull('parent_id')
            ->count();

        if ($totalInquiries > 0) {
            $totalReceived += $totalInquiries;
            $respondedInquiries = SupplierToSupplierInquiry::where('receiver_supplier_id', $supplierId)
                ->where('type', 'inquiry')
                ->whereNull('parent_id')
                ->whereHas('replies', function($query) use ($supplierId) {
                    $query->where('sender_supplier_id', $supplierId);
                })
                ->count();
            $respondedCount += $respondedInquiries;
        }

        // 2. Messages
        $totalMessages = Message::where('receiver_supplier_id', $supplierId)
            ->where('type', 'message')
            ->count();

        if ($totalMessages > 0) {
            $totalReceived += $totalMessages;
            // For messages, we consider a response as sending a reply message to the original sender
            $respondedMessages = Message::where('receiver_supplier_id', $supplierId)
                ->where('type', 'message')
                ->whereExists(function($query) use ($supplierId) {
                    $query->select('id')
                        ->from('messages as replies')
                        ->whereColumn('replies.sender_supplier_id', 'messages.receiver_supplier_id')
                        ->whereColumn('replies.receiver_supplier_id', 'messages.sender_supplier_id')
                        ->where('replies.type', 'message')
                        ->where('replies.sender_supplier_id', $supplierId);
                })
                ->count();
            $respondedCount += $respondedMessages;
        }

        // 3. Supplier Inquiries (from admin)
        $totalAdminInquiries = SupplierInquiry::where('supplier_id', $supplierId)
            ->count();

        if ($totalAdminInquiries > 0) {
            $totalReceived += $totalAdminInquiries;
            // For admin inquiries, count those that have been responded to (have admin_id)
            $respondedAdminInquiries = SupplierInquiry::where('supplier_id', $supplierId)
                ->where('from', 'admin')
                ->whereNotNull('admin_id')
                ->count();
            $respondedCount += $respondedAdminInquiries;
        }

        // 4. Supplier Ratings (reviews)
        $totalRatings = SupplierRating::where('rated_supplier_id', $supplierId)
            ->where('type', 'review')
            ->count();

        if ($totalRatings > 0) {
            $totalReceived += $totalRatings;
            // For ratings, count those that have a reply
            $respondedRatings = SupplierRating::where('rated_supplier_id', $supplierId)
                ->where('type', 'review')
                ->whereHas('reply')
                ->count();
            $respondedCount += $respondedRatings;
        }

        if ($totalReceived === 0) {
            return '0%';
        }

        $rate = round(($respondedCount / $totalReceived) * 100, 1);
        
        return $rate . '%';
    }
}
