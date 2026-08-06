<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\SupplierCertification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CertificationController extends BaseSupplierController
{
    public function index()
    {
        $supplier = $this->getSupplier();
        $certifications = $supplier->certifications()->get();
        
        return response()->json($certifications);
    }

    public function store(Request $request)
    {
        $supplier = $this->getSupplier();

        $validated = $request->validate([
            'certification_name' => 'required|string|max:255'
        ]);

        $certData = [
            'certification_name' => $validated['certification_name']
        ];


        $certification = $supplier->certifications()->create($certData);

        return response()->json($certification, 201);
    }

    public function update(Request $request, SupplierCertification $certification)
    {
        $this->authorize('update', $certification);

        $validated = $request->validate([
            'certification_name' => 'required|string|max:255',
        ]);

        $certData = [
            'certification_name' => $validated['certification_name'],
        ];
        
        $certData['expiry_date'] = $validated['expiry_date'] ?? null;

        if ($request->hasFile('certificate_file')) {
            // Delete old file if exists
            if ($certification->certificate_url) {
                $oldPath = str_replace('/storage/', '', parse_url($certification->certificate_url, PHP_URL_PATH));
                Storage::disk('public')->delete($oldPath);
            }
            
            // Upload new file
            $path = $request->file('certificate_file')->store("suppliers/{$certification->supplier_id}/certifications", 'public');
            $certData['certificate_url'] = Storage::url($path);
        }

        $certification->update($certData);

        return response()->json($certification);
    }

    public function destroy(SupplierCertification $certification)
    {
        $this->authorize('delete', $certification);
        
        // Delete the file if exists
        if ($certification->certificate_url) {
            $path = str_replace('/storage/', '', parse_url($certification->certificate_url, PHP_URL_PATH));
            Storage::disk('public')->delete($path);
        }
        
        $certification->delete();
        
        return response()->noContent();
    }

    public function reorder(Request $request)
    {
        // Sorting is no longer supported as we removed the sort_order column
        return response()->json(['message' => 'Certification reordering is not supported'], 400);
    }
}
