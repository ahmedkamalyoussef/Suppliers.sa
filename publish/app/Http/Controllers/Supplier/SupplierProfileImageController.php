<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class SupplierProfileImageController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'profile_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $supplier = $request->user();

        // Delete old profile image if exists
        if ($supplier->profile_image && $supplier->profile_image !== 'uploads/default.png') {
            $oldImagePath = public_path($supplier->profile_image);
            if (File::exists($oldImagePath)) {
                File::delete($oldImagePath);
            }
        }

        // Create directory if it doesn't exist
        $uploadPath = public_path('uploads/suppliers');
        if (!File::exists($uploadPath)) {
            File::makeDirectory($uploadPath, 0755, true, true);
        }

        // Generate unique filename
        $filename = 'profile_' . time() . '_' . uniqid() . '.' . $request->file('profile_image')->getClientOriginalExtension();
        
        // Move the file
        $request->file('profile_image')->move($uploadPath, $filename);
        
        // Relative path to store in database
        $imagePath = 'uploads/suppliers/' . $filename;
        
        // Update supplier with new profile image path
        $supplier->update(['profile_image' => $imagePath]);
        
        return response()->json([
            'message' => 'تم تحديث صورة الملف الشخصي بنجاح',
            'profile_image' => asset($imagePath)
        ]);
    }
}
