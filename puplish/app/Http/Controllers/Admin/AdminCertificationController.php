<?php

namespace App\Http\Controllers\Admin;

use App\Models\SupplierCertification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminCertificationController extends BaseAdminController
{
    public function index($supplierId)
    {
        $supplier = $this->getSupplier($supplierId);
        return response()->json($supplier->certifications);
    }

    public function store(Request $request, $supplierId)
    {
        $supplier = $this->getSupplier($supplierId);

        $validated = $request->validate([
            'certification_name' => 'required|string|max:255',
            'issuing_organization' => 'nullable|string|max:255',
            'issue_date' => 'required|date',
            'expiry_date' => 'nullable|date|after:issue_date',
            'certificate_number' => 'nullable|string|max:100',
            'certificate_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'description' => 'nullable|string',
        ]);

        $certData = [
            'certification_name' => $validated['certification_name'],
            'issuing_organization' => $validated['issuing_organization'] ?? null,
            'issue_date' => $validated['issue_date'],
            'expiry_date' => $validated['expiry_date'] ?? null,
            'certificate_number' => $validated['certificate_number'] ?? null,
            'description' => $validated['description'] ?? null,
            'sort_order' => $supplier->certifications()->max('sort_order') + 1,
        ];

        if ($request->hasFile('certificate_file')) {
            $path = $request->file('certificate_file')->store("suppliers/{$supplier->id}/certifications", 'public');
            $certData['certificate_url'] = Storage::url($path);
        }

        $certification = $supplier->certifications()->create($certData);

        return response()->json($certification, 201);
    }

    public function update(Request $request, $supplierId, $certificationId)
    {
        $certification = SupplierCertification::where('supplier_id', $supplierId)
            ->findOrFail($certificationId);

        $validated = $request->validate([
            'certification_name' => 'required|string|max:255',
            'issuing_organization' => 'nullable|string|max:255',
            'issue_date' => 'required|date',
            'expiry_date' => 'nullable|date|after:issue_date',
            'certificate_number' => 'nullable|string|max:100',
            'certificate_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'description' => 'nullable|string',
        ]);

        $certData = [
            'certification_name' => $validated['certification_name'],
            'issuing_organization' => $validated['issuing_organization'] ?? null,
            'issue_date' => $validated['issue_date'],
            'expiry_date' => $validated['expiry_date'] ?? null,
            'certificate_number' => $validated['certificate_number'] ?? null,
            'description' => $validated['description'] ?? null,
        ];

        if ($request->hasFile('certificate_file')) {
            // Delete old file if exists
            if ($certification->certificate_url) {
                $oldPath = str_replace('/storage/', '', parse_url($certification->certificate_url, PHP_URL_PATH));
                Storage::disk('public')->delete($oldPath);
            }
            
            // Upload new file
            $path = $request->file('certificate_file')->store("suppliers/{$supplierId}/certifications", 'public');
            $certData['certificate_url'] = Storage::url($path);
        }

        $certification->update($certData);

        return response()->json($certification);
    }

    public function destroy($supplierId, $certificationId)
    {
        $certification = SupplierCertification::where('supplier_id', $supplierId)
            ->findOrFail($certificationId);
        
        // Delete the file if exists
        if ($certification->certificate_url) {
            $path = str_replace('/storage/', '', parse_url($certification->certificate_url, PHP_URL_PATH));
            Storage::disk('public')->delete($path);
        }
        
        $certification->delete();
        
        return response()->noContent();
    }
}
