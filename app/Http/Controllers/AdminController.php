<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\AdminPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            $action = $request->route() ? $request->route()->getActionMethod() : null;

            // Special case: registerSuper can be public ONLY if no super admin exists yet.
            if ($action === 'registerSuper') {
                $hasSuper = Admin::where('role', 'super_admin')->exists();
                if ($hasSuper) {
                    if (!$user || !($user instanceof Admin) || !$user->isSuperAdmin()) {
                        return response()->json(['message' => 'Unauthorized. Super admin access required.'], 403);
                    }
                }
                return $next($request);
            }

            // Allow any authenticated admin to access own profile endpoints
            $selfAllowed = in_array($action, ['updateProfile', 'updateProfileImage'], true);

            if (!$selfAllowed) {
                // Only super admins can access management routes
                if (!$user || !($user instanceof Admin) || !$user->isSuperAdmin()) {
                    return response()->json(['message' => 'Unauthorized. Super admin access required.'], 403);
                }
            } else {
                if (!$user || !($user instanceof Admin)) {
                    return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
                }
            }

            return $next($request);
        });
    }

    /**
     * Get all admins
     */
    public function index()
{
        $admins = Admin::with('permissions')->get()->map(fn (Admin $admin) => $this->transformAdmin($admin));

        return response()->json(['admins' => $admins]);
}


    /**
     * Get a specific admin
     */
    public function show($id)
    {
        $admin = Admin::with('permissions')->findOrFail($id);
        return response()->json(['admin' => $this->transformAdmin($admin)]);
    }

    /**
     * Create a new admin (only super admin can do this)
     */
    public function store(Request $request)
    {
        if (!$request->has('job_role') && $request->filled('jobRole')) {
            $request->merge(['job_role' => $request->input('jobRole')]);
        }

        if (!$request->has('password_confirmation') && $request->filled('passwordConfirmation')) {
            $request->merge(['password_confirmation' => $request->input('passwordConfirmation')]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:admins,email',
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|in:admin,super_admin',
            'department' => 'nullable|string|max:255',
            'job_role' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ($request->role === 'admin' && (empty($request->department) || empty($request->job_role))) {
            return response()->json([
                'message' => 'Department and jobRole are required for regular admin',
            ], 422);
        }

        if (Admin::where('email', $request->email)->exists()) {
            return response()->json(['message' => 'Email is already used'], 422);
        }

        $password = $request->filled('password') ? $request->password : Str::random(12);

        $admin = Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($password),
            'role' => $request->role,
            'department' => $request->department,
            'job_role' => $request->job_role,
            'email_verified_at' => now(),
        ]);

        if ($admin->role === 'admin') {
            $permissions = $this->normalizePermissions($request->input('permissions', []));
            AdminPermission::updateOrCreate(['admin_id' => $admin->id], $permissions);
        }

        $admin->load('permissions');

        return response()->json([
            'message' => 'Admin created successfully',
            'admin' => $this->transformAdmin($admin),
            'generatedPassword' => $request->filled('password') ? null : $password,
        ], 201);
    }

    /**
     * Update an admin
     */
    public function update(Request $request, $id)
    {
        $admin = Admin::findOrFail($id);

        if (!$request->has('job_role') && $request->filled('jobRole')) {
            $request->merge(['job_role' => $request->input('jobRole')]);
        }

        if (!$request->has('password_confirmation') && $request->filled('passwordConfirmation')) {
            $request->merge(['password_confirmation' => $request->input('passwordConfirmation')]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:admins,email,' . $id,
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'sometimes|in:admin,super_admin',
            'department' => 'nullable|string|max:255',
            'job_role' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ($request->filled('name')) {
            $admin->name = $request->name;
        }

        if ($request->filled('email') && $request->email !== $admin->email) {
            $admin->email = $request->email;
        }

        if ($request->filled('phone')) {
            $admin->phone = $request->phone;
        }

        if ($request->filled('password')) {
            $admin->password = Hash::make($request->password);
        }

        if ($request->filled('role')) {
            $admin->role = $request->role;
        }

        if ($admin->role === 'admin') {
            if (empty($request->input('department', $admin->department)) || empty($request->input('job_role', $admin->job_role))) {
                return response()->json([
                    'message' => 'Department and jobRole are required for regular admin',
                ], 422);
            }
        }

        if ($request->has('department')) {
            $admin->department = $request->department;
        }

        if ($request->has('job_role')) {
            $admin->job_role = $request->job_role;
        }

        $admin->save();

        if ($admin->role === 'admin') {
            $permissions = $this->normalizePermissions($request->input('permissions', []));
            AdminPermission::updateOrCreate(['admin_id' => $admin->id], $permissions);
        } else {
            $admin->permissions()->delete();
        }

        $admin->load('permissions');

        return response()->json([
            'message' => 'Admin updated successfully',
            'admin' => $this->transformAdmin($admin),
        ]);
    }

    /**
     * Delete an admin
     */
    public function destroy($id)
    {
        $admin = Admin::findOrFail($id);
        
        // Prevent deleting yourself
        if ($admin->id === auth()->id()) {
            return response()->json([
                'message' => 'You cannot delete your own account'
            ], 403);
        }

        $admin->delete();

        return response()->json([
            'message' => 'Admin deleted successfully'
        ]);
    }

    /**
     * Update admin's own profile (name, email, department, job_role only)
     * Admin can update their own profile without super admin permission
     */
    public function updateProfile(Request $request)
    {
        $admin = $request->user();

        if (!($admin instanceof Admin)) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        if (!$request->has('job_role') && $request->filled('jobRole')) {
            $request->merge(['job_role' => $request->input('jobRole')]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:admins,email,' . $admin->id,
            'department' => 'sometimes|nullable|string|max:255',
            'job_role' => 'sometimes|nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ($request->filled('name')) {
            $admin->name = $request->name;
        }

        if ($request->filled('department')) {
            $admin->department = $request->department;
        }

        if ($request->filled('job_role')) {
            $admin->job_role = $request->job_role;
        }

        if ($admin->isSuperAdmin() && $request->filled('email')) {
            $admin->email = $request->email;
        }

        if ($admin->role === 'admin' && (empty($admin->department) || empty($admin->job_role))) {
            return response()->json([
                'message' => 'Department and jobRole are required for regular admin',
            ], 422);
        }

        $admin->save();
        $admin->load('permissions');

        return response()->json([
            'message' => 'Profile updated successfully',
            'admin' => $this->transformAdmin($admin),
        ]);
    }

    /**
     * Upload/update admin profile image (self)
     */
    public function updateProfileImage(Request $request)
    {
        $admin = $request->user();
        if (!($admin instanceof Admin)) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'profile_image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $destDir = public_path('uploads/admins');
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        // delete previous if exists
        if (!empty($admin->profile_image)) {
            $existing = public_path($admin->profile_image);
            if (is_file($existing)) {
                @unlink($existing);
            }
        }

        $file = $request->file('profile_image');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->move($destDir, $filename);

        $admin->profile_image = 'uploads/admins/' . $filename;
        $admin->save();
        $admin->load('permissions');

        return response()->json([
            'message' => 'Profile image updated successfully',
            'admin' => $this->transformAdmin($admin),
        ]);
    }

    /**
     * Register a super admin (super admin only)
     */
    public function registerSuper(Request $request)
    {
        if (!$request->has('password_confirmation') && $request->filled('passwordConfirmation')) {
            $request->merge(['password_confirmation' => $request->input('passwordConfirmation')]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:admins,email',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if (Admin::where('email', $request->email)->exists()) {
            return response()->json(['message' => 'Email is already used'], 422);
        }

        $password = $request->filled('password') ? $request->password : Str::random(12);

        $admin = Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($password),
            'role' => 'super_admin',
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'Super admin created successfully',
            'admin' => $this->transformAdmin($admin),
            'generatedPassword' => $request->filled('password') ? null : $password,
        ], 201);
    }

    /**
     * Normalize permissions from camelCase to snake_case for storage.
     */
    private function normalizePermissions(array $permissions = []): array
    {
        $defaults = [
            'user_management_view' => false,
            'user_management_edit' => false,
            'user_management_delete' => false,
            'user_management_full' => false,
            'content_management_view' => false,
            'content_management_supervise' => false,
            'content_management_delete' => false,
            'analytics_view' => false,
            'analytics_export' => false,
            'reports_view' => false,
            'reports_create' => false,
            'system_manage' => false,
            'system_settings' => false,
            'system_backups' => false,
            'support_manage' => false,
        ];

        if (empty($permissions)) {
            return $defaults;
        }

        // If already in snake_case structure, just merge with defaults
        if (isset($permissions['user_management_view'])) {
            return array_merge($defaults, array_intersect_key($permissions, $defaults));
        }

        return [
            'user_management_view' => (bool) data_get($permissions, 'userManagement.view', false),
            'user_management_edit' => (bool) data_get($permissions, 'userManagement.edit', false),
            'user_management_delete' => (bool) data_get($permissions, 'userManagement.delete', false),
            'user_management_full' => (bool) data_get($permissions, 'userManagement.full', false),
            'content_management_view' => (bool) data_get($permissions, 'contentManagement.view', false),
            'content_management_supervise' => (bool) data_get($permissions, 'contentManagement.supervise', false),
            'content_management_delete' => (bool) data_get($permissions, 'contentManagement.delete', false),
            'analytics_view' => (bool) data_get($permissions, 'analytics.view', false),
            'analytics_export' => (bool) data_get($permissions, 'analytics.export', false),
            'reports_view' => (bool) data_get($permissions, 'reports.view', false),
            'reports_create' => (bool) data_get($permissions, 'reports.create', false),
            'system_manage' => (bool) data_get($permissions, 'system.manage', false),
            'system_settings' => (bool) data_get($permissions, 'system.settings', false),
            'system_backups' => (bool) data_get($permissions, 'system.backups', false),
            'support_manage' => (bool) data_get($permissions, 'support.manage', false),
        ];
    }
}

