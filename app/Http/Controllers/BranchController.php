<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchController extends Controller
{
    /**
     * List all branches of the authenticated supplier
     */
    public function index(Request $request)
    {
        $branches = $request->user()->branches()->get();

        return response()->json(['branches' => $branches]);
    }

    /**
     * Create a new branch
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|email',
            'address' => 'required|string',
            'manager_name' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'working_hours' => 'required|array',
            'special_services' => 'required|array',
            'status' => 'required|string|in:active,inactive',
        ]);

        $branch = $request->user()->branches()->create(array_merge($validated, [
            'is_main_branch' => false
        ]));

        return response()->json([
            'message' => 'Branch created successfully',
            'branch' => $branch
        ], 201);
    }

    /**
     * Show a single branch
     */
    public function show(Request $request, Branch $branch)
    {
        if ($branch->supplier_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(['branch' => $branch]);
    }

    /**
     * Update branch
     */
    public function update(Request $request, Branch $branch)
    {
        if ($branch->supplier_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'email' => 'sometimes|email',
            'address' => 'sometimes|string',
            'manager_name' => 'sometimes|string|max:255',
            'latitude' => 'sometimes|numeric',
            'longitude' => 'sometimes|numeric',
            'working_hours' => 'sometimes|array',
            'special_services' => 'sometimes|array',
            'status' => 'sometimes|string|in:active,inactive',
        ]);

        $branch->update(array_filter($validated));

        return response()->json([
            'message' => 'Branch updated successfully',
            'branch' => $branch->fresh()
        ]);
    }

    /**
     * Delete a branch
     */
    public function destroy(Request $request, Branch $branch)
    {
        if ($branch->supplier_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($branch->is_main_branch) {
            return response()->json(['message' => 'Cannot delete main branch'], 400);
        }

        $branch->delete();

        return response()->json(['message' => 'Branch deleted successfully']);
    }

    /**
     * Set a branch as the main branch
     */
    public function setMainBranch(Request $request, Branch $branch)
    {
        if ($branch->supplier_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();
        try {
            // Remove main branch status from others
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

            return response()->json(['message' => 'Error setting main branch'], 500);
        }
    }
}
