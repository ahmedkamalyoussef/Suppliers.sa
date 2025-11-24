<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'profile_image' => $this->profile_image ? asset($this->profile_image) : null,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'profile' => $this->whenLoaded('profile', function () {
                return [
                    'business_name' => $this->profile->business_name ?? null,
                    'business_type' => $this->profile->business_type ?? null,
                    'description' => $this->profile->description ?? null,
                    'website' => $this->profile->website ?? null,
                    'main_phone' => $this->profile->main_phone ?? null,
                    'business_address' => $this->profile->business_address ?? null,
                    'latitude' => $this->profile->latitude ?? null,
                    'longitude' => $this->profile->longitude ?? null,
                ];
            }),
        ];
    }
}
