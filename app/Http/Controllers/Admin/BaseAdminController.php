<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class BaseAdminController extends Controller
{
    protected function getSupplier($id)
    {
        return Supplier::findOrFail($id);
    }
}
