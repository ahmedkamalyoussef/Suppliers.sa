<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Supplier;

class PasswordService
{
    /**
     * Locate a user by email across Admins and Suppliers.
     * Returns ['user' => Model, 'type' => 'admin'|'supplier'] or null.
     */
    public function findUserByEmail(string $email): ?array
    {
        $user = Admin::where('email', $email)->first();
        if ($user) {
            return ['user' => $user, 'type' => 'admin'];
        }

        $user = Supplier::where('email', $email)->first();
        if ($user) {
            return ['user' => $user, 'type' => 'supplier'];
        }

        return null;
    }
}
