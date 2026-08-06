<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupplierResourcePolicy
{
    use HandlesAuthorization;

    public function viewAny($user, $supplier)
    {
        return $this->isAuthorized($user, $supplier);
    }

    public function view($user, $model, $supplier)
    {
        return $this->isAuthorized($user, $supplier);
    }

    public function create($user, $supplier)
    {
        return $this->isAuthorized($user, $supplier);
    }

    public function update($user, $model, $supplier)
    {
        return $this->isAuthorized($user, $supplier);
    }

    public function delete($user, $model, $supplier)
    {
        return $this->isAuthorized($user, $supplier);
    }

    protected function isAuthorized($user, $supplier)
    {
        // If user is admin, allow
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        // If user is a Supplier, check if it's the same as the target supplier
        if ($user instanceof Supplier) {
            return $user->id === $supplier->id;
        }

        // If user has a supplier relationship, check if it matches
        if (method_exists($user, 'supplier') && $user->supplier) {
            return $user->supplier->id === $supplier->id;
        }

        return false;
    }
}
