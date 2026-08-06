<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierViewController extends Controller
{
    public function view(Request $request, Supplier $supplier): JsonResponse
    {
        // Don't count views from the supplier themselves
        $authUser = $request->user();
        if ($authUser && $authUser->id === $supplier->id) {
            return response()->json([
                'success' => true,
                'message' => 'View recorded (self-view not counted)'
            ]);
        }

        // Increment profile views counter
        if ($supplier->profile) {
            $supplier->profile->increment('profile_views');
        } else {
            // Create profile if it doesn't exist
            $supplier->profile()->create([
                'profile_views' => 1,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'View recorded successfully',
            'total_views' => $supplier->fresh()->profile?->profile_views ?? 1
        ]);
    }
}
