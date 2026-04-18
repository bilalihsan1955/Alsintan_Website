<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representasi user publik (aman untuk dikirim ke klien). Konsisten untuk login, refresh, dan /me.
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var \App\Models\User $this */
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'role' => $this->role ?? 'operator',
            'preferences' => $this->preferences ?? (object) [],
            'email_verified_at' => optional($this->email_verified_at)?->toAtomString(),
            'created_at' => optional($this->created_at)?->toAtomString(),
        ];
    }
}
