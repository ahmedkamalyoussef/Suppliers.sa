<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'message' => $this->message,
            'type' => $this->type,
            'is_read' => $this->is_read,
            'sender_email' => $this->sender_email,
            'receiver_email' => $this->receiver_email,
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
            ],
            'receiver' => [
                'id' => $this->receiver->id,
                'name' => $this->receiver->name,
            ],
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'time_ago' => $this->created_at->diffForHumans(),
        ];
    }
}
