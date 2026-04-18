<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifikasi header Authorization: Bearer {access_token}, lalu set auth user untuk request ini.
 * Tidak menyentuh session; guard yang ditulis di sini bersifat per-request (stateless).
 */
class AuthenticateJwt
{
    public function __construct(private readonly JwtService $jwt)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');
        if (! str_starts_with(strtolower($header), 'bearer ')) {
            return $this->unauthorized('Token tidak ditemukan');
        }

        $token = trim(substr($header, 7));
        if ($token === '') {
            return $this->unauthorized('Token kosong');
        }

        $payload = $this->jwt->verifyAccessToken($token);
        if (! $payload || empty($payload['sub'])) {
            return $this->unauthorized('Token tidak valid atau kedaluwarsa');
        }

        /** @var User|null $user */
        $user = User::query()->find($payload['sub']);
        if (! $user) {
            return $this->unauthorized('User tidak ditemukan');
        }

        /* Inject user ke request; tidak pakai session. */
        $request->setUserResolver(fn () => $user);
        /* Simpan klaim untuk handler downstream (mis. cek role tanpa query DB). */
        $request->attributes->set('jwt', $payload);

        return $next($request);
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'message' => $message,
            'code' => 'unauthorized',
        ], 401);
    }
}
