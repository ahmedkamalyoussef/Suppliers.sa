<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierInquiry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PublicBusinessInquiryController extends Controller
{
    public function store(Request $request, string $slug)
    {
        $supplier = Supplier::whereHas('profile', fn (Builder $query) => $query->where('slug', $slug))->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string',
        ]);

        $inquiry = SupplierInquiry::create(array_merge($validated, [
            'supplier_id' => $supplier->id,
            'status' => 'pending',
            'is_unread' => true,
        ]));

        return response()->json([
            'message' => 'Your inquiry has been sent successfully.',
            'inquiryId' => $inquiry->id,
        ], 201);
    }
}
