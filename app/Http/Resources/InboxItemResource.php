<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InboxItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Base structure for all items
        $baseData = [
            'id' => $this->id,
            'type' => $this->resource_type,
            'subject' => $this->getSubject(),
            'message' => $this->getMessage(),
            'is_read' => $this->isRead(),
            'created_at' => $this->getCreatedAt(),
            'time_ago' => $this->getTimeAgo(),
            'direction' => $this->getDirection(), // 'sent' or 'received'
        ];

        // Add specific data based on type
        switch ($this->resource_type) {
            case 'supplier_inquiry':
                return array_merge($baseData, [
                    'sender' => $this->getSender(),
                    'receiver' => $this->getReceiver(),
                    'inquiry_type' => 'admin',
                ]);

            case 'supplier_to_supplier_inquiry':
                return array_merge($baseData, [
                    'sender' => $this->getSender(),
                    'receiver' => $this->getReceiver(),
                    'inquiry_type' => 'supplier',
                    'is_reply' => (bool) $this->parent_id,
                ]);

            case 'message':
                return array_merge($baseData, [
                    'sender' => $this->getSender(),
                    'receiver' => $this->getReceiver(),
                    'sender_email' => $this->sender_email,
                    'receiver_email' => $this->receiver_email,
                ]);

            case 'supplier_rating':
                return array_merge($baseData, [
                    'sender' => $this->getRater(),
                    'receiver' => $this->getRated(),
                    'score' => $this->score,
                    'rating_type' => 'review',
                    'has_reply' => $this->hasReply(),
                ]);

            case 'review_reply':
                return array_merge($baseData, [
                    'sender' => $this->getSupplier(),
                    'receiver' => $this->getRatingRater(),
                    'reply_type' => 'review_reply',
                    'rating_id' => $this->supplier_rating_id,
                ]);

            default:
                return $baseData;
        }
    }

    // Helper methods
    private function getSubject()
    {
        switch ($this->resource_type) {
            case 'supplier_inquiry':
                return $this->subject ?? 'Inquiry from Admin';
            case 'supplier_to_supplier_inquiry':
                return $this->subject;
            case 'message':
                return $this->subject;
            case 'supplier_rating':
                return 'Review from ' . ($this->rater->name ?? 'Unknown');
            case 'review_reply':
                return 'Re: Review Reply';
            default:
                return 'Unknown';
        }
    }

    private function getMessage()
    {
        switch ($this->resource_type) {
            case 'supplier_inquiry':
                return $this->message;
            case 'supplier_to_supplier_inquiry':
                return $this->message;
            case 'message':
                return $this->message;
            case 'supplier_rating':
                return $this->comment;
            case 'review_reply':
                return $this->reply;
            default:
                return '';
        }
    }

    private function isRead()
    {
        switch ($this->resource_type) {
            case 'supplier_inquiry':
                return $this->is_read ?? false;
            case 'supplier_to_supplier_inquiry':
                return $this->is_read;
            case 'message':
                return $this->is_read;
            case 'supplier_rating':
                return $this->is_read;
            case 'review_reply':
                return true; // Replies are considered read by default
            default:
                return false;
        }
    }

    private function getCreatedAt()
    {
        return $this->created_at->format('Y-m-d H:i:s');
    }

    private function getTimeAgo()
    {
        return $this->created_at->diffForHumans();
    }

    private function getDirection()
    {
        $supplierId = auth()->id();
        
        switch ($this->resource_type) {
            case 'supplier_inquiry':
                return $this->sender_id == $supplierId ? 'sent' : 'received';
            case 'supplier_to_supplier_inquiry':
                return $this->sender_supplier_id == $supplierId ? 'sent' : 'received';
            case 'message':
                return $this->sender_supplier_id == $supplierId ? 'sent' : 'received';
            case 'supplier_rating':
                return $this->rater_supplier_id == $supplierId ? 'sent' : 'received';
            case 'review_reply':
                return $this->supplier_id == $supplierId ? 'sent' : 'received';
            default:
                return 'received';
        }
    }

    private function getSender()
    {
        switch ($this->resource_type) {
            case 'supplier_inquiry':
                if ($this->sender) {
                    return [
                        'id' => $this->sender->id,
                        'name' => $this->sender->name,
                    ];
                }
                // If sender_id is null, it's from admin
                return [
                    'id' => null,
                    'name' => 'Admin',
                ];
            case 'supplier_to_supplier_inquiry':
                return [
                    'id' => $this->sender_supplier_id,
                    'name' => $this->sender_name ?? 'Unknown',
                ];
            case 'message':
                if (!$this->sender) {
                    return ['id' => null, 'name' => 'Unknown'];
                }
                return [
                    'id' => $this->sender->id,
                    'name' => $this->sender->name ?? 'Unknown',
                ];
            default:
                return null;
        }
    }

    private function getReceiver()
    {
        switch ($this->resource_type) {
            case 'supplier_inquiry':
                if ($this->receiver) {
                    return [
                        'id' => $this->receiver->id,
                        'name' => $this->receiver->name,
                    ];
                }
                // If receiver_id is null, it's sent to admin
                return [
                    'id' => null,
                    'name' => 'Admin',
                ];
            case 'supplier_to_supplier_inquiry':
                if (!$this->receiver) {
                    return ['id' => null, 'name' => 'Unknown'];
                }
                return [
                    'id' => $this->receiver->id,
                    'name' => $this->receiver->name ?? 'Unknown',
                ];
            case 'message':
                if (!$this->receiver) {
                    return ['id' => null, 'name' => 'Unknown'];
                }
                return [
                    'id' => $this->receiver->id,
                    'name' => $this->receiver->name ?? 'Unknown',
                ];
            default:
                return null;
        }
    }

    private function getRater()
    {
        if (!$this->rater) {
            return [
                'id' => null,
                'name' => 'Unknown',
            ];
        }
        
        return [
            'id' => $this->rater->id,
            'name' => $this->rater->name ?? 'Unknown',
        ];
    }

    private function getRated()
    {
        if (!$this->rated) {
            return [
                'id' => null,
                'name' => 'Unknown',
            ];
        }
        
        return [
            'id' => $this->rated->id,
            'name' => $this->rated->name ?? 'Unknown',
        ];
    }

    private function getSupplier()
    {
        if (!$this->supplier) {
            return [
                'id' => null,
                'name' => 'Unknown',
            ];
        }
        
        return [
            'id' => $this->supplier->id,
            'name' => $this->supplier->name ?? 'Unknown',
        ];
    }

    private function getRatingRater()
    {
        if (!$this->rating || !$this->rating->rater) {
            return [
                'id' => null,
                'name' => 'Unknown',
            ];
        }
        
        return [
            'id' => $this->rating->rater->id,
            'name' => $this->rating->rater->name ?? 'Unknown',
        ];
    }

    private function hasReply()
    {
        return isset($this->reply) && $this->reply !== null;
    }
}
