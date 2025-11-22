<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'business_name' => 'required|string|max:255',
            'referral_code' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'business_name' => $request->business_name,
            'plan' => 'Basic',
            'status' => 'active',
            'join_date' => now(),
            'profile_completion' => 0,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'business_name' => $user->business_name,
                'plan' => $user->plan,
                'status' => $user->status,
                'join_date' => $user->join_date->toISOString(),
                'profile_completion' => $user->profile_completion,
            ],
            'token' => $token
        ], 201);
    }

    /**
     * Get list of users (admin only)
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        // Apply filters
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('business_name', 'like', "%{$search}%");
            });
        }

        if ($request->status) {
            $query->byStatus($request->status);
        }

        if ($request->plan) {
            $query->byPlan($request->plan);
        }

        $users = $query->paginate($request->limit ?? 10);

        return response()->json([
            'success' => true,
            'users' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'business_name' => $user->business_name,
                    'plan' => $user->plan,
                    'status' => $user->status,
                    'join_date' => $user->join_date?->toISOString(),
                    'last_active' => $user->last_active?->toISOString(),
                    'revenue' => $user->revenue ?? '0',
                    'profile_completion' => $user->profile_completion,
                    'avatar' => $user->avatar ?? $user->profile_image,
                ];
            }),
            'pagination' => [
                'page' => $users->currentPage(),
                'limit' => $users->perPage(),
                'total' => $users->total(),
                'totalPages' => $users->lastPage(),
            ]
        ]);
    }

    /**
     * Update user data
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'business_name' => 'sometimes|required|string|max:255',
            'plan' => ['sometimes', 'required', Rule::in(['Basic', 'Premium', 'Enterprise'])],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'suspended'])],
            'avatar' => 'sometimes|nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update($request->only([
            'name', 'email', 'business_name', 'plan', 'status', 'avatar'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'business_name' => $user->business_name,
                'plan' => $user->plan,
                'status' => $user->status,
                'avatar' => $user->avatar,
                'profile_completion' => $user->profile_completion,
            ]
        ]);
    }

    /**
     * Get user limits
     */
    public function getLimits(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'limits' => [
                'maxBusinesses' => $user->getMaxBusinesses(),
                'currentBusinesses' => $user->businesses()->count(),
                'remainingBusinesses' => $user->getRemainingBusinesses(),
                'plan' => $user->plan,
                'features' => [
                    'supplierRating' => in_array($user->plan, ['Premium', 'Enterprise']),
                    'advancedAnalytics' => $user->plan === 'Enterprise',
                    'exportData' => in_array($user->plan, ['Premium', 'Enterprise']),
                    'prioritySupport' => $user->plan === 'Enterprise',
                ]
            ]
        ]);
    }

    /**
     * Check if user can add new business
     */
    public function canAddBusiness(Request $request): JsonResponse
    {
        $user = $request->user();
        $canAdd = $user->canAddBusiness();
        
        return response()->json([
            'success' => true,
            'canAdd' => $canAdd,
            'message' => $canAdd 
                ? 'You can add a new business' 
                : 'You have reached your business limit. Upgrade your plan to add more businesses.',
            'currentCount' => $user->businesses()->count(),
            'maxAllowed' => $user->getMaxBusinesses(),
        ]);
    }

    /**
     * Get user profile
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'business_name' => $user->business_name,
                'plan' => $user->plan,
                'status' => $user->status,
                'avatar' => $user->avatar ?? $user->profile_image,
                'join_date' => $user->join_date?->toISOString(),
                'last_active' => $user->last_active?->toISOString(),
                'profile_completion' => $user->profile_completion,
                'revenue' => $user->revenue ?? '0',
            ]
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'business_name' => 'sometimes|required|string|max:255',
            'avatar' => 'sometimes|nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update($request->only([
            'name', 'phone', 'business_name', 'avatar'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'business_name' => $user->business_name,
                'plan' => $user->plan,
                'status' => $user->status,
                'avatar' => $user->avatar ?? $user->profile_image,
                'profile_completion' => $user->profile_completion,
            ]
        ]);
    }
}
