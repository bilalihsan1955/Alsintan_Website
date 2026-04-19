<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmailOtp;
use App\Models\User;
use App\Services\JwtService;
use App\Support\ApiAuthEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function __construct(private readonly JwtService $jwt)
    {
    }

    /**
     * POST /api/v1/auth/login
     * Body: email, password, device_name (opsional).
     * Rate limit: 5 percobaan gagal per email+IP per menit.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $throttleKey = 'login:'.strtolower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'message' => 'Terlalu banyak percobaan. Coba lagi dalam '.$seconds.' detik.',
                'code' => 'too_many_requests',
                'retry_after' => $seconds,
            ], 429);
        }

        /** @var User|null $user */
        $user = User::query()->where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            return response()->json([
                'message' => 'Email atau password salah',
                'code' => 'invalid_credentials',
            ], 401);
        }

        RateLimiter::clear($throttleKey);

        $tokens = $this->jwt->issueTokens(
            $user,
            $data['device_name'] ?? $request->userAgent(),
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json(ApiAuthEnvelope::sessionPayload($user, $tokens, $request));
    }

    /**
     * POST /api/v1/auth/refresh
     * Body: refresh_token.
     * Rotasi: refresh lama di-revoke, klien menerima pasangan baru.
     */
    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $result = $this->jwt->rotateRefreshToken($data['refresh_token'], $request->ip(), $request->userAgent());
        if (! $result) {
            return response()->json([
                'message' => 'Refresh token tidak valid atau kedaluwarsa',
                'code' => 'invalid_refresh_token',
            ], 401);
        }

        return response()->json(ApiAuthEnvelope::sessionPayload($result['user'], $result['tokens'], $request));
    }

    /**
     * POST /api/v1/auth/logout
     * Body: refresh_token (agar device tersebut langsung tidak bisa refresh lagi).
     * Memerlukan Authorization access token juga (untuk tahu user yang memicu).
     */
    public function logout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'refresh_token' => ['nullable', 'string'],
        ]);

        if (! empty($data['refresh_token'])) {
            $this->jwt->revokeRefreshToken($data['refresh_token']);
        }

        return response()->json([
            'data' => [
                'message' => 'Logout berhasil',
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/forgot-password
     * Body: email. Selalu 200 agar tidak membocorkan keberadaan email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
        ]);

        /* Rate limit per email+ip: 3 request per jam. */
        $throttleKey = 'otp:'.strtolower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'message' => 'Terlalu sering meminta OTP. Coba lagi dalam '.$seconds.' detik.',
                'code' => 'too_many_requests',
                'retry_after' => $seconds,
            ], 429);
        }
        RateLimiter::hit($throttleKey, 3600);

        $user = User::query()->where('email', $data['email'])->first();
        if ($user) {
            $code = (string) random_int(100000, 999999);
            EmailOtp::query()->create([
                'email' => $user->email,
                'purpose' => 'password_reset',
                'code_hash' => hash('sha256', $code),
                'expires_at' => Carbon::now()->addMinutes(15),
            ]);

            /* TODO(product-owner): konfigurasi SMTP produksi. Untuk dev log driver sudah cukup. */
            try {
                Mail::raw("Kode OTP reset password Anda: {$code}\nBerlaku 15 menit. Jika bukan Anda, abaikan pesan ini.", function ($m) use ($user) {
                    $m->to($user->email)->subject('Kode OTP Reset Password');
                });
            } catch (\Throwable $e) {
                /* Jangan bocor ke klien; cukup log (biar tidak crash jika mail belum dikonfigurasi). */
                \Log::warning('Gagal kirim OTP: '.$e->getMessage());
            }
        }

        return response()->json([
            'data' => [
                'message' => 'Jika email terdaftar, kami telah mengirim kode OTP ke email tersebut.',
                'otp_ttl_seconds' => 15 * 60,
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/reset-password
     * Body: email, code (6 digit), password (baru).
     * Sukses: sesi baru (envelope) + pesan — tidak perlu login ulang di mobile.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();
        if (! $user) {
            return response()->json([
                'message' => 'Kode OTP salah atau kedaluwarsa',
                'code' => 'invalid_otp',
            ], 422);
        }

        /** @var EmailOtp|null $otp */
        $otp = EmailOtp::query()
            ->where('email', $data['email'])
            ->where('purpose', 'password_reset')
            ->whereNull('used_at')
            ->where('expires_at', '>', Carbon::now())
            ->orderByDesc('id')
            ->first();

        if (! $otp) {
            return response()->json([
                'message' => 'Kode OTP salah atau kedaluwarsa',
                'code' => 'invalid_otp',
            ], 422);
        }

        if ($otp->attempts >= 5) {
            return response()->json([
                'message' => 'Kode OTP sudah melewati batas percobaan. Minta kode baru.',
                'code' => 'otp_locked',
            ], 422);
        }

        if (! hash_equals($otp->code_hash, hash('sha256', $data['code']))) {
            $otp->increment('attempts');
            return response()->json([
                'message' => 'Kode OTP salah atau kedaluwarsa',
                'code' => 'invalid_otp',
            ], 422);
        }

        $otp->used_at = Carbon::now();
        $otp->save();

        $user->password = $data['password'];
        $user->save();

        $this->jwt->revokeAllForUser($user->getKey());

        $userFresh = $user->fresh();
        $tokens = $this->jwt->issueTokens(
            $userFresh,
            $request->userAgent(),
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json(ApiAuthEnvelope::sessionPayload($userFresh, $tokens, $request, [
            'message' => 'Password berhasil direset. Sesi baru aktif.',
        ]));
    }
}
