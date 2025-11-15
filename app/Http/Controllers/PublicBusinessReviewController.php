<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierRating;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PublicBusinessReviewController extends Controller
{
    public function store(Request $request, string $slug)
    {
        $supplier = Supplier::whereHas('profile', fn (Builder $query) => $query->where('slug', $slug))->firstOrFail();

        $validator = Validator::make($request->all(), [
            'score' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $rating = SupplierRating::create([
            'rater_supplier_id' => null,
            'rated_supplier_id' => $supplier->id,
            'score' => $request->score,
            'comment' => $request->comment,
            'reviewer_name' => $request->input('name'),
            'reviewer_email' => $request->input('email'),
            'is_approved' => false,
            'status' => 'pending_review',
        ]);

        return response()->json([
            'message' => 'Thank you! Your review is pending approval.',
            'rating' => $this->transformRating($rating),
        ], 201);
    }
}
