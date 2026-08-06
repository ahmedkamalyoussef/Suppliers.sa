<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupplierProductPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can update the model.
     */
    public function update(Supplier $supplier, SupplierProduct $supplierProduct): bool
    {
        return $supplier->id === $supplierProduct->supplier_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Supplier $supplier, SupplierProduct $supplierProduct): bool
    {
        return $supplier->id === $supplierProduct->supplier_id;
    }
}
