<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\User\LoginRequest;
use App\Http\Requests\User\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends BaseController
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = bcrypt($data['password']);
        $data['status']   = 'active';

        $user = User::create($data);

        $token = Auth::guard('user')->login($user);

        return $this->sendResponse([
            'user' => [
                'id'           => $user->id,
                'name'         => $user->name,
                'email'        => $user->email,
                'phone_number' => $user->phone_number,
                'address'      => $user->address,
                'status'       => $user->status,
            ],
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => Auth::guard('user')->factory()->getTTL() * 60,
        ], 'Registrasi berhasil.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $email = $request->input('email');
        $ip = $request->ip() ?? 'unknown';

        $key = 'login-user:' . md5($email . $ip);
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

        $token = Auth::guard('user')->attempt($credentials);

        if (! $token) {
            return $this->sendError(
                ['auth' => ['Email atau password salah.']],
                'Login gagal.',
                401
            );
        }

        /** @var User $user */
        $user = Auth::guard('user')->user();

        if ($user->status !== 'active') {
            Auth::guard('user')->logout();
            $message = $user->status === 'banned' ? 'Akun dibanned.' : 'Akun nonaktif.';
            return $this->sendError(['auth' => [$message]], 'Login gagal.', 403);
        }

        return $this->sendResponse([
            'user' => [
                'id'           => $user->id,
                'name'         => $user->name,
                'email'        => $user->email,
                'phone_number' => $user->phone_number,
                'address'      => $user->address,
                'status'       => $user->status,
            ],
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => Auth::guard('user')->factory()->getTTL() * 60,
        ], 'Login berhasil.');
    }

    public function logout(): JsonResponse
    {
        try {
            JWTAuth::parseToken()->invalidate();
        } catch (\Throwable $e) {
            // ignore
        }

        return $this->sendResponse(null, 'Logout berhasil.');
    }
}