<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\LoginRequest;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends BaseController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $email = $request->input('email');
        $ip = $request->ip() ?? 'unknown';

        $key = 'login-admin:' . md5($email . $ip);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return $this->sendError(
                ['auth' => ["Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik."]],
                'Rate limit exceeded.',
                429
            );
        }

        RateLimiter::hit($key, 60);

        $credentials = $request->only('email', 'password');

        $token = Auth::guard('admin')->attempt($credentials);

        if (! $token) {
            return $this->sendError(
                ['auth' => ['Email atau password salah.']],
                'Login gagal.',
                401
            );
        }

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        if ($admin->status !== 'active') {
            Auth::guard('admin')->logout();
            return $this->sendError(
                ['auth' => ['Akun admin nonaktif.']],
                'Login gagal.',
                403
            );
        }

        return $this->sendResponse([
            'admin' => [
                'id'    => $admin->id,
                'name'  => $admin->name,
                'email' => $admin->email,
                'status' => $admin->status,
            ],
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => Auth::guard('admin')->factory()->getTTL() * 60,
        ], 'Login admin berhasil.');
    }

    public function logout(): JsonResponse
    {
        try {
            JWTAuth::parseToken()->invalidate();
        } catch (\Throwable $e) {
            // token already invalid or missing — still report success
        }

        return $this->sendResponse(null, 'Logout berhasil.');
    }
}