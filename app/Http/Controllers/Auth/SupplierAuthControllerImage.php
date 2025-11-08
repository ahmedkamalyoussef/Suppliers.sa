<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SupplierAuthControllerImage extends Controller
{
    /**
     * Handle supplier profile image upload and store under public/uploads/suppliers
     */
    public function updateProfileImage(Request $request)
    {
        $request->validate([
            'profile_image' => 'required|image|mimes:jpeg,png,jpg'
        ]);

        $supplier = $request->user();

        $destDir = public_path('uploads/suppliers');
        if (!File::exists($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        if ($supplier->profile_image) {
            $existing = public_path($supplier->profile_image);
            if (File::exists($existing)) {
                File::delete($existing);
            }
        }

        $file = $request->file('profile_image');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->move($destDir, $filename);

        $supplier->profile_image = 'uploads/suppliers/' . $filename;
        $supplier->save();

        return response()->json([
            'message' => 'Profile image updated successfully',
            'user' => $supplier
        ]);
    }
}
