<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\SupplierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServiceController extends BaseSupplierController
{
    public function index()
    {
        $supplier = $this->getSupplier();
        $services = $supplier->services()->get();
        
        return response()->json($services);
    }

    public function store(Request $request)
    {
        $supplier = $this->getSupplier();
        $this->checkLimit($supplier, 'services', 8, 'You have reached the maximum number of services for your plan.');

        $validated = $request->validate([
            'service_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $service = $supplier->services()->create([
            'service_name' => $validated['service_name'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json($service, 201);
    }

    public function update(Request $request, SupplierService $service)
    {
        $this->authorize('update', $service);

        $validated = $request->validate([
            'service_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $service->update($validated);

        return response()->json($service);
    }

    public function destroy(SupplierService $service)
    {
        $this->authorize('delete', $service);
        $service->delete();
        return response()->noContent();
    }

    public function reorder(Request $request)
    {
        // Sorting is no longer supported as we removed the sort_order column
        return response()->json(['message' => 'Service reordering is not supported'], 400);
    }
}
