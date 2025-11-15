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
        $branches = $request->user()->branches()->get()->map(fn (Branch $branch) => $this->transformBranch($branch));

        return response()->json(['branches' => $branches]);
    }

    /**
     * Create a new branch
     */
    public function store(Request $request)
    {
        $this->normalizeBranchPayload($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'manager_name' => 'required|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'working_hours' => 'nullable|array',
            'special_services' => 'nullable|array',
            'status' => 'nullable|string|in:active,inactive',
            'is_main_branch' => 'sometimes|boolean',
        ]);

        $branch = $request->user()->branches()->create(array_merge($validated, [
            'status' => $validated['status'] ?? 'active',
            'working_hours' => $validated['working_hours'] ?? $this->defaultWorkingHours(),
            'special_services' => $validated['special_services'] ?? [],
            'is_main_branch' => $validated['is_main_branch'] ?? false,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ]));

        return response()->json([
            'message' => 'Branch created successfully',
            'branch' => $this->transformBranch($branch),
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

        return response()->json(['branch' => $this->transformBranch($branch)]);
    }

    /**
     * Update branch
     */
    public function update(Request $request, Branch $branch)
    {
        if ($branch->supplier_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->normalizeBranchPayload($request);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'email' => 'sometimes|nullable|email|max:255',
            'address' => 'sometimes|nullable|string|max:500',
            'manager_name' => 'sometimes|string|max:255',
            'latitude' => 'sometimes|nullable|numeric',
            'longitude' => 'sometimes|nullable|numeric',
            'working_hours' => 'sometimes|nullable|array',
            'special_services' => 'sometimes|nullable|array',
            'status' => 'sometimes|nullable|string|in:active,inactive',
            'is_main_branch' => 'sometimes|boolean',
        ]);

        $payload = array_filter($validated, function ($value) {
            return $value !== null;
        });

        if (!array_key_exists('working_hours', $payload) && !$branch->working_hours) {
            $payload['working_hours'] = $this->defaultWorkingHours();
        }

        if (!array_key_exists('special_services', $payload) && !$branch->special_services) {
            $payload['special_services'] = [];
        }

        $branch->update($payload);

        return response()->json([
            'message' => 'Branch updated successfully',
            'branch' => $this->transformBranch($branch->fresh()),
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
                'branch' => $this->transformBranch($branch->fresh()),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Error setting main branch'], 500);
        }
    }

    private function normalizeBranchPayload(Request $request): void
    {
        if (!$request->has('manager_name') && $request->filled('manager')) {
            $request->merge(['manager_name' => $request->input('manager')]);
        }

        if ($request->has('location')) {
            $location = $request->input('location', []);
            if (data_get($location, 'lat') !== null) {
                $request->merge(['latitude' => data_get($location, 'lat')]);
            }
            if (data_get($location, 'lng') !== null) {
                $request->merge(['longitude' => data_get($location, 'lng')]);
            }
        }

        if (!$request->has('working_hours') && $request->has('workingHours')) {
            $request->merge(['working_hours' => $request->input('workingHours')]);
        }

        if (!$request->has('special_services') && $request->has('specialServices')) {
            $request->merge(['special_services' => $request->input('specialServices')]);
        }

        if ($request->has('isMainBranch')) {
            $request->merge(['is_main_branch' => (bool) $request->input('isMainBranch')]);
        }

        if (!$request->has('status') && $request->isMethod('post')) {
            $request->merge(['status' => 'active']);
        }
    }

    /**
     * @return array<string, array{open:string,close:string,closed:bool}>
     */
    private function defaultWorkingHours(): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $hours = [];

        foreach ($days as $day) {
            $hours[$day] = [
                'open' => $day === 'sunday' ? '10:00' : '09:00',
                'close' => $day === 'sunday' ? '16:00' : '18:00',
                'closed' => $day === 'sunday',
            ];
        }

        return $hours;
    }
}
