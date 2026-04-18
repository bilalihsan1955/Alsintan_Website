<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya izinkan user ber-role `admin`. Operator akan 403 (web) atau JSON forbidden (api).
 * Gunakan alias `admin`.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Tidak terautentikasi', 'code' => 'unauthorized'], 401);
            }

            return redirect()->guest(route('login'));
        }

        if (($user->role ?? 'operator') !== 'admin') {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Hanya admin yang bisa mengakses', 'code' => 'forbidden'], 403);
            }

            abort(403, 'Hanya admin yang bisa mengakses halaman ini.');
        }

        return $next($request);
    }
}
