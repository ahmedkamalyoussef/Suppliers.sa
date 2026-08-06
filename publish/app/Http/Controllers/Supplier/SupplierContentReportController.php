<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\ContentReport;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierContentReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $supplier = $this->resolveSupplier($request);

        $scope = $request->query('scope', 'received');

        $query = ContentReport::query()->with(['handler', 'reporter', 'targetSupplier.profile']);

        if ($scope === 'submitted') {
            $query->where('reported_by_supplier_id', $supplier->id);
        } else {
            $query->where('target_supplier_id', $supplier->id);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $reports = $query->latest()->get();

        return response()->json([
            'data' => $reports->map(fn (ContentReport $report) => $this->transformReport($report)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $supplier = $this->resolveSupplier($request);

        $validated = $request->validate([
            'reportType' => ['required', 'string', 'max:100'],
            'targetSupplierId' => ['nullable', 'different:reported_by_supplier_id', 'exists:suppliers,id'],
            'targetType' => ['nullable', 'string', 'max:100'],
            'targetId' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(['pending', 'pending_review'])],
        ]);

        $targetSupplierId = $validated['targetSupplierId'] ?? $supplier->id;

        $report = ContentReport::create([
            'target_supplier_id' => $targetSupplierId,
            'reported_by_supplier_id' => $supplier->id,
            'report_type' => $validated['reportType'],
            'target_type' => $validated['targetType'] ?? null,
            'target_id' => $validated['targetId'] ?? null,
            'status' => 'pending',
            'reason' => $validated['reason'] ?? null,
            'details' => $validated['details'] ?? null,
            'reported_by_name' => $supplier->name,
            'reported_by_email' => $supplier->email,
        ]);

        return response()->json([
            'message' => 'Report submitted successfully.',
            'data' => $this->transformReport($report),
        ], 201);
    }

    private function resolveSupplier(Request $request): Supplier
    {
        $user = $request->user();

        if (! $user instanceof Supplier) {
            abort(403, 'Only suppliers can access this resource.');
        }

        return $user;
    }
}
