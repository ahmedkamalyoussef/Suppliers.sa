<?php

namespace App\Policies;

use App\Models\SupplierCertification;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupplierCertificationPolicy extends SupplierResourcePolicy
{
    use HandlesAuthorization;

    public function view(User $user, SupplierCertification $certification)
    {
        return parent::view($user, $certification, $certification->supplier);
    }

    public function update(User $user, SupplierCertification $certification)
    {
        return parent::update($user, $certification, $certification->supplier);
    }

    public function delete(User $user, SupplierCertification $certification)
    {
        return parent::delete($user, $certification, $certification->supplier);
    }
}
