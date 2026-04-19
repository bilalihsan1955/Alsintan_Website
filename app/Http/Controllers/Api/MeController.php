<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\JwtService;
use App\Support\ApiAuthEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Endpoint profil user saat ini. Semua method butuh middleware auth.jwt.
 */
class MeController extends Controller
{
    public function __construct(private readonly JwtService $jwt)
    {
    }

    /** GET /api/v1/me */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => (new UserResource($user))->toArray($request),
        ]);
    }

    /** PATCH /api/v1/me  — update nama & telepon. Avatar pakai endpoint terpisah (multipart). */
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
        ]);

        if (! empty($data)) {
            $user->fill($data)->save();
        }

        return response()->json([
            'data' => (new UserResource($user->fresh()))->toArray($request),
        ]);
    }

    /** POST /api/v1/me/avatar (multipart: file=avatar). */
    public function uploadAvatar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], /* 2MB */
        ]);

        /* Hapus avatar lama jika ada. */
        if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store("avatars/{$user->getKey()}", 'public');
        $user->avatar_path = $path;
        $user->save();

        return response()->json([
            'data' => (new UserResource($user->fresh()))->toArray($request),
        ]);
    }

    /** DELETE /api/v1/me/avatar */
    public function deleteAvatar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $user->avatar_path = null;
        $user->save();

        return response()->json([
            'data' => (new UserResource($user->fresh()))->toArray($request),
        ]);
    }

    /** PATCH /api/v1/me/password */
    public function updatePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed', 'different:current_password'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Password lama salah',
                'code' => 'invalid_current_password',
                'errors' => ['current_password' => ['Password lama salah']],
            ], 422);
        }

        $user->password = $data['password'];
        $user->save();

        /* Cabut semua refresh lalu terbitkan sesi baru (sama pola dengan reset password). */
        $this->jwt->revokeAllForUser($user->getKey());

        $fresh = $user->fresh();
        $tokens = $this->jwt->issueTokens(
            $fresh,
            $request->header('X-Device-Name') ?: $request->userAgent(),
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json(ApiAuthEnvelope::sessionPayload($fresh, $tokens, $request, [
            'message' => 'Password diperbarui',
        ]));
    }

    /** GET /api/v1/me/preferences */
    public function getPreferences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $user->preferences ?? $this->defaults(),
        ]);
    }

    /**
     * PUT /api/v1/me/preferences
     * Body: theme_mode (system|light|dark), language (id|en), + field tambahan akan diterima apa adanya.
     * Server = source of truth; klien menerapkan payload ini setelah response.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'theme_mode' => ['sometimes', 'string', Rule::in(['system', 'light', 'dark'])],
            'language' => ['sometimes', 'string', Rule::in(['id', 'en'])],
            'extras' => ['sometimes', 'array'], /* kantong bebas untuk preferensi masa depan */
        ]);

        $current = $user->preferences ?? $this->defaults();
        $merged = array_replace($current, $data);
        $user->preferences = $merged;
        $user->save();

        return response()->json([
            'data' => $merged,
        ]);
    }

    /** Nilai default preferensi. Jadikan satu tempat agar konsisten dengan klien. */
    private function defaults(): array
    {
        return [
            'theme_mode' => 'system',
            'language' => 'id',
        ];
    }
}
