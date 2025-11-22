<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ContentReport;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PublicContentReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'businessSlug' => ['nullable', 'string', 'max:255'],
            'targetSupplierId' => ['nullable', 'exists:suppliers,id'],
            'reportType' => ['required', 'string', 'max:100'],
            'targetType' => ['nullable', 'string', 'max:100'],
            'targetId' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:2000'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $validator->after(function ($validator) use ($request) {
            if (! $request->filled('businessSlug') && ! $request->filled('targetSupplierId')) {
                $validator->errors()->add('businessSlug', 'A business slug or supplier ID is required.');
            }
        });

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $supplier = null;
        if ($request->filled('targetSupplierId')) {
            $supplier = Supplier::find($request->input('targetSupplierId'));
        } elseif ($request->filled('businessSlug')) {
            $supplier = Supplier::whereHas('profile', function (Builder $query) use ($request) {
                $query->where('slug', $request->input('businessSlug'));
            })->first();
        }

        if (! $supplier) {
            return response()->json(['message' => 'Unable to locate the specified business.'], 404);
        }

        $report = ContentReport::create([
            'target_supplier_id' => $supplier->id,
            'report_type' => $request->input('reportType'),
            'target_type' => $request->input('targetType'),
            'target_id' => $request->input('targetId'),
            'status' => 'pending',
            'reason' => $request->input('reason'),
            'details' => $request->input('details'),
            'reported_by_name' => $request->input('name'),
            'reported_by_email' => $request->input('email'),
        ]);

        return response()->json([
            'message' => 'Your report has been submitted and will be reviewed shortly.',
            'data' => $this->transformReport($report),
        ], 201);
    }
}
