<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Carbon\Carbon;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Str;

/**
 * Terbitkan dan verifikasi pasangan access+refresh token untuk klien mobile.
 * Access token = JWT (HS256) yang stateless dan pendek (default 15 menit).
 * Refresh token = string acak panjang; hash-nya disimpan di DB sehingga bisa di-revoke/rotasi.
 */
class JwtService
{
    public function issueTokens(User $user, ?string $deviceName = null, ?string $ip = null, ?string $userAgent = null): array
    {
        $accessTtl = (int) config('jwt.access_ttl');
        $refreshTtl = (int) config('jwt.refresh_ttl');

        $now = Carbon::now();
        $accessExp = $now->copy()->addSeconds($accessTtl);
        $refreshExp = $now->copy()->addSeconds($refreshTtl);

        $accessPayload = [
            'iss' => (string) config('jwt.issuer'),
            'aud' => (string) config('jwt.audience'),
            'sub' => (string) $user->getKey(),
            'iat' => $now->getTimestamp(),
            'nbf' => $now->getTimestamp(),
            'exp' => $accessExp->getTimestamp(),
            'jti' => (string) Str::uuid(),
            'typ' => 'access',
            'role' => $user->role ?? 'operator',
        ];

        $accessToken = JWT::encode($accessPayload, (string) config('jwt.secret'), (string) config('jwt.algo'));

        /* Refresh token: plaintext hanya dikembalikan ke klien sekali; DB menyimpan hash SHA-256. */
        $refreshPlain = 'rt_'.Str::random(64);
        RefreshToken::query()->create([
            'user_id' => $user->getKey(),
            'token_hash' => hash('sha256', $refreshPlain),
            'device_name' => $deviceName,
            'ip_address' => $ip,
            'user_agent' => $userAgent ? Str::limit($userAgent, 250, '') : null,
            'expires_at' => $refreshExp,
        ]);

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $accessTtl,
            'refresh_token' => $refreshPlain,
            'refresh_expires_in' => $refreshTtl,
        ];
    }

    /**
     * Verifikasi access token. Mengembalikan payload sebagai array, atau null jika tidak valid.
     * Throwable dari library ditelan — cukup balas null agar middleware dapat menentukan response 401.
     */
    public function verifyAccessToken(string $token): ?array
    {
        try {
            JWT::$leeway = (int) config('jwt.leeway');
            $decoded = JWT::decode($token, new Key((string) config('jwt.secret'), (string) config('jwt.algo')));
            $data = (array) $decoded;
            if (($data['typ'] ?? null) !== 'access') {
                return null;
            }

            return $data;
        } catch (ExpiredException|SignatureInvalidException $e) {
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Rotasi refresh token: validasi plaintext lama, revoke record lama, terbitkan pasangan baru.
     * Return array [tokens, user] atau null bila invalid / expired / revoked.
     */
    public function rotateRefreshToken(string $refreshPlain, ?string $ip = null, ?string $userAgent = null): ?array
    {
        $hash = hash('sha256', $refreshPlain);
        /** @var RefreshToken|null $record */
        $record = RefreshToken::query()->where('token_hash', $hash)->first();
        if (! $record || $record->revoked_at !== null || $record->expires_at <= Carbon::now()) {
            /* Reuse detection sederhana: jika token sudah di-revoke tapi dicoba lagi, revoke semua
               token user untuk keamanan (dipaksa login ulang di semua device). */
            if ($record && $record->revoked_at !== null) {
                RefreshToken::query()->where('user_id', $record->user_id)->whereNull('revoked_at')->update(['revoked_at' => Carbon::now()]);
            }

            return null;
        }

        $user = $record->user;
        if (! $user) {
            return null;
        }

        $record->revoked_at = Carbon::now();
        $record->last_used_at = Carbon::now();
        $record->save();

        $tokens = $this->issueTokens($user, $record->device_name, $ip, $userAgent);

        return ['tokens' => $tokens, 'user' => $user];
    }

    public function revokeRefreshToken(string $refreshPlain): bool
    {
        $hash = hash('sha256', $refreshPlain);
        $affected = RefreshToken::query()
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Carbon::now()]);

        return $affected > 0;
    }

    public function revokeAllForUser(int|string $userId): int
    {
        return RefreshToken::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Carbon::now()]);
    }
}
