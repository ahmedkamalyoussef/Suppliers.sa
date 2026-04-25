<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class SupplierBusinessImageController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'business_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $supplier = $request->user();
        
        // Delete old business image if exists
        if ($supplier->profile->business_image) {
            $oldImagePath = public_path($supplier->profile->business_image);
            if (File::exists($oldImagePath) && !str_contains($oldImagePath, 'default.png')) {
                File::delete($oldImagePath);
            }
        }

        // Create directory if it doesn't exist
        $uploadPath = public_path('uploads/businessImages');
        if (!File::exists($uploadPath)) {
            File::makeDirectory($uploadPath, 0755, true, true);
        }

        // Generate unique filename
        $filename = 'business_' . time() . '_' . uniqid() . '.' . $request->file('business_image')->getClientOriginalExtension();
        
        // Move the file to the public/uploads/businessImages directory
        $request->file('business_image')->move($uploadPath, $filename);
        
        // Relative path to store in database
        $imagePath = 'uploads/businessImages/' . $filename;
        
        // Update profile with new business image path
        $supplier->profile->update([
            'business_image' => $imagePath
        ]);

        return response()->json([
            'message' => 'تم تحديث صورة النشاط التجاري بنجاح',
            'business_image' => asset($imagePath)
        ]);
    }
}
