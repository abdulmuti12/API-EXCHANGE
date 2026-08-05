<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal.',
                    'error'   => $e->errors(),
                    'status'  => 422,
                ], 422);
            }

            if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak valid.',
                    'error'   => ['auth' => [$e->getMessage()]],
                    'status'  => 401,
                ], 401);
            }

            if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token sudah kadaluarsa.',
                    'error'   => ['auth' => [$e->getMessage()]],
                    'status'  => 401,
                ], 401);
            }

            if ($e instanceof \Tymon\JWTAuth\Exceptions\JWTException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak ditemukan.',
                    'error'   => ['auth' => [$e->getMessage()]],
                    'status'  => 401,
                ], 401);
            }

            if ($e instanceof AccessDeniedHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak.',
                    'error'   => ['role' => [$e->getMessage() ?: 'Anda tidak memiliki akses ke resource ini.']],
                    'status'  => 403,
                ], 403);
            }

            if ($e instanceof NotFoundHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource tidak ditemukan.',
                    'error'   => ['url' => [$request->path()]],
                    'status'  => 404,
                ], 404);
            }

            if ($e instanceof HttpExceptionInterface) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Terjadi kesalahan.',
                    'error'   => [],
                    'status'  => $e->getStatusCode(),
                ], $e->getStatusCode());
            }

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan pada server.',
                'error'   => config('app.debug') ? ['exception' => get_class($e)] : [],
                'status'  => 500,
            ], 500);
        });
    })->create();