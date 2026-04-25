<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\SupplierService;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupplierServicePolicy extends SupplierResourcePolicy
{
    use HandlesAuthorization;

    public function view($user, $model, $supplier = null)
    {
        if ($model instanceof SupplierService) {
            return parent::view($user, $model, $model->supplier);
        }
        
        return parent::view($user, $model, $supplier);
    }

    public function update($user, $model, $supplier = null)
    {
        if ($model instanceof SupplierService) {
            return parent::update($user, $model, $model->supplier);
        }
        
        return parent::update($user, $model, $supplier);
    }

    public function delete($user, $model, $supplier = null)
    {
        if ($model instanceof SupplierService) {
            return parent::delete($user, $model, $model->supplier);
        }
        
        return parent::delete($user, $model, $supplier);
    }
}
