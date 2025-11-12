<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\AdminPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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
    $admins = Admin::with('permissions')
        ->where('role', '!=', 'super_admin')
        ->get();

    return response()->json($admins);
}


    /**
     * Get a specific admin
     */
    public function show($id)
    {
        $admin = Admin::with('permissions')->findOrFail($id);
        return response()->json($admin);
    }

    /**
     * Create a new admin (only super admin can do this)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:admins,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,super_admin',
            'department' => 'nullable|string|max:255',
            'job_role' => 'nullable|string|max:255',
            // Permissions (only for regular admin)
            'permissions' => 'nullable|array',
            'permissions.user_management_view' => 'nullable|boolean',
            'permissions.user_management_edit' => 'nullable|boolean',
            'permissions.user_management_delete' => 'nullable|boolean',
            'permissions.user_management_full' => 'nullable|boolean',
            'permissions.content_management_view' => 'nullable|boolean',
            'permissions.content_management_supervise' => 'nullable|boolean',
            'permissions.content_management_delete' => 'nullable|boolean',
            'permissions.analytics_view' => 'nullable|boolean',
            'permissions.analytics_export' => 'nullable|boolean',
            'permissions.reports_view' => 'nullable|boolean',
            'permissions.reports_create' => 'nullable|boolean',
            'permissions.system_manage' => 'nullable|boolean',
            'permissions.system_settings' => 'nullable|boolean',
            'permissions.system_backups' => 'nullable|boolean',
            'permissions.support_manage' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        if (\App\Models\Admin::where('email', $request->email)->exists() || \App\Models\Supplier::where('email', $request->email)->exists()) {
            return response()->json(['message' => 'Email is already used'], 422);
        }

        // Validate department and job_role for regular admin
        if ($request->role === 'admin') {
            if (empty($request->department) || empty($request->job_role)) {
                return response()->json([
                    'message' => 'Department and job_role are required for regular admin'
                ], 422);
            }
        }

        // Create admin
        $admin = Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'department' => $request->department,
            'job_role' => $request->job_role,
            'email_verified_at' => now(), // Auto-verify admins
        ]);

        // Create permissions only for regular admin
        if ($request->role === 'admin' && $request->has('permissions')) {
            AdminPermission::create([
                'admin_id' => $admin->id,
                'user_management_view' => $request->input('permissions.user_management_view', false),
                'user_management_edit' => $request->input('permissions.user_management_edit', false),
                'user_management_delete' => $request->input('permissions.user_management_delete', false),
                'user_management_full' => $request->input('permissions.user_management_full', false),
                'content_management_view' => $request->input('permissions.content_management_view', false),
                'content_management_supervise' => $request->input('permissions.content_management_supervise', false),
                'content_management_delete' => $request->input('permissions.content_management_delete', false),
                'analytics_view' => $request->input('permissions.analytics_view', false),
                'analytics_export' => $request->input('permissions.analytics_export', false),
                'reports_view' => $request->input('permissions.reports_view', false),
                'reports_create' => $request->input('permissions.reports_create', false),
                'system_manage' => $request->input('permissions.system_manage', false),
                'system_settings' => $request->input('permissions.system_settings', false),
                'system_backups' => $request->input('permissions.system_backups', false),
                'support_manage' => $request->input('permissions.support_manage', false),
            ]);
        }

        $admin->load('permissions');

        return response()->json([
            'message' => 'Admin created successfully',
            'admin' => $admin
        ], 201);
    }

    /**
     * Update an admin
     */
    public function update(Request $request, $id)
    {
        $admin = Admin::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:admins,email,' . $id,
            'password' => 'sometimes|string|min:8|confirmed',
            'role' => 'sometimes|in:admin,super_admin',
            'department' => 'nullable|string|max:255',
            'job_role' => 'nullable|string|max:255',
            // Permissions (only for regular admin)
            'permissions' => 'nullable|array',
            'permissions.user_management_view' => 'nullable|boolean',
            'permissions.user_management_edit' => 'nullable|boolean',
            'permissions.user_management_delete' => 'nullable|boolean',
            'permissions.user_management_full' => 'nullable|boolean',
            'permissions.content_management_view' => 'nullable|boolean',
            'permissions.content_management_supervise' => 'nullable|boolean',
            'permissions.content_management_delete' => 'nullable|boolean',
            'permissions.analytics_view' => 'nullable|boolean',
            'permissions.analytics_export' => 'nullable|boolean',
            'permissions.reports_view' => 'nullable|boolean',
            'permissions.reports_create' => 'nullable|boolean',
            'permissions.system_manage' => 'nullable|boolean',
            'permissions.system_settings' => 'nullable|boolean',
            'permissions.system_backups' => 'nullable|boolean',
            'permissions.support_manage' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        if (\App\Models\Admin::where('email', $request->email)->exists() || \App\Models\Supplier::where('email', $request->email)->exists()) {
            return response()->json(['message' => 'Email is already used'], 422);
        }
        // Update admin fields
        if ($request->has('name')) {
            $admin->name = $request->name;
        }
        if ($request->has('email')) {
            $admin->email = $request->email;
        }
        if ($request->has('password')) {
            $admin->password = Hash::make($request->password);
        }
        if ($request->has('role')) {
            $admin->role = $request->role;
        }
        if ($request->has('department')) {
            $admin->department = $request->department;
        }
        if ($request->has('job_role')) {
            $admin->job_role = $request->job_role;
        }

        // Validate department and job_role for regular admin
        if ($admin->role === 'admin') {
            if (empty($admin->department) || empty($admin->job_role)) {
                return response()->json([
                    'message' => 'Department and job_role are required for regular admin'
                ], 422);
            }
        }

        $admin->save();

        // Update permissions only for regular admin
        if ($admin->role === 'admin' && $request->has('permissions')) {
            $permissions = $admin->permissions;
            
            if (!$permissions) {
                $permissions = AdminPermission::create(['admin_id' => $admin->id]);
            }

            $permissions->update([
                'user_management_view' => $request->input('permissions.user_management_view', $permissions->user_management_view),
                'user_management_edit' => $request->input('permissions.user_management_edit', $permissions->user_management_edit),
                'user_management_delete' => $request->input('permissions.user_management_delete', $permissions->user_management_delete),
                'user_management_full' => $request->input('permissions.user_management_full', $permissions->user_management_full),
                'content_management_view' => $request->input('permissions.content_management_view', $permissions->content_management_view),
                'content_management_supervise' => $request->input('permissions.content_management_supervise', $permissions->content_management_supervise),
                'content_management_delete' => $request->input('permissions.content_management_delete', $permissions->content_management_delete),
                'analytics_view' => $request->input('permissions.analytics_view', $permissions->analytics_view),
                'analytics_export' => $request->input('permissions.analytics_export', $permissions->analytics_export),
                'reports_view' => $request->input('permissions.reports_view', $permissions->reports_view),
                'reports_create' => $request->input('permissions.reports_create', $permissions->reports_create),
                'system_manage' => $request->input('permissions.system_manage', $permissions->system_manage),
                'system_settings' => $request->input('permissions.system_settings', $permissions->system_settings),
                'system_backups' => $request->input('permissions.system_backups', $permissions->system_backups),
                'support_manage' => $request->input('permissions.support_manage', $permissions->support_manage),
            ]);
        }

        $admin->load('permissions');

        return response()->json([
            'message' => 'Admin updated successfully',
            'admin' => $admin
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

        // Only admins can access this
        if (!($admin instanceof Admin)) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:admins,email,' . $admin->id,
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if (\App\Models\Admin::where('email', $request->email)->exists() || \App\Models\Supplier::where('email', $request->email)->exists()) {
            return response()->json(['message' => 'Email is already used'], 422);
        }
        if ($request->has('name')) {
            $admin->name = $request->name;
        }
        // Regular admin cannot change email
        if ($request->has('email') && $admin->isSuperAdmin()) {
            $admin->email = $request->email;
        }


        $admin->save();
        $admin->load('permissions');

        return response()->json([
            'message' => 'Profile updated successfully',
            'admin' => $admin
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
            'profile_image' => 'required|image|mimes:jpeg,png,jpg',
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
        $filename = \Illuminate\Support\Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->move($destDir, $filename);

        $admin->profile_image = 'uploads/admins/' . $filename;
        $admin->save();

        return response()->json([
            'message' => 'Profile image updated successfully',
            'admin' => $admin,
        ]);
    }

    /**
     * Register a super admin (super admin only)
     */
    public function registerSuper(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:admins,email',
            'password' => 'required|string|min:8|confirmed',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        if (\App\Models\Admin::where('email', $request->email)->exists() || \App\Models\Supplier::where('email', $request->email)->exists()) {
            return response()->json(['message' => 'Email is already used'], 422);
        }

        $admin = Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'super_admin',
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'Super admin created successfully',
            'admin' => $admin,
        ], 201);
    }
}
