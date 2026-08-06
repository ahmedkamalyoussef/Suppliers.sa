<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierRatingResource extends JsonResource
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
            'score' => $this->score,
            'comment' => $this->comment,
            'type' => $this->type,
            'is_approved' => $this->is_approved,
            'status' => $this->status,
            'rater' => [
                'id' => $this->rater->id,
                'name' => $this->rater->name,
            ],
            'rated' => [
                'id' => $this->rated->id,
                'name' => $this->rated->name,
            ],
            'reply' => $this->reply ? new ReviewReplyResource($this->reply) : null,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'time_ago' => $this->created_at->diffForHumans(),
        ];
    }
}
