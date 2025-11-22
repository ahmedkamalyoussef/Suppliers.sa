<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\Admin;
use App\Models\Otp;
use App\Models\Supplier;
use App\Notifications\OtpNotification;
use App\Services\PasswordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class PasswordController extends Controller
{
    protected function findUserByEmail($email)
    {
        // Check in admins table first
        $user = Admin::where('email', $email)->first();
        if ($user) {
            return ['user' => $user, 'type' => 'admin'];
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
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // البحث عن المستخدم سواء Admin أو Supplier عبر الخدمة
        $userInfo = (new PasswordService)->findUserByEmail($request->email);

        if (! $userInfo) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $user = $userInfo['user'];

        // Generate OTP based on user type
        $otp = $userInfo['type'] === 'admin'
            ? Otp::generateForAdmin($user->id, $user->email)
            : Otp::generateForSupplier($user->id, $user->email);

        // إرسال OTP عبر الإيميل
        $user->notify(new OtpNotification($otp->otp));

        return response()->json([
            'message' => 'Password reset OTP has been sent to your email.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        // validation handled by ResetPasswordRequest

        // البحث عن المستخدم سواء Admin أو Supplier
        $userInfo = $this->findUserByEmail($request->email);

        if (! $userInfo) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $user = $userInfo['user'];

        // التحقق من OTP حسب نوع المستخدم
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
            return response()->json([
                'message' => 'Invalid or expired OTP',
            ], 422);
        }

        // تحديث كلمة المرور وحذف OTP بعد الاستخدام
        $user->password = Hash::make($request->password);
        $user->save();
        $otp->delete();

        return response()->json([
            'message' => 'Password has been reset successfully',
        ]);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        // validation handled by ChangePasswordRequest

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }
}
