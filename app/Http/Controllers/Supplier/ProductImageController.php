<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\SupplierProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageController extends BaseSupplierController
{
    public function index()
    {
        $supplier = $this->getSupplier();
        $images = $supplier->productImages()->get();
        
        return response()->json($images);
    }

    public function store(Request $request)
    {
        $supplier = $this->getSupplier();
        $this->checkLimit($supplier, 'productImages', 8, 'You have reached the maximum number of product images for your plan.');

        $validated = $request->validate([
            'image' => 'required|image|max:5120', // 5MB max
        ]);

        $destDir = public_path('uploads/productImages');
        
        // Create directory if it doesn't exist
        if (!File::exists($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        $file = $request->file('image');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->move($destDir, $filename);
        
        $imageUrl = 'uploads/productImages/' . $filename;
        
        $image = $supplier->productImages()->create([
            'image_url' => url($imageUrl),
        ]);

        return response()->json($image, 201);
    }

    public function destroy(SupplierProductImage $image)
    {
        $this->authorize('delete', $image);
        
        // Delete the file from storage
        $path = parse_url($image->image_url, PHP_URL_PATH);
        $filePath = public_path($path);
        
        if (File::exists($filePath)) {
            File::delete($filePath);
        }
        
        $image->delete();
        
        return response()->noContent();
    }

    public function reorder(Request $request)
    {
        // Sorting is no longer supported as we removed the sort_order column
        return response()->json(['message' => 'Image reordering is not supported'], 400);
    }
}
