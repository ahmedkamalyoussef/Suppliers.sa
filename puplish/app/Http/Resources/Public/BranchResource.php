<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray($request): array
    {
        $branch = $this->resource;

        return array_filter([
            'id' => (string) $branch->id,
            'name' => $branch->name,
            'phone' => $branch->phone,
            'email' => $branch->email,
            'address' => $branch->address,
            'manager' => $branch->manager_name,
            'status' => $branch->status,
            'isMainBranch' => (bool) $branch->is_main_branch,
            'location' => ($branch->latitude || $branch->longitude) ? [
                'lat' => $branch->latitude ? (float) $branch->latitude : null,
                'lng' => $branch->longitude ? (float) $branch->longitude : null,
            ] : null,
            'workingHours' => $branch->working_hours ?? $this->defaultBranchHours(),
            'specialServices' => $branch->special_services ?? [],
            'createdAt' => optional($branch->created_at)->toIso8601String(),
            'updatedAt' => optional($branch->updated_at)->toIso8601String(),
        ], function ($value) {
            return $value !== null;
        });
    }

    private function defaultBranchHours(): array
    {
        // Mirror Controller::defaultBranchHours current behavior
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $hours = [];

        foreach ($days as $day) {
            $hours[$day] = [
                'open' => $day === 'sunday' ? '10:00' : '09:00',
                'close' => $day === 'sunday' ? '16:00' : '18:00',
                'closed' => $day === 'sunday',
            ];
        }

        return $hours;
    }
}
