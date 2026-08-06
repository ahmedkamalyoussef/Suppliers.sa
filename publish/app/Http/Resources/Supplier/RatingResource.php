<?php

namespace App\Http\Resources\Supplier;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RatingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rating = $this->resource;

        return array_filter([
            'id' => $rating->id,
            'score' => $rating->score,
            'comment' => $rating->comment,
            'raterName' => $rating->rater_name,
            'raterEmail' => $rating->rater_email,
            'raterPhone' => $rating->rater_phone,
            'status' => $rating->status,
            'createdAt' => $rating->created_at?->toIso8601String(),
            'updatedAt' => $rating->updated_at?->toIso8601String(),
            'moderatedBy' => $rating->moderatedBy ? [
                'id' => $rating->moderatedBy->id,
                'name' => $rating->moderatedBy->name,
                'email' => $rating->moderatedBy->email,
            ] : null,
            'flaggedBy' => $rating->flaggedBy ? [
                'id' => $rating->flaggedBy->id,
                'name' => $rating->flaggedBy->name,
                'email' => $rating->flaggedBy->email,
            ] : null,
            'raterSupplier' => $rating->rater ? (new \App\Http\Resources\SupplierResource($rating->rater))->toArray($request) : null,
            'supplier' => $rating->rated ? (new \App\Http\Resources\SupplierResource($rating->rated))->toArray($request) : null,
        ], function ($value) {
            return $value !== null;
        });
    }
}
