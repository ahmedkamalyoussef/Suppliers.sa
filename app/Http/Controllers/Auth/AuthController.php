<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Supplier\SupplierResource;
use App\Models\Admin;
use App\Models\Otp;
use App\Models\Supplier;
use App\Notifications\OtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Find a user by email in admins or suppliers tables.
     */
    protected function findUserByEmail(string $email)
    {
        $user = Admin::where('email', $email)->first();
        if ($user) {
            $userType = $user->role === 'super_admin' ? 'super_admin' : 'admin';
            return ['user' => $user, 'type' => $userType];
        }

        $user = Supplier::where('email', $email)->first();
        if ($user) {
            return ['user' => $user, 'type' => 'supplier'];
        }

        return null;
    }

    /**
     * Login user (admin or supplier)
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $userInfo = $this->findUserByEmail($request->email);
        if (! $userInfo) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user = $userInfo['user'];
        if (! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // For admins, email verification is optional (can be null)
        // For suppliers, check email verification
        if ($userInfo['type'] === 'supplier' && ! $user->email_verified_at) {
            return response()->json(['message' => 'Email not verified'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Update last_seen_at for suppliers on login
        if ($userInfo['type'] === 'supplier') {
            $user->last_seen_at = now();
            $user->save();
        }

        // Load relationships
        if (in_array($userInfo['type'], ['admin', 'super_admin'])) {
            $user->load('permissions');
        } elseif ($userInfo['type'] === 'supplier' && method_exists($user, 'profile')) {
            $user->load('profile', 'branches');
        }

        if (in_array($userInfo['type'], ['admin', 'super_admin'])) {
            $payloadKey = $userInfo['type'];
            $payloadValue = $this->transformAdmin($user);
        } else {
            $payloadKey = 'supplier';
            $payloadValue = (new SupplierResource($user))->toArray(request());
        }

        return response()->json([
            'message' => 'Login successful',
            'userType' => $userInfo['type'],
            $payloadKey => $payloadValue,
            'accessToken' => $token,
            'tokenType' => 'Bearer',
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $bearer = $request->bearerToken();
        if ($bearer) {
            $pat = PersonalAccessToken::findToken($bearer);
            if ($pat) {
                $pat->delete();
            }
        }

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Send OTP to user email (for admins and suppliers)
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), ['email' => 'required|email']);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $userInfo = $this->findUserByEmail($request->email);
        if (! $userInfo) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user = $userInfo['user'];

        // Generate OTP based on user type
        $otp = in_array($userInfo['type'], ['admin', 'super_admin'])
            ? Otp::generateForAdmin($user->id, $user->email)
            : Otp::generateForSupplier($user->id, $user->email);

        $user->notify(new OtpNotification($otp->otp));

        return response()->json(['message' => 'OTP has been sent to your email']);
    }

    /**
     * Verify OTP (for admins and suppliers)
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|numeric|digits:6',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $userInfo = $this->findUserByEmail($request->email);
        if (! $userInfo) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user = $userInfo['user'];

        // Find OTP based on user type
        $otp = in_array($userInfo['type'], ['admin', 'super_admin'])
            ? Otp::where('admin_id', $user->id)
                ->where('otp', $request->otp)
                ->where('expires_at', '>', now())
                ->first()
            : Otp::where('supplier_id', $user->id)
                ->where('otp', $request->otp)
                ->where('expires_at', '>', now())
                ->first();

        if (! $otp) {
            return response()->json(['message' => 'Invalid or expired OTP'], 422);
        }

        $user->email_verified_at = now();
        $user->save();
        $otp->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        // Update last_seen_at for suppliers on OTP login
        if ($userInfo['type'] === 'supplier') {
            $user->last_seen_at = now();
            $user->save();
        }

        // Load relationships
        if (in_array($userInfo['type'], ['admin', 'super_admin'])) {
            $user->load('permissions');
        } elseif (method_exists($user, 'profile')) {
            $user->load('profile', 'branches');
        }

        if (in_array($userInfo['type'], ['admin', 'super_admin'])) {
            $payloadKey = $userInfo['type'];
            $payloadValue = $this->transformAdmin($user);
        } else {
            $payloadKey = 'supplier';
            $payloadValue = (new SupplierResource($user))->toArray(request());
        }

        return response()->json([
            'message' => 'Email verified successfully',
            $payloadKey => $payloadValue,
            'accessToken' => $token,
            'tokenType' => 'Bearer',
        ]);
    }

    /**
     * Get a user's profile picture by user ID
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProfilePicture(Request $request, $id = null)
    {
        // If no ID is provided, get the authenticated user's ID
        $userId = $id ?? $request->user()?->id;
        
        if (!$userId) {
            return response()->json(['message' => 'User ID is required'], 400);
        }

        // Try to find the user in both admin and supplier tables
        $user = Admin::find($userId) ?? Supplier::find($userId);
        
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Get the profile image path
        $profileImage = $user->profile_image;
        
        // Get the media URL using the parent class method
        $imageUrl = $this->mediaUrl($profileImage);
        
        if (empty($imageUrl)) {
            return response()->json([
                'message' => 'No profile picture found for this user',
                'profile_image' => null
            ], 404);
        }
        
        return response()->json([
            'message' => 'Profile picture retrieved successfully',
            'profile_image' => $imageUrl
        ]);
    }
}
