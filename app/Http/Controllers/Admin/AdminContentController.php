<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\ContentReport;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\SupplierRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminContentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware(function ($request, $next) {
            $user = $request->user();

            if (! $user instanceof Admin) {
                return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
            }

            if ($user->isSuperAdmin()) {
                return $next($request);
            }

            $user->loadMissing('permissions');
            if (! $user->permissions || ! $user->hasPermission('content_management_supervise')) {
                return response()->json(['message' => 'Unauthorized. Content supervision permission required.'], 403);
            }

            return $next($request);
        });
    }

    public function index(Request $request): JsonResponse
    {
        $businesses = $this->businessListings();
        $pendingReviews = $this->pendingReviews();
        $documentVerifications = $this->documentVerifications();
        $reportedContent = $this->reportedContent();

        return response()->json([
            'businesses' => $businesses,
            'pendingReviews' => $pendingReviews,
            'documentVerifications' => $documentVerifications,
            'reportedContent' => $reportedContent,
        ]);
    }

    private function businessListings(): array
    {
        return Supplier::with(['profile', 'documents' => function ($query) {
            $query->latest();
        }])
            ->latest()
            ->take(25)
            ->get()
            ->map(function (Supplier $supplier) {
                $profile = $supplier->profile;
                $documentStatus = optional($supplier->documents->first())->status ?? 'pending_verification';

                return [
                    'id' => (string) $supplier->id,
                    'name' => $profile?->business_name ?? $supplier->name,
                    'owner' => $supplier->name,
                    'category' => $profile?->business_categories[0] ?? 'General',
                    'status' => $this->mapBusinessStatus($supplier->status),
                    'crStatus' => $this->mapDocumentStatus($documentStatus),
                    'views' => (int) ($profile?->profile_views ?? random_int(120, 980)),
                    'createdDate' => optional($supplier->created_at)->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    private function pendingReviews(): array
    {
        return SupplierRating::with(['rated.profile', 'rater'])
            ->latest()
            ->take(25)
            ->get()
            ->map(function (SupplierRating $rating) {
                return [
                    'id' => $rating->id,
                    'businessName' => $rating->rated?->profile?->business_name ?? $rating->rated?->name,
                    'customerName' => $rating->reviewer_name ?? optional($rating->rater)->name,
                    'rating' => (int) $rating->score,
                    'reviewText' => $rating->comment,
                    'submissionDate' => optional($rating->created_at)->toIso8601String(),
                    'status' => $rating->status ?? 'pending_review',
                    'flagged' => $rating->status === 'flagged',
                ];
            })
            ->values()
            ->all();
    }

    private function documentVerifications(): array
    {
        return SupplierDocument::with(['supplier.profile', 'reviewer'])
            ->latest()
            ->take(25)
            ->get()
            ->map(function (SupplierDocument $document) {
                return [
                    'id' => $document->id,
                    'businessName' => $document->supplier?->profile?->business_name ?? $document->supplier?->name,
                    'ownerName' => $document->supplier?->name,
                    'documentType' => $document->document_type,
                    'crNumber' => $document->reference_number,
                    'uploadDate' => optional($document->created_at)->toIso8601String(),
                    'issueDate' => optional($document->issue_date)->toDateString(),
                    'expiryDate' => optional($document->expiry_date)->toDateString(),
                    'status' => $document->status,
                    'reviewer' => optional($document->reviewer)->name,
                    'notes' => $document->notes,
                ];
            })
            ->values()
            ->all();
    }

    private function reportedContent(): array
    {
        return ContentReport::with(['targetSupplier.profile', 'reporter', 'handler'])
            ->latest()
            ->take(25)
            ->get()
            ->map(function (ContentReport $report) {
                $businessName = $report->targetSupplier?->profile?->business_name ?? $report->targetSupplier?->name;
                $reportedBy = $report->reported_by_name ?? optional($report->reporter)->name;

                return [
                    'id' => $report->id,
                    'business' => $businessName,
                    'type' => $report->report_type,
                    'reportedBy' => $reportedBy,
                    'reportDate' => optional($report->created_at)->toIso8601String(),
                    'reason' => $report->reason,
                    'content' => $report->details,
                    'status' => $report->status,
                ];
            })
            ->values()
            ->all();
    }

    private function mapBusinessStatus(?string $status): string
    {
        return match ($status) {
            'active' => 'approved',
            'pending' => 'pending_verification',
            'suspended', 'inactive' => 'flagged',
            default => 'pending_verification',
        };
    }

    private function mapDocumentStatus(?string $status): string
    {
        return match ($status) {
            'verified' => 'verified',
            'rejected' => 'rejected',
            'flagged' => 'flagged',
            default => 'pending_verification',
        };
    }
}
