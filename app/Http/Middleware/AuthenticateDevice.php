<?php

namespace App\Http\Middleware;

use App\Models\Tractor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auth untuk device IoT (ESP32/RPi). Header: X-Device-Token: {plaintext}
 * Token disimpan sebagai hash SHA-256 di tractors.api_token_hash.
 * Berhasil: inject $request->attributes['tractor'] supaya controller tinggal pakai.
 */
class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = (string) $request->header('X-Device-Token', '');
        if ($plain === '') {
            return $this->unauthorized('Header X-Device-Token wajib diisi');
        }

        $hash = hash('sha256', $plain);
        /** @var Tractor|null $tractor */
        $tractor = Tractor::query()->where('api_token_hash', $hash)->first();
        if (! $tractor) {
            return $this->unauthorized('Device token tidak dikenal');
        }

        $tractor->forceFill(['api_token_last_used_at' => Carbon::now()])->saveQuietly();

        $request->attributes->set('tractor', $tractor);

        return $next($request);
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'message' => $message,
            'code' => 'device_unauthorized',
        ], 401);
    }
}
