<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Supplier;
use App\Models\Otp;
use App\Notifications\OtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    protected function findUserByEmail($email)
    {
        // Check in buyers (users) table first
        $user = User::where('email', $email)->first();
        if ($user) {
            return ['user' => $user, 'type' => 'buyer', 'guard' => 'web'];
        }

        // Then check in suppliers table
        $user = Supplier::where('email', $email)->first();
        if ($user) {
            return ['user' => $user, 'type' => 'supplier', 'guard' => 'supplier'];
        }

        return null;
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $userInfo = $this->findUserByEmail($request->email);
        
        if (!$userInfo) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        $user = $userInfo['user'];
        
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!$user->email_verified_at) {
            return response()->json([
                'message' => 'Email not verified'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Load relationships if they exist
        if ($userInfo['type'] === 'supplier' && method_exists($user, 'profile')) {
            $user->load('profile');
        }

        return response()->json([
            'message' => 'Login successful',
            'user_type' => $userInfo['type'],
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer'
        ]);
    }

    public function logout(Request $request)
    {
        // Try to delete the current access token via the user relation (preferred)
        try {
            if ($request->user() && method_exists($request->user(), 'currentAccessToken')) {
                $token = $request->user()->currentAccessToken();
                if ($token && method_exists($token, 'delete')) {
                    $token->delete();
                    return response()->json(['message' => 'Logged out successfully']);
                }
            }
        } catch (\Throwable $e) {
            // ignore and try fallback
        }

        // Fallback: delete by bearer token value (works even if currentAccessToken isn't available)
        $bearer = $request->bearerToken();
        if ($bearer) {
            $pat = PersonalAccessToken::findToken($bearer);
            if ($pat) {
                $pat->delete();
                return response()->json(['message' => 'Logged out successfully']);
            }
        }

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $userInfo = $this->findUserByEmail($request->email);

        if (!$userInfo) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        $user = $userInfo['user'];

        // Delete any existing OTP and generate new one depending on type
        if ($userInfo['type'] === 'buyer') {
            $otp = Otp::generateForUser($user->id, $user->email);
        } else {
            // For suppliers, use the supplier_id
            $otp = Otp::generateForSupplier($user->id, $user->email);
        }

        $user->notify(new OtpNotification($otp->otp));

        return response()->json([
            'message' => 'OTP has been sent to your email'
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|numeric|digits:6'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $userInfo = $this->findUserByEmail($request->email);

        if (!$userInfo) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        $user = $userInfo['user'];
        if ($userInfo['type'] === 'buyer') {
            $otp = Otp::where('user_id', $user->id)
                ->where('otp', $request->otp)
                ->where('expires_at', '>', now())
                ->first();
        } else {
            $otp = Otp::where('supplier_id', $user->id)
                ->where('otp', $request->otp)
                ->where('expires_at', '>', now())
                ->first();
        }

        if (!$otp) {
            return response()->json([
                'message' => 'Invalid or expired OTP'
            ], 422);
        }

        $user->email_verified_at = now();
        $user->save();
        $otp->delete();

        // Generate token after verification
        $token = $user->createToken('auth_token')->plainTextToken;

        // Load relationships if available
        if ($userInfo['type'] === 'supplier' && method_exists($user, 'profile')) {
            $user->load('profile');
        }

        return response()->json([
            'message' => 'Email verified successfully',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer'
        ]);
    }
}