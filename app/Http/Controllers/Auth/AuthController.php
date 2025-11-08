<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Supplier;
use App\Models\Otp;
use App\Notifications\OtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Find a user by email in buyers or suppliers tables.
     */
    protected function findUserByEmail(string $email)
    {
        $user = User::where('email', $email)->first();
        if ($user) return ['user' => $user, 'type' => 'buyer'];

        $user = Supplier::where('email', $email)->first();
        if ($user) return ['user' => $user, 'type' => 'supplier'];

        return null;
    }

    /**
     * Login user (buyer or supplier)
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $userInfo = $this->findUserByEmail($request->email);
        if (!$userInfo) return response()->json(['message' => 'User not found'], 404);

        $user = $userInfo['user'];
        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!$user->email_verified_at) {
            return response()->json(['message' => 'Email not verified'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

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

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $bearer = $request->bearerToken();
        if ($bearer) {
            $pat = PersonalAccessToken::findToken($bearer);
            if ($pat) $pat->delete();
        }
        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Send OTP to user email
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), ['email' => 'required|email']);
        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $userInfo = $this->findUserByEmail($request->email);
        if (!$userInfo) return response()->json(['message' => 'User not found'], 404);

        $user = $userInfo['user'];

        // Delete existing OTPs & generate new one
        $otp = $userInfo['type'] === 'buyer'
            ? Otp::generateForUser($user->id, $user->email)
            : Otp::generateForSupplier($user->id, $user->email);

        $user->notify(new OtpNotification($otp->otp));

        return response()->json(['message' => 'OTP has been sent to your email']);
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|numeric|digits:6'
        ]);
        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $userInfo = $this->findUserByEmail($request->email);
        if (!$userInfo) return response()->json(['message' => 'User not found'], 404);

        $user = $userInfo['user'];

        $otp = $userInfo['type'] === 'buyer'
            ? Otp::where('user_id', $user->id)->where('otp', $request->otp)->where('expires_at', '>', now())->first()
            : Otp::where('supplier_id', $user->id)->where('otp', $request->otp)->where('expires_at', '>', now())->first();

        if (!$otp) return response()->json(['message' => 'Invalid or expired OTP'], 422);

        $user->email_verified_at = now();
        $user->save();
        $otp->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

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
