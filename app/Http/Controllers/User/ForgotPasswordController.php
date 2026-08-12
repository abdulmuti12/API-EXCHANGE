<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\User\ResetPasswordRequest;
use App\Http\Requests\User\SendOtpRequest;
use App\Http\Requests\User\VerifyOtpRequest;
use App\Mail\SendOtpMail;
use App\Models\PasswordOtp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ForgotPasswordController extends BaseController
{
    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $email = $request->validated('email');

        $key = 'otp-send:' . $email;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return $this->sendError(
                ['email' => ['Terlalu banyak permintaan. Tunggu beberapa menit lagi.']],
                'Rate limit terlampaui.',
                429
            );
        }

        RateLimiter::hit($key, 60);

        /** @var User $user */
        $user = User::where('email', $email)->first();

        $otp = Str::random(6);

        PasswordOtp::where('user_id', $user->id)->delete();

        PasswordOtp::create([
            'user_id'  => $user->id,
            'otp'      => $otp,
            'expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($email)->send(new SendOtpMail($otp, $user->name));

        return $this->sendResponse(null, 'Kode OTP telah dikirim ke email Anda.');
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $otp   = $request->validated('otp');

        $user = User::where('email', $email)->first();

        $otpRecord = PasswordOtp::where('user_id', $user->id)
            ->where('otp', $otp)
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->first();

        if (! $otpRecord) {
            return $this->sendError(
                ['otp' => ['Kode OTP salah atau telah kedaluwarsa.']],
                'Verifikasi OTP gagal.',
                422
            );
        }

        return $this->sendResponse([
            'email' => $user->email,
        ], 'OTP berhasil diverifikasi. Silakan buat password baru.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $otp   = $request->validated('otp');
        $password = $request->validated('password');

        $user = User::where('email', $email)->first();

        $otpRecord = PasswordOtp::where('user_id', $user->id)
            ->where('otp', $otp)
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->first();

        if (! $otpRecord) {
            return $this->sendError(
                ['otp' => ['Kode OTP salah atau telah kedaluwarsa.']],
                'Reset password gagal.',
                422
            );
        }

        $user->update([
            'password' => bcrypt($password),
        ]);

        PasswordOtp::where('user_id', $user->id)->delete();

        return $this->sendResponse(null, 'Password berhasil diubah. Silakan login dengan password baru.');
    }
}
