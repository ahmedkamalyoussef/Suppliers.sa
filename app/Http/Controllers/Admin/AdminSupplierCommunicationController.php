<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\SupplierToSupplierInquiry;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AdminSupplierCommunicationController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            
            // Only allow admins to access this endpoint
            if (!$user || !($user instanceof \App\Models\Admin)) {
                return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
            }

            return $next($request);
        });
    }

    /**
     * Get all communications (inquiries and messages) between two suppliers
     */
    public function getCommunications(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier1_id' => 'required|integer|exists:suppliers,id',
            'supplier2_id' => 'required|integer|exists:suppliers,id|different:supplier1_id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        try {
            $supplier1Id = $request->input('supplier1_id');
            $supplier2Id = $request->input('supplier2_id');

            // Get supplier information
            $supplier1 = Supplier::find($supplier1Id);
            $supplier2 = Supplier::find($supplier2Id);

            // Get supplier-to-supplier inquiries between the two suppliers
            $inquiries = SupplierToSupplierInquiry::where(function($query) use ($supplier1Id, $supplier2Id) {
                    $query->where('sender_supplier_id', $supplier1Id)
                          ->where('receiver_supplier_id', $supplier2Id);
                })
                ->orWhere(function($query) use ($supplier1Id, $supplier2Id) {
                    $query->where('sender_supplier_id', $supplier2Id)
                          ->where('receiver_supplier_id', $supplier1Id);
                })
                ->with(['sender', 'receiver'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Get messages between the two suppliers
            $messages = Message::where(function($query) use ($supplier1Id, $supplier2Id) {
                    $query->where('sender_supplier_id', $supplier1Id)
                          ->where('receiver_supplier_id', $supplier2Id);
                })
                ->orWhere(function($query) use ($supplier1Id, $supplier2Id) {
                    $query->where('sender_supplier_id', $supplier2Id)
                          ->where('receiver_supplier_id', $supplier1Id);
                })
                ->with(['sender', 'receiver'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Transform inquiries data
            $transformedInquiries = $inquiries->map(function ($inquiry) {
                return [
                    'id' => $inquiry->id,
                    'type' => 'inquiry',
                    'sender_id' => $inquiry->sender_supplier_id,
                    'sender_name' => $inquiry->sender_name ?? ($inquiry->sender ? $inquiry->sender->name : 'Unknown'),
                    'sender_email' => $inquiry->email,
                    'sender_image' => $inquiry->sender ? ($inquiry->sender->profile_image ?: 'assets/images/default-supplier.png') : 'assets/images/default-supplier.png',
                    'receiver_id' => $inquiry->receiver_supplier_id,
                    'receiver_name' => $inquiry->receiver ? $inquiry->receiver->name : 'Unknown',
                    'receiver_email' => $inquiry->receiver ? $inquiry->receiver->email : null,
                    'receiver_image' => $inquiry->receiver ? ($inquiry->receiver->profile_image ?: 'assets/images/default-supplier.png') : 'assets/images/default-supplier.png',
                    'subject' => $inquiry->subject,
                    'message' => $inquiry->message,
                    'phone' => $inquiry->phone,
                    'company' => $inquiry->company,
                    'is_read' => $inquiry->is_read ?? false,
                    'read_at' => $inquiry->read_at,
                    'parent_id' => $inquiry->parent_id,
                    'inquiry_type' => $inquiry->type ?? 'inquiry',
                    'created_at' => $inquiry->created_at->toIso8601String(),
                    'updated_at' => $inquiry->updated_at->toIso8601String(),
                ];
            });

            // Transform messages data
            $transformedMessages = $messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'type' => 'message',
                    'sender_id' => $message->sender_supplier_id,
                    'sender_name' => $message->sender ? $message->sender->name : 'Unknown',
                    'sender_email' => $message->sender_email,
                    'sender_image' => $message->sender ? ($message->sender->profile_image ?: 'assets/images/default-supplier.png') : 'assets/images/default-supplier.png',
                    'receiver_id' => $message->receiver_supplier_id,
                    'receiver_name' => $message->receiver ? $message->receiver->name : 'Unknown',
                    'receiver_email' => $message->receiver_email,
                    'receiver_image' => $message->receiver ? ($message->receiver->profile_image ?: 'assets/images/default-supplier.png') : 'assets/images/default-supplier.png',
                    'subject' => $message->subject,
                    'message' => $message->message,
                    'message_type' => $message->type ?? 'message',
                    'is_read' => $message->is_read,
                    'created_at' => $message->created_at->toIso8601String(),
                    'updated_at' => $message->updated_at->toIso8601String(),
                ];
            });

            // Combine all communications and sort by date
            $allCommunications = $transformedInquiries->concat($transformedMessages)
                ->sortByDesc('created_at')
                ->values()
                ->all();

            // Get statistics
            $statistics = [
                'total_communications' => count($allCommunications),
                'total_inquiries' => $transformedInquiries->count(),
                'total_messages' => $transformedMessages->count(),
                'unread_count' => $transformedInquiries->where('is_read', false)->count() + 
                                $transformedMessages->where('is_read', false)->count(),
                'last_communication' => count($allCommunications) > 0 ? $allCommunications[0]['created_at'] : null,
            ];

            // Log the access
            Log::info('Admin accessed supplier communications', [
                'admin_id' => auth()->id(),
                'supplier1_id' => $supplier1Id,
                'supplier2_id' => $supplier2Id,
                'total_communications' => $statistics['total_communications'],
                'accessed_at' => now()
            ]);

            return response()->json([
                'message' => 'Communications retrieved successfully',
                'suppliers' => [
                    'supplier1' => [
                        'id' => $supplier1->id,
                        'name' => $supplier1->name,
                        'email' => $supplier1->email,
                        'profile_image' => $supplier1->profile_image ?: 'assets/images/default-supplier.png',
                    ],
                    'supplier2' => [
                        'id' => $supplier2->id,
                        'name' => $supplier2->name,
                        'email' => $supplier2->email,
                        'profile_image' => $supplier2->profile_image ?: 'assets/images/default-supplier.png',
                    ]
                ],
                'statistics' => $statistics,
                'communications' => $allCommunications,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get supplier communications', [
                'admin_id' => auth()->id(),
                'supplier1_id' => $request->input('supplier1_id'),
                'supplier2_id' => $request->input('supplier2_id'),
                'error' => $e->getMessage(),
                'failed_at' => now()
            ]);

            return response()->json([
                'message' => 'Failed to retrieve communications: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get communication summary between two suppliers
     */
    public function getCommunicationSummary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier1_id' => 'required|integer|exists:suppliers,id',
            'supplier2_id' => 'required|integer|exists:suppliers,id|different:supplier1_id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        try {
            $supplier1Id = $request->input('supplier1_id');
            $supplier2Id = $request->input('supplier2_id');

            // Count inquiries
            $inquiryCount = SupplierToSupplierInquiry::where(function($query) use ($supplier1Id, $supplier2Id) {
                    $query->where('sender_supplier_id', $supplier1Id)
                          ->where('receiver_supplier_id', $supplier2Id);
                })
                ->orWhere(function($query) use ($supplier1Id, $supplier2Id) {
                    $query->where('sender_supplier_id', $supplier2Id)
                          ->where('receiver_supplier_id', $supplier1Id);
                })
                ->count();

            // Count messages
            $messageCount = Message::where(function($query) use ($supplier1Id, $supplier2Id) {
                    $query->where('sender_supplier_id', $supplier1Id)
                          ->where('receiver_supplier_id', $supplier2Id);
                })
                ->orWhere(function($query) use ($supplier1Id, $supplier2Id) {
                    $query->where('sender_supplier_id', $supplier2Id)
                          ->where('receiver_supplier_id', $supplier1Id);
                })
                ->count();

            // Get last communication date
            $lastInquiry = SupplierToSupplierInquiry::where(function($query) use ($supplier1Id, $supplier2Id) {
                    $query->where('sender_supplier_id', $supplier1Id)
                          ->where('receiver_supplier_id', $supplier2Id);
                })
                ->orWhere(function($query) use ($supplier1Id, $supplier2Id) {
                    $query->where('sender_supplier_id', $supplier2Id)
                          ->where('receiver_supplier_id', $supplier1Id);
                })
                ->orderBy('created_at', 'desc')
                ->first();

            $lastMessage = Message::where(function($query) use ($supplier1Id, $supplier2Id) {
                    $query->where('sender_supplier_id', $supplier1Id)
                          ->where('receiver_supplier_id', $supplier2Id);
                })
                ->orWhere(function($query) use ($supplier1Id, $supplier2Id) {
                    $query->where('sender_supplier_id', $supplier2Id)
                          ->where('receiver_supplier_id', $supplier1Id);
                })
                ->orderBy('created_at', 'desc')
                ->first();

            $lastCommunication = null;
            if ($lastInquiry && $lastMessage) {
                $lastCommunication = $lastInquiry->created_at > $lastMessage->created_at 
                    ? $lastInquiry->created_at 
                    : $lastMessage->created_at;
            } elseif ($lastInquiry) {
                $lastCommunication = $lastInquiry->created_at;
            } elseif ($lastMessage) {
                $lastCommunication = $lastMessage->created_at;
            }

            return response()->json([
                'message' => 'Communication summary retrieved successfully',
                'supplier1_id' => $supplier1Id,
                'supplier2_id' => $supplier2Id,
                'summary' => [
                    'total_inquiries' => $inquiryCount,
                    'total_messages' => $messageCount,
                    'total_communications' => $inquiryCount + $messageCount,
                    'last_communication_at' => $lastCommunication ? $lastCommunication->toIso8601String() : null,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get communication summary', [
                'admin_id' => auth()->id(),
                'supplier1_id' => $request->input('supplier1_id'),
                'supplier2_id' => $request->input('supplier2_id'),
                'error' => $e->getMessage(),
                'failed_at' => now()
            ]);

            return response()->json([
                'message' => 'Failed to retrieve communication summary: ' . $e->getMessage()
            ], 500);
        }
    }
}
