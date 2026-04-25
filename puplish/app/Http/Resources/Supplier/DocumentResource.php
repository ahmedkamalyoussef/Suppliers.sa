<?php

namespace App\Http\Resources\Supplier;

use App\Support\Media;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray($request): array
    {
        $document = $this->resource;

        return array_filter([
            'id' => $document->id,
            'businessName' => $document->supplier?->profile?->business_name ?? $document->supplier?->name,
            'ownerName' => $document->supplier?->name,
            'fileUrl' => Media::url($document->file_path),
            'uploadDate' => optional($document->created_at)->toIso8601String(),
        ], function ($value) {
            return $value !== null;
        });
    }
}
