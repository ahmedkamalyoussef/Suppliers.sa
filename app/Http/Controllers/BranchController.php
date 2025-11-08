<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Supplier;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $branches = $request->user()->branches()->get();
        
        return response()->json([
            'branches' => $branches
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email'],
            'address' => ['required', 'string'],
            'manager_name' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'working_hours' => ['required', 'array'],
            'special_services' => ['required', 'array'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $branch = $request->user()->branches()->create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'manager_name' => $request->manager_name,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'working_hours' => $request->working_hours,
            'special_services' => $request->special_services,
            'status' => $request->status,
            'is_main_branch' => false
        ]);

        return response()->json([
            'message' => 'Branch created successfully',
            'branch' => $branch
        ], 201);
    }

    public function show(Request $request, Branch $branch)
    {
        if ($branch->supplier_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'branch' => $branch
        ]);
    }

    public function update(Request $request, Branch $branch)
    {
        if ($branch->supplier_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'email' => ['sometimes', 'email'],
            'address' => ['sometimes', 'string'],
            'manager_name' => ['sometimes', 'string', 'max:255'],
            'latitude' => ['sometimes', 'numeric'],
            'longitude' => ['sometimes', 'numeric'],
            'working_hours' => ['sometimes', 'array'],
            'special_services' => ['sometimes', 'array'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        $branch->update($request->only(['name', 'location', 'service_distance']));

        return response()->json([
            'message' => 'Branch updated successfully',
            'branch' => $branch->fresh()
        ]);
    }

    public function destroy(Request $request, Branch $branch)
    {
        if ($branch->supplier_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($branch->is_main_branch) {
            return response()->json([
                'message' => 'Cannot delete main branch'
            ], 400);
        }

        $branch->delete();

        return response()->json([
            'message' => 'Branch deleted successfully'
        ]);
    }

    public function setMainBranch(Request $request, Branch $branch)
    {
        if ($branch->supplier_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        // Begin transaction
        DB::beginTransaction();

        try {
            // Remove main branch status from all other branches
            $request->user()->branches()->where('is_main_branch', true)
                ->update(['is_main_branch' => false]);

            // Set this branch as main
            $branch->update(['is_main_branch' => true]);

            DB::commit();

            return response()->json([
                'message' => 'Main branch updated successfully',
                'branch' => $branch->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error setting main branch'
            ], 500);
        }
    }
}
