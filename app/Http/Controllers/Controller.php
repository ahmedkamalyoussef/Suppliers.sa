<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Supplier;
use App\Models\SupplierRating;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function transformAdmin(Admin $admin): array
    {
        $admin->loadMissing('permissions');

        $permissions = $admin->permissions ? [
            'userManagement' => [
                'view' => (bool) $admin->permissions->user_management_view,
                'edit' => (bool) $admin->permissions->user_management_edit,
                'delete' => (bool) $admin->permissions->user_management_delete,
                'full' => (bool) $admin->permissions->user_management_full,
            ],
            'contentManagement' => [
                'view' => (bool) $admin->permissions->content_management_view,
                'supervise' => (bool) $admin->permissions->content_management_supervise,
                'delete' => (bool) $admin->permissions->content_management_delete,
            ],
            'analytics' => [
                'view' => (bool) $admin->permissions->analytics_view,
                'export' => (bool) $admin->permissions->analytics_export,
            ],
            'reports' => [
                'view' => (bool) $admin->permissions->reports_view,
                'create' => (bool) $admin->permissions->reports_create,
            ],
            'system' => [
                'manage' => (bool) $admin->permissions->system_manage,
                'settings' => (bool) $admin->permissions->system_settings,
                'backups' => (bool) $admin->permissions->system_backups,
            ],
            'support' => [
                'manage' => (bool) $admin->permissions->support_manage,
            ],
        ] : null;

        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => $admin->role,
            'department' => $admin->department,
            'jobRole' => $admin->job_role,
            'profileImage' => $this->mediaUrl($admin->profile_image),
            'emailVerifiedAt' => optional($admin->email_verified_at)->toIso8601String(),
            'permissions' => $permissions,
        ];
    }

    protected function transformSupplier(Supplier $supplier, bool $withRelations = true): array
    {
        if ($withRelations) {
            $supplier->loadMissing(['profile', 'branches']);
        } else {
            $supplier->loadMissing(['profile']);
        }

        $profile = $supplier->profile;

        return array_filter([
            'id' => $supplier->id,
            'name' => $supplier->name,
            'email' => $supplier->email,
            'phone' => $supplier->phone,
            'profileImage' => $this->mediaUrl($supplier->profile_image),
            'emailVerifiedAt' => optional($supplier->email_verified_at)->toIso8601String(),
            'profile' => $profile ? array_filter([
                'businessName' => $profile->business_name,
                'businessType' => $profile->business_type,
                'category' => $profile->business_categories[0] ?? null,
                'categories' => $profile->business_categories ?? [],
                'description' => $profile->description,
                'services' => $profile->services_offered ?? [],
                'website' => $profile->website,
                'address' => $profile->business_address,
                'mainPhone' => $profile->main_phone,
                'contactEmail' => $profile->contact_email,
                'targetCustomers' => $profile->target_market ?? [],
                'productKeywords' => $profile->keywords ?? [],
                'serviceDistance' => $profile->service_distance !== null ? (float) $profile->service_distance : null,
                'additionalPhones' => $profile->additional_phones ?? [],
                'workingHours' => $profile->working_hours ?? [],
                'hasBranches' => (bool) $profile->has_branches,
                'location' => ($profile->latitude || $profile->longitude) ? [
                    'lat' => $profile->latitude ? (float) $profile->latitude : null,
                    'lng' => $profile->longitude ? (float) $profile->longitude : null,
                ] : null,
            ]) : null,
            'branches' => $withRelations ? $supplier->branches->map(fn (Branch $branch) => $this->transformBranch($branch))->toArray() : null,
        ], function ($value) {
            return $value !== null;
        });
    }

    protected function transformBranch(Branch $branch): array
    {
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
            'workingHours' => $branch->working_hours ?? [],
            'specialServices' => $branch->special_services ?? [],
            'createdAt' => optional($branch->created_at)->toIso8601String(),
            'updatedAt' => optional($branch->updated_at)->toIso8601String(),
        ], function ($value) {
            return $value !== null;
        });
    }

    protected function transformRating(SupplierRating $rating): array
    {
        $rating->loadMissing(['rater', 'rated']);

        return [
            'id' => $rating->id,
            'score' => (int) $rating->score,
            'comment' => $rating->comment,
            'isApproved' => (bool) $rating->is_approved,
            'raterSupplier' => $rating->rater ? $this->transformSupplier($rating->rater, false) : null,
            'ratedSupplier' => $rating->rated ? $this->transformSupplier($rating->rated, false) : null,
            'createdAt' => optional($rating->created_at)->toIso8601String(),
        ];
    }

    protected function mediaUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return URL::to($path);
    }
}
