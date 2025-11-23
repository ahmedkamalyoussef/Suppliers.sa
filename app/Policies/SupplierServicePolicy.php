<?php

namespace App\Policies;

use App\Models\SupplierService;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupplierServicePolicy extends SupplierResourcePolicy
{
    use HandlesAuthorization;

    public function view(User $user, SupplierService $service)
    {
        return parent::view($user, $service, $service->supplier);
    }

    public function update(User $user, SupplierService $service)
    {
        return parent::update($user, $service, $service->supplier);
    }

    public function delete(User $user, SupplierService $service)
    {
        return parent::delete($user, $service, $service->supplier);
    }
}
