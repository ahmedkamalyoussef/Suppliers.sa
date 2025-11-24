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
        $images = $supplier->productImages()->get(['id', 'image_url', 'name']);
        
        return response()->json($images);
    }

    public function store(Request $request)
    {
        $supplier = $this->getSupplier();
        $this->checkLimit($supplier, 'productImages', 8, 'You have reached the maximum number of product images for your plan.');

        $validated = $request->validate([
            'image' => 'required|image|max:5120', // 5MB max
            'name' => 'nullable|string|max:255',
        ]);

        $destDir = 'uploads/productImages/';
        
        // Create directory if it doesn't exist
        if (!File::exists(public_path($destDir))) {
            File::makeDirectory(public_path($destDir), 0755, true);
        }

        $file = $request->file('image');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path($destDir), $filename);
        
        $imageUrl = $destDir . $filename;
        
        $image = $supplier->productImages()->create([
            'image_url' => url($imageUrl),
            'name' => $validated['name'] ?? $file->getClientOriginalName(),
        ]);

        return response()->json($image, 201);
    }

    public function destroy(SupplierProductImage $image)
    {
        $this->authorize('delete', $image);
        
        // Delete the file from public directory
        $path = parse_url($image->image_url, PHP_URL_PATH);
        $path = ltrim($path, '/'); // Remove leading slash
        
        if (File::exists(public_path($path))) {
            File::delete(public_path($path));
        }
        
        $image->delete();
        
        return response()->json(null, 204);
    }

    public function reorder(Request $request)
    {
        // Sorting is no longer supported as we removed the sort_order column
        return response()->json(['message' => 'Image reordering is not supported'], 400);
    }
}
