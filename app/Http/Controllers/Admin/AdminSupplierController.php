<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSupplierStatusRequest;
use App\Http\Resources\Public\BranchResource;
use App\Models\Admin;
use App\Models\Supplier;
use App\Support\Constants;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminSupplierController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
        $this->middleware(function ($request, $next) {
            $user = $request->user();

            if (! $user instanceof Admin) {
                return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
            }

            $action = $request->route() ? $request->route()->getActionMethod() : null;

            if ($user->isSuperAdmin()) {
                return $next($request);
            }

            $user->loadMissing('permissions');
            $permissions = $user->permissions;

            if (! $permissions) {
                return response()->json(['message' => 'Unauthorized. Permission required.'], 403);
            }

            $requiresEdit = in_array($action, ['update', 'updateStatus'], true);
            $requiresDelete = $action === 'destroy';

            if ($requiresDelete && ! $user->hasPermission('user_management_delete') && ! $user->hasPermission('user_management_full')) {
                return response()->json(['message' => 'Unauthorized. Delete permission required.'], 403);
            }

            if ($requiresEdit && ! $user->hasPermission('user_management_edit') && ! $user->hasPermission('user_management_full')) {
                return response()->json(['message' => 'Unauthorized. Edit permission required.'], 403);
            }

            if (! $requiresEdit && ! $requiresDelete) {
                if (
                    ! $user->hasPermission('user_management_view') &&
                    ! $user->hasPermission('user_management_full')
                ) {
                    return response()->json(['message' => 'Unauthorized. View permission required.'], 403);
                }
            }

            return $next($request);
        });
    }

    public function index(Request $request): JsonResponse
    {
        $query = Supplier::query()->with(['profile', 'branches', 'approvedRatings']);

        if ($search = $request->query('search')) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('suppliers.name', 'like', '%'.$search.'%')
                    ->orWhere('suppliers.email', 'like', '%'.$search.'%')
                    ->orWhereHas('profile', function ($profileQuery) use ($search) {
                        $profileQuery->where('business_name', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($plan = $request->query('plan')) {
            $query->where('plan', $plan);
        }

        $query->withCount(['approvedRatings as approved_reviews_count']);
        $query->withAvg('approvedRatings as approved_reviews_avg', 'score');

        $perPage = (int) $request->query('perPage', 15);
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;

        $suppliers = $query->paginate($perPage);

        return response()->json([
            'users' => $suppliers->getCollection()
                ->map(fn (Supplier $supplier) => $this->formatSupplier($supplier))
                ->values()
                ->all(),
            'pagination' => [
                'currentPage' => $suppliers->currentPage(),
                'perPage' => $suppliers->perPage(),
                'total' => $suppliers->total(),
                'lastPage' => $suppliers->lastPage(),
            ],
        ]);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $supplier->load(['profile', 'branches', 'approvedRatings']);

        return response()->json([
            'user' => $this->formatSupplier($supplier, true),
        ]);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('suppliers', 'email')->ignore($supplier->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'plan' => ['sometimes', Rule::in(['Basic', 'Premium', 'Enterprise'])],
            'status' => ['sometimes', Rule::in(['active', 'pending', 'suspended', 'inactive'])],
            'businessName' => ['sometimes', 'string', 'max:255'],
            'businessType' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        if (array_key_exists('name', $validated)) {
            $supplier->name = $validated['name'];
        }

        if (array_key_exists('email', $validated)) {
            $supplier->email = $validated['email'];
        }

        if (array_key_exists('phone', $validated)) {
            $supplier->phone = $validated['phone'];
        }

        if (array_key_exists('plan', $validated)) {
            $supplier->plan = $validated['plan'];
        }

        if (array_key_exists('status', $validated)) {
            $supplier->status = $validated['status'];
        }

        $supplier->save();

        $profile = $supplier->profile ?: $supplier->profile()->create();
        $profileData = [];

        if ($request->has('businessName')) {
            $profileData['business_name'] = $request->input('businessName');
        }

        if ($request->has('businessType')) {
            $profileData['business_type'] = $request->input('businessType');
        }

        if (! empty($profileData)) {
            $profile->update($profileData);
        }

        $supplier->load(['profile', 'branches', 'approvedRatings']);

        return response()->json([
            'message' => 'Supplier updated successfully.',
        ]);
    }

    public function updateStatus(UpdateSupplierStatusRequest $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validated();

        $supplier->status = $validated['status'];
        $supplier->save();

        return response()->json([
            'message' => 'Supplier status updated.',
            'user' => $this->formatSupplier($supplier, false),
        ]);
    }

    public function addSupplier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:suppliers,email',
            'businessName' => 'required|string|max:255',
            'plan' => 'required|in:Basic,Premium,Enterprise',
            'status' => 'required|in:active,pending,suspended,inactive',
            'password' => 'required|string|min:6',
        ]);

        $supplier = Supplier::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'plan' => $validated['plan'],
            'status' => $validated['status'],
            'password' => bcrypt($validated['password']),
            'email_verified_at' => now(),
        ]);

        // Create profile with business name
        $supplier->profile()->create([
            'business_name' => $validated['businessName'],
        ]);

        return response()->json([
            'message' => 'Supplier created successfully.',
            
        ], 201);
    }

    public function export(): \Illuminate\Http\Response
    {
        $suppliers = Supplier::with(['profile', 'branches', 'approvedRatings'])
            ->get()
            ->map(function (Supplier $supplier) {
                $profile = $supplier->profile;
                return [
                    'ID' => $supplier->id,
                    'Name' => $supplier->name,
                    'Email' => $supplier->email,
                    'Phone' => $supplier->phone,
                    'Business Name' => $profile?->business_name ?? $supplier->name,
                    'Business Type' => $profile?->business_type ?? '',
                    'Plan' => ucfirst($supplier->plan ?? 'Basic'),
                    'Status' => $supplier->status ?? 'pending',
                    'Join Date' => optional($supplier->created_at)->format('Y-m-d'),
                    'Last Active' => optional($supplier->last_seen_at ?? $supplier->updated_at)->format('Y-m-d'),
                    'Rating' => $supplier->approvedRatings()->avg('score') ? round($supplier->approvedRatings()->avg('score'), 1) : 'N/A',
                    'Reviews Count' => $supplier->approvedRatings()->count(),
                    'Branches Count' => $supplier->branches()->count(),
                    'Profile Completion' => $this->calculateProfileCompletion($supplier) . '%',
                ];
            });

        $csv = $this->arrayToCsv($suppliers->toArray());

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="suppliers-' . date('Y-m-d') . '.csv"');
    }

    private function arrayToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');
        
        // Add header row
        fputcsv($output, array_keys($data[0]));
        
        // Add data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }

    public function destroy($id): JsonResponse
    {
        $supplier = Supplier::find($id);
        
        if (! $supplier) {
            return response()->json([
                'message' => 'Supplier not found.',
            ], 404);
        }
        
        $supplier->delete();

        return response()->json([
            'message' => 'Supplier deleted successfully.',
        ]);
    }

    private function formatSupplier(Supplier $supplier, bool $includeRelations = false): array
    {
        $supplier->loadMissing(['profile', 'branches', 'approvedRatings', 'inquiries']);
        $profile = $supplier->profile;

        $ratingAvg = $supplier->approvedRatings()->avg('score');
        $ratingCount = $supplier->approvedRatings()->count();

        $plan = $supplier->plan ?? 'Basic';
        $revenueEstimate = max(0, ($supplier->approvedRatings()->count() * 120) + ($supplier->inquiries()->count() * 45));
        $lastActive = $supplier->last_seen_at ?? $supplier->updated_at ?? $supplier->created_at;

        $base = [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'email' => $supplier->email,
            'businessName' => $profile?->business_name ?? $supplier->name,
            'plan' => ucfirst($plan),
            'status' => $supplier->status ?? Constants::SUPPLIER_STATUS_PENDING,
            'joinDate' => optional($supplier->created_at)->toIso8601String(),
            'lastActive' => optional($lastActive)->toIso8601String(),
            'revenue' => '$'.number_format($revenueEstimate),
            'profileCompletion' => $this->calculateProfileCompletion($supplier),
            'avatar' => \App\Support\Media::url($supplier->profile_image) ?? 'https://readdy.ai/api/search-image?query=Professional%20business%20avatar&width=120&height=120',
            'rating' => $ratingAvg ? round((float) $ratingAvg, 1) : null,
            'reviewsCount' => $ratingCount,
        ];

        if ($includeRelations) {
            $base['profile'] = $profile ? array_filter([
                'businessType' => $profile->business_type,
                'categories' => $profile->business_categories ?? [],
                'services' => $profile->services_offered ?? [],
                'description' => $profile->description,
                'website' => $profile->website,
                'address' => $profile->business_address,
                'contactEmail' => $profile->contact_email,
                'contactPhone' => $profile->main_phone,
            ]) : null;

            $base['branches'] = $supplier->branches->map(fn ($branch) => (new BranchResource($branch))->toArray(request()))->values();
        }

        return $base;
    }
}
