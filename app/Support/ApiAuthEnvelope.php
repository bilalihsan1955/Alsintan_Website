<?php

namespace App\Support;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use JsonException;

/**
 * Respons API mobile: `data.user` + `data.token` (Base64 JSON berisi access/refresh/TTL).
 * Dipakai login, refresh, reset password, dan ganti password (PATCH /me/password).
 */
final class ApiAuthEnvelope
{
    /**
     * @param  array<string, mixed>  $jwtTokens  Keluaran JwtService::issueTokens
     * @param  array<string, mixed>  $mergeIntoData  Digabung ke `data` setelah `user` & `token` (mis. `message`).
     * @return array{data: array<string, mixed>}
     */
    public static function sessionPayload(User $user, array $jwtTokens, Request $request, array $mergeIntoData = []): array
    {
        $core = [
            'user' => (new UserResource($user))->toArray($request),
            'token' => self::encode($jwtTokens),
        ];

        return [
            'data' => array_merge($core, $mergeIntoData),
        ];
    }

    /**
     * @param  array<string, mixed>  $jwtTokens
     *
     * @throws JsonException
     */
    public static function encode(array $jwtTokens): string
    {
        $inner = [
            'access' => $jwtTokens['access_token'],
            'refresh' => $jwtTokens['refresh_token'],
            'token_type' => $jwtTokens['token_type'],
            'access_expires_in' => $jwtTokens['expires_in'],
            'refresh_expires_in' => $jwtTokens['refresh_expires_in'],
        ];

        return base64_encode(json_encode($inner, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
