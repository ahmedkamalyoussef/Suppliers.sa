<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Partnership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PartnershipController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            
            if (!$user || !($user instanceof Admin)) {
                return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
            }

            // Super admins can access everything
            if ($user->isSuperAdmin()) {
                return $next($request);
            }

            // Check content management permissions
            if (!$user->permissions || $user->permissions->content_management_supervise) {
                return response()->json(['message' => 'Unauthorized. Content management permission required.'], 403);
            }

            return $next($request);
        })->except(['index']);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        $partnership = new Partnership();
        $partnership->name = $request->name;

        if ($request->hasFile('image')) {
            $destDir = public_path('uploads/partnerships');
            
            if (!File::exists($destDir)) {
                File::makeDirectory($destDir, 0755, true);
            }

            $file = $request->file('image');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $file->move($destDir, $filename);
            
            $partnership->image = 'uploads/partnerships/' . $filename;
        }

        $partnership->save();

        return response()->json([
            'message' => 'Partnership created successfully',
            'partnership' => [
                'id' => $partnership->id,
                'name' => $partnership->name,
                'image' => $partnership->image ? url($partnership->image) : null,
                'created_at' => $partnership->created_at,
            ]
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        
        // Check content management supervise permission for update
        if (!$user->isSuperAdmin() && 
            (!$user->permissions || !$user->permissions->content_management_supervise)) {
            return response()->json(['message' => 'Unauthorized. Content management supervise permission required.'], 403);
        }

        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        $partnership = Partnership::findOrFail($id);
        
        if ($request->has('name')) {
            $partnership->name = $request->name;
        }

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($partnership->image && File::exists(public_path($partnership->image))) {
                File::delete(public_path($partnership->image));
            }

            $destDir = public_path('uploads/partnerships');
            
            if (!File::exists($destDir)) {
                File::makeDirectory($destDir, 0755, true);
            }

            $file = $request->file('image');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $file->move($destDir, $filename);
            
            $partnership->image = 'uploads/partnerships/' . $filename;
        }

        $partnership->save();

        return response()->json([
            'message' => 'Partnership updated successfully',
            'partnership' => [
                'id' => $partnership->id,
                'name' => $partnership->name,
                'image' => $partnership->image ? url($partnership->image) : null,
                'updated_at' => $partnership->updated_at,
            ]
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        
        // Check content management supervise or delete permission for delete
        if (!$user->isSuperAdmin() && 
            (!$user->permissions || 
             (!$user->permissions->content_management_supervise && 
              !$user->permissions->content_management_delete))) {
            return response()->json(['message' => 'Unauthorized. Content management supervise or delete permission required.'], 403);
        }

        $partnership = Partnership::findOrFail($id);

        // Delete image if exists
        if ($partnership->image && File::exists(public_path($partnership->image))) {
            File::delete(public_path($partnership->image));
        }

        $partnership->delete();

        return response()->json(['message' => 'Partnership deleted successfully']);
    }

    public function index()
    {
        $partnerships = Partnership::orderBy('created_at', 'desc')->get(['id', 'name', 'image', 'created_at']);
        
        // Convert image paths to full URLs
        $partnerships->each(function ($partnership) {
            $partnership->image = $partnership->image ? url($partnership->image) : null;
        });
        
        return response()->json($partnerships);
    }
}
