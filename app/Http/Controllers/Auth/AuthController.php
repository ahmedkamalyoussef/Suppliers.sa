<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Supplier\SupplierResource;
use App\Models\Admin;
use App\Models\Otp;
use App\Models\Supplier;
use App\Notifications\OtpNotification;
use Illuminate\Http\Request;
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
            return ['user' => $user, 'type' => 'admin'];
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

        // Load relationships
        if ($userInfo['type'] === 'admin') {
            $user->load('permissions');
        } elseif ($userInfo['type'] === 'supplier' && method_exists($user, 'profile')) {
            $user->load('profile', 'branches');
        }

        $payloadKey = $userInfo['type'] === 'admin' ? 'admin' : 'supplier';
        $payloadValue = $userInfo['type'] === 'admin'
            ? $this->transformAdmin($user)
            : (new SupplierResource($user))->toArray(request());

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
        $otp = $userInfo['type'] === 'admin'
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
        $otp = $userInfo['type'] === 'admin'
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

        // Load relationships
        if ($userInfo['type'] === 'admin') {
            $user->load('permissions');
        } elseif (method_exists($user, 'profile')) {
            $user->load('profile', 'branches');
        }

        $payloadKey = $userInfo['type'] === 'admin' ? 'admin' : 'supplier';
        $payloadValue = $userInfo['type'] === 'admin'
            ? $this->transformAdmin($user)
            : (new SupplierResource($user))->toArray(request());

        return response()->json([
            'message' => 'Email verified successfully',
            $payloadKey => $payloadValue,
            'accessToken' => $token,
            'tokenType' => 'Bearer',
        ]);
    }
}
