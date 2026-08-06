<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
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

        // Check if user is logged in (has auth token)
        $isGuest = !auth()->check();
        $senderId = $isGuest ? null : auth()->id();

        $inquiry = SupplierInquiry::create(array_merge($validated, [
            'supplier_id' => $supplier->id,
            'receiver_id' => $supplier->id,
            'sender_id' => $senderId,
            'is_guest' => $isGuest,
            'from' => 'public',
            'full_name' => $validated['name'],
            'email_address' => $validated['email'],
            'phone_number' => $validated['phone'],
        ]));

        return response()->json([
            'message' => 'Your inquiry has been sent successfully.',
            'inquiryId' => $inquiry->id,
            'isGuest' => $isGuest,
        ], 201);
    }
}
