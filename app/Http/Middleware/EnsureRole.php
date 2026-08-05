<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        try {
            $token   = JWTAuth::parseToken();
            $payload = $token->getPayload();
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid atau tidak ditemukan.',
                'error'   => ['auth' => [$e->getMessage()]],
                'status'  => 401,
            ], 401);
        }

        $tokenRole = $payload->get('role');

        if ($tokenRole !== $role) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke resource ini.',
                'error'   => ['role' => ["Endpoint ini khusus untuk {$role}."]],
                'status'  => 403,
            ], 403);
        }

        Auth::shouldUse($role);

        try {
            $user = Auth::guard($role)->user();
        } catch (\Throwable $e) {
            $user = null;
        }

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak ditemukan.',
                'error'   => ['auth' => ['Pengguna dengan token ini tidak ditemukan.']],
                'status'  => 401,
            ], 401);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}