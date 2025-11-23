<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BaseSupplierController extends Controller
{
    protected function getSupplier(): Supplier
    {
        return Auth::user();
    }

    protected function checkLimit(Supplier $supplier, string $relation, int $limit, string $message): void
    {
        if ($supplier->plan === 'Basic' && $supplier->$relation()->count() >= $limit) {
            abort(403, $message);
        }
    }
}
