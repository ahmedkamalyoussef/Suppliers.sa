<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SupplierProductController extends Controller
{
    /**
     * The authenticated supplier.
     *
     * @var \App\Models\Supplier
     */
    protected $supplier;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->supplier = Auth::guard('supplier')->user();
            return $next($request);
        });
    }

    /**
     * Display a listing of the supplier's products.
     */
    public function index()
    {
        $products = $this->supplier->products()
            ->select(['id', 'product_name'])
            ->get();
        
        return response()->json($products);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_name' => 'required|string|max:255',
        ]);

        $product = $this->supplier->products()->create($validated);

        return response()->json([
            'id' => $product->id,
            'product_name' => $product->product_name,
        ], 201);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $product = $this->supplier->products()->findOrFail($id);
        
        $validated = $request->validate([
            'product_name' => 'required|string|max:255',
        ]);

        $product->update($validated);

        return response()->json([
            'id' => $product->id,
            'product_name' => $product->product_name,
        ]);
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy($id): JsonResponse
    {
        $product = $this->supplier->products()->findOrFail($id);
        $product->delete();
        
        return response()->json(null, 204);
    }
}
