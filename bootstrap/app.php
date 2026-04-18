<?php

use App\Http\Middleware\AuthenticateDevice;
use App\Http\Middleware\AuthenticateJwt;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.jwt' => AuthenticateJwt::class,
            'auth.device' => AuthenticateDevice::class,
            'admin' => EnsureAdmin::class,
        ]);

        /* Redirect guest web ke halaman /login. */
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /* Format error JSON konsisten untuk semua endpoint /api/*. */
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null; /* biarkan default (render HTML) untuk web. */
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => 'Data tidak valid',
                    'code' => 'validation_error',
                    'errors' => $e->errors(),
                ], 422);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'message' => 'Tidak terautentikasi',
                    'code' => 'unauthorized',
                ], 401);
            }

            if ($e instanceof NotFoundHttpException) {
                return response()->json([
                    'message' => 'Resource tidak ditemukan',
                    'code' => 'not_found',
                ], 404);
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                return response()->json([
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Terjadi kesalahan',
                    'code' => match (true) {
                        $status === 403 => 'forbidden',
                        $status === 429 => 'too_many_requests',
                        default => 'http_error',
                    },
                ], $status, $e->getHeaders());
            }

            if (! config('app.debug')) {
                return response()->json([
                    'message' => 'Terjadi kesalahan pada server',
                    'code' => 'server_error',
                ], 500);
            }

            return null; /* debug: kembalikan default trace. */
        });
    })->create();
