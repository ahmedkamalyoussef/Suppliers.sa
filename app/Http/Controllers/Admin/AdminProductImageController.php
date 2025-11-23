<?php

namespace App\Http\Controllers\Admin;

use App\Models\SupplierProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminProductImageController extends BaseAdminController
{
    public function index($supplierId)
    {
        $supplier = $this->getSupplier($supplierId);
        return response()->json($supplier->productImages);
    }

    public function store(Request $request, $supplierId)
    {
        $supplier = $this->getSupplier($supplierId);
        
        $validated = $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        $path = $request->file('image')->store("suppliers/{$supplier->id}/products", 'public');
        
        $image = $supplier->productImages()->create([
            'image_url' => Storage::url($path),
            'sort_order' => $supplier->productImages()->max('sort_order') + 1,
        ]);

        return response()->json($image, 201);
    }

    public function destroy($supplierId, $imageId)
    {
        $image = SupplierProductImage::findOrFail($imageId);
        
        // Delete the file from storage
        $path = str_replace('/storage/', '', parse_url($image->image_url, PHP_URL_PATH));
        Storage::disk('public')->delete($path);
        
        $image->delete();
        
        return response()->noContent();
    }
}
