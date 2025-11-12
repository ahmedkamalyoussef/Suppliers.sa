<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Supplier;
use App\Models\SupplierRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplierRatingController extends Controller
{
    /**
     * Store a newly created resource in storage.
     * Supplier can rate another supplier 1..5
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!($user instanceof Supplier)) {
            return response()->json(['message' => 'Only suppliers can create ratings'], 403);
        }

        if (!$request->has('rated_supplier_id') && $request->filled('ratedSupplierId')) {
            $request->merge(['rated_supplier_id' => $request->input('ratedSupplierId')]);
        }

        $validator = Validator::make($request->all(), [
            'rated_supplier_id' => 'required|exists:suppliers,id',
            'score' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        if ((int) $request->rated_supplier_id === (int) $user->id) {
            $validator->after(function ($v) {
                $v->errors()->add('rated_supplier_id', 'You cannot rate yourself');
            });
        }

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $rating = SupplierRating::updateOrCreate(
            [
                'rater_supplier_id' => $user->id,
                'rated_supplier_id' => $request->rated_supplier_id,
            ],
            [
                'score' => $request->score,
                'comment' => $request->comment,
                'is_approved' => false,
            ]
        );

        return response()->json([
            'message' => 'Rating submitted and awaiting approval',
            'rating' => $this->transformRating($rating),
        ], 201);
    }

    /**
     * Approve a rating (super admin or admins with content_management_supervise)
     */
    public function approve(Request $request, SupplierRating $rating)
    {
        $user = $request->user();

        // Super admin or admin with supervise permission
        if ($user instanceof Admin) {
            if ($user->isSuperAdmin() || $user->hasPermission('content_management_supervise')) {
                $rating->is_approved = true;
                $rating->save();
                return response()->json(['message' => 'Rating approved', 'rating' => $this->transformRating($rating)]);
            }
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }
}
