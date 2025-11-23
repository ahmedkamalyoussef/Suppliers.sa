<?php

namespace App\Http\Controllers\Admin;

use App\Models\SupplierService;
use Illuminate\Http\Request;

class AdminServiceController extends BaseAdminController
{
    public function index($supplierId)
    {
        $supplier = $this->getSupplier($supplierId);
        return response()->json($supplier->services);
    }

    public function store(Request $request, $supplierId)
    {
        $supplier = $this->getSupplier($supplierId);

        $validated = $request->validate([
            'service_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $service = $supplier->services()->create([
            'service_name' => $validated['service_name'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $supplier->services()->max('sort_order') + 1,
        ]);

        return response()->json($service, 201);
    }

    public function update(Request $request, $supplierId, $serviceId)
    {
        $service = SupplierService::where('supplier_id', $supplierId)
            ->findOrFail($serviceId);

        $validated = $request->validate([
            'service_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $service->update($validated);

        return response()->json($service);
    }

    public function destroy($supplierId, $serviceId)
    {
        $service = SupplierService::where('supplier_id', $supplierId)
            ->findOrFail($serviceId);
            
        $service->delete();
        
        return response()->noContent();
    }
}
