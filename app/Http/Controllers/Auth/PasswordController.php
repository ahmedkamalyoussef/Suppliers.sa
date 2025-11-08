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

class PasswordController extends Controller
{
    protected function findUserByEmail($email)
    {
        // Check in buyers (users) table first
        $user = User::where('email', $email)->first();
        if ($user) {
            return ['user' => $user, 'type' => 'buyer'];
        }

        // Then check in suppliers table
        $user = Supplier::where('email', $email)->first();
        if ($user) {
            return ['user' => $user, 'type' => 'supplier'];
        }

        return null;
    }

    public function forgotPassword(Request $request)
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

        // Delete any existing OTP
        Otp::where('user_id', $user->id)->delete();
        
        // Generate new OTP
        $otp = Otp::create([
            'user_id' => $user->id,
            'otp' => rand(100000, 999999),
            'expires_at' => now()->addMinutes(10)
        ]);

        $user->notify(new OtpNotification($otp->otp));

        return response()->json([
            'message' => 'Password reset OTP has been sent to your email.'
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|numeric|digits:6',
            'password' => 'required|string|min:8|confirmed'
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
        $otp = Otp::where('user_id', $user->id)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) {
            return response()->json([
                'message' => 'Invalid or expired OTP'
            ], 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();
        $otp->delete();

        return response()->json([
            'message' => 'Password has been reset successfully'
        ]);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed|different:current_password'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'message' => 'Password changed successfully'
        ]);
    }
}