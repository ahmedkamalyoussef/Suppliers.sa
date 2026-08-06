<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\SupplierProductImage;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupplierProductImagePolicy extends SupplierResourcePolicy
{
    use HandlesAuthorization;

    public function view($user, $model, $supplier = null)
    {
        $supplier = $this->resolveSupplier($user, $supplier);
        
        if ($model instanceof SupplierProductImage) {
            return parent::view($user, $model, $model->supplier);
        }
        
        return parent::view($user, $model, $supplier);
    }

    public function update($user, $model, $supplier = null)
    {
        $supplier = $this->resolveSupplier($user, $supplier);
        
        if ($model instanceof SupplierProductImage) {
            return parent::update($user, $model, $model->supplier);
        }
        
        return parent::update($user, $model, $supplier);
    }

    public function delete($user, $model, $supplier = null)
    {
        $supplier = $this->resolveSupplier($user, $supplier);
        
        if ($model instanceof SupplierProductImage) {
            return parent::delete($user, $model, $model->supplier);
        }
        
        return parent::delete($user, $model, $supplier);
    }
    
    protected function resolveSupplier($user, $supplier = null)
    {
        if ($user instanceof Supplier) {
            return $user;
        }
        
        if ($user && method_exists($user, 'supplier')) {
            return $user->supplier;
        }
        
        return $supplier;
    }
}
