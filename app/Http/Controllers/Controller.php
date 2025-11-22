<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Branch;
use App\Models\ContentReport;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\SupplierInquiry;
use App\Models\SupplierRating;
use App\Support\BranchSupport;
use App\Support\Media;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

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
        $ratingAverage = $this->extractAggregate($supplier, ['rating_average', 'approved_ratings_avg_score', 'approved_ratings_avg']);
        $ratingCount = $this->extractAggregate($supplier, ['rating_count', 'approved_ratings_count']);

        return array_filter([
            'id' => $supplier->id,
            'slug' => $profile?->slug,
            'name' => $supplier->name,
            'email' => $supplier->email,
            'phone' => $supplier->phone,
            'profileImage' => $this->mediaUrl($supplier->profile_image),
            'emailVerifiedAt' => optional($supplier->email_verified_at)->toIso8601String(),
            'status' => $supplier->status,
            'plan' => $supplier->plan,
            'lastSeenAt' => optional($supplier->last_seen_at)->toIso8601String(),
            'profileCompletion' => $this->calculateProfileCompletion($supplier),
            'rating' => $ratingAverage !== null ? round((float) $ratingAverage, 2) : null,
            'reviewsCount' => $ratingCount !== null ? (int) $ratingCount : null,
            'profile' => $profile ? array_filter([
                'slug' => $profile->slug,
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
                'whoDoYouServe' => $profile->target_market ?? [],
                'productKeywords' => $profile->keywords ?? [],
                'serviceDistance' => $profile->service_distance !== null ? (string) $profile->service_distance : null,
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

    protected function transformSupplierSummary(Supplier $supplier): array
    {
        $supplier->loadMissing('profile');
        $profile = $supplier->profile;
        $ratingAverage = $this->extractAggregate($supplier, ['rating_average', 'approved_ratings_avg_score', 'approved_ratings_avg']);
        $ratingCount = $this->extractAggregate($supplier, ['rating_count', 'approved_ratings_count']);

        return array_filter([
            'id' => $supplier->id,
            'name' => $supplier->name,
            'profileImage' => $this->mediaUrl($supplier->profile_image),
            'slug' => $profile?->slug,
            'category' => $profile?->business_categories[0] ?? null,
            'categories' => $profile?->business_categories ?? [],
            'businessType' => $profile?->business_type,
            'address' => $profile?->business_address,
            'serviceDistance' => $profile?->service_distance !== null ? (float) $profile->service_distance : null,
            'rating' => $ratingAverage !== null ? round((float) $ratingAverage, 2) : null,
            'reviewsCount' => $ratingCount !== null ? (int) $ratingCount : null,
            'status' => $supplier->status,
            'plan' => $supplier->plan,
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
            'workingHours' => $branch->working_hours ?? $this->defaultBranchHours(),
            'specialServices' => $branch->special_services ?? [],
            'createdAt' => optional($branch->created_at)->toIso8601String(),
            'updatedAt' => optional($branch->updated_at)->toIso8601String(),
        ], function ($value) {
            return $value !== null;
        });
    }

    protected function transformRating(SupplierRating $rating): array
    {
        $rating->loadMissing(['rater', 'rated', 'moderatedBy', 'flaggedBy']);

        return array_filter([
            'id' => $rating->id,
            'score' => (int) $rating->score,
            'comment' => $rating->comment,
            'reviewerName' => $rating->reviewer_name,
            'reviewerEmail' => $rating->reviewer_email,
            'isApproved' => (bool) $rating->is_approved,
            'status' => $rating->status,
            'moderatedAt' => optional($rating->moderated_at)->toIso8601String(),
            'moderationNotes' => $rating->moderation_notes,
            'moderatedBy' => $rating->moderatedBy ? $this->transformAdmin($rating->moderatedBy) : null,
            'flaggedAt' => optional($rating->flagged_at)->toIso8601String(),
            'flaggedBy' => $rating->flaggedBy ? $this->transformAdmin($rating->flaggedBy) : null,
            'raterSupplier' => $rating->rater ? $this->transformSupplier($rating->rater, false) : null,
            'supplier' => $rating->rated ? $this->transformSupplier($rating->rated, false) : null,
            'createdAt' => optional($rating->created_at)->toIso8601String(),
            'businessName' => $rating->rated?->profile?->business_name ?? $rating->rated?->name,
            'customerName' => $rating->reviewer_name ?? optional($rating->rater)->name,
            'rating' => (int) $rating->score,
            'reviewText' => $rating->comment,
            'submissionDate' => optional($rating->created_at)->toIso8601String(),
            'flagged' => $rating->status === 'flagged',
        ], function ($value) {
            return $value !== null;
        });
    }

    protected function transformInquiry(SupplierInquiry $inquiry): array
    {
        $inquiry->loadMissing('handledBy');

        return array_filter([
            'id' => $inquiry->id,
            'from' => $inquiry->name,
            'company' => $inquiry->company,
            'subject' => $inquiry->subject,
            'message' => $inquiry->message,
            'contact' => $inquiry->email,
            'phone' => $inquiry->phone,
            'status' => $inquiry->status,
            'isUnread' => (bool) $inquiry->is_unread,
            'lastResponse' => $inquiry->last_response,
            'lastResponseAt' => optional($inquiry->last_response_at)->toIso8601String(),
            'handledBy' => $inquiry->handledBy ? $this->transformAdmin($inquiry->handledBy) : null,
            'handledAt' => optional($inquiry->handled_at)->toIso8601String(),
            'receivedAt' => optional($inquiry->created_at)->toIso8601String(),
        ], function ($value) {
            return $value !== null;
        });
    }

    protected function transformDocument(SupplierDocument $document): array
    {
        return array_filter([
            'id' => $document->id,
            'businessName' => $document->supplier?->profile?->business_name ?? $document->supplier?->name,
            'ownerName' => $document->supplier?->name,
            'fileUrl' => $this->mediaUrl($document->file_path),
            'uploadDate' => optional($document->created_at)->toIso8601String(),
        ], function ($value) {
            return $value !== null;
        });
    }

    protected function mediaUrl(?string $path): ?string
    {
        return Media::url($path);
    }

    protected function transformReport(ContentReport $report): array
    {
        $report->loadMissing(['targetSupplier.profile', 'reporter', 'handler']);

        $targetSupplier = $report->targetSupplier ? $this->transformSupplier($report->targetSupplier, false) : null;

        return array_filter([
            'id' => $report->id,
            'reportType' => $report->report_type,
            'type' => $report->report_type,
            'status' => $report->status,
            'reason' => $report->reason,
            'details' => $report->details,
            'content' => $report->details,
            'reportedByName' => $report->reported_by_name ?? optional($report->reporter)->name,
            'reportedByEmail' => $report->reported_by_email ?? optional($report->reporter)->email,
            'reportedBy' => $report->reported_by_name ?? optional($report->reporter)->name,
            'business' => $targetSupplier['profile']['businessName'] ?? $targetSupplier['name'] ?? null,
            'targetSupplier' => $targetSupplier,
            'reportDate' => optional($report->created_at)->toIso8601String(),
            'handledAt' => optional($report->handled_at)->toIso8601String(),
            'handledBy' => $report->handler ? $this->transformAdmin($report->handler) : null,
            'resolutionNotes' => $report->resolution_notes,
            'createdAt' => optional($report->created_at)->toIso8601String(),
        ], function ($value) {
            return $value !== null;
        });
    }

    protected function calculateProfileCompletion(Supplier $supplier): int
    {
        $supplier->loadMissing('profile', 'branches');
        $profile = $supplier->profile;

        $checks = [
            (bool) $supplier->phone,
            (bool) $profile?->business_name,
            (bool) $profile?->business_type,
            ! empty($profile?->business_categories),
            ! empty($profile?->services_offered),
            (bool) $profile?->description,
            (bool) $profile?->website,
            (bool) $profile?->business_address,
            ! empty($profile?->working_hours),
            ! empty($profile?->additional_phones),
            ! empty($profile?->keywords),
            (bool) $supplier->profile_image,
            $supplier->branches()->exists(),
        ];

        $total = count($checks);
        $completed = count(array_filter($checks));

        if ($total === 0) {
            return 0;
        }

        return (int) round(($completed / $total) * 100);
    }

    private function extractAggregate(object $model, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (isset($model->$key)) {
                return $model->$key;
            }
        }

        return null;
    }

    /**
     * @return array<string, array{open:string,close:string,closed:bool}>
     */
    protected function defaultBranchHours(): array
    {
        return BranchSupport::defaultHours();
    }
}
