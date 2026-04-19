# Envelope token — API mobile (sesi JWT)

Endpoint berikut (sukses `200`) mengembalikan bentuk **`data.user` + `data.token`** (satu string Base64 untuk kredensial; tidak ada `access_token` / `tokens` terpisah):

| Endpoint | Catatan |
| --- | --- |
| `POST /api/v1/auth/login` | Setelah password valid |
| `POST /api/v1/auth/refresh` | Rotasi refresh |
| `POST /api/v1/auth/reset-password` | Setelah OTP + password baru valid; disertai `data.message` |
| `PATCH /api/v1/me/password` | Setelah password lama benar; disertai `data.message`; semua refresh lama dicabut |

```json
{
  "data": {
    "user": { "...": "lihat UserResource / GET /me" },
    "token": "<satu string Base64>",
    "message": "opsional — teks status untuk pengguna"
  }
}
```

Kunci **`message`** di `data` opsional (hanya reset password & ganti password).

---

## Isi `data.token`

1. Ambil string `token` dari JSON respons.
2. **Decode Base64** (encoding standar, UTF-8).
3. Hasilnya adalah **teks JSON** dengan struktur tetap:

| Kunci | Tipe | Deskripsi |
| --- | --- | --- |
| `access` | string | JWT access — dipakai sebagai `Authorization: Bearer {access}` |
| `refresh` | string | Refresh token (`rt_…`) — body `POST /auth/refresh` dan `POST /auth/logout` |
| `token_type` | string | Biasanya `Bearer` |
| `access_expires_in` | number | Umur access token dalam **detik** |
| `refresh_expires_in` | number | Umur refresh token dalam **detik** |

Contoh JSON **sebelum** Base64:

```json
{
  "access": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "refresh": "rt_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "token_type": "Bearer",
  "access_expires_in": 900,
  "refresh_expires_in": 2592000
}
```

---

## Alur klien (ringkas)

1. **Login** → simpan `access` & `refresh` hasil decode ke secure storage.
2. Request API → header `Authorization: Bearer {access}`.
3. **401** → `POST /auth/refresh` dengan body `{ "refresh_token": "<refresh dari envelope terakhir>" }` → decode envelope baru dari `data.token`, ganti storage.
4. **Logout** → `POST /auth/logout` + header Bearer + body `{ "refresh_token": "..." }` (refresh tidak ada di envelope logout).

---

## Contoh decode

### Dart (dart:convert)

```dart
import 'dart:convert';

Map<String, dynamic> parseAuthEnvelope(dynamic responseData) {
  final tokenB64 = responseData['data']['token'] as String;
  final jsonStr = utf8.decode(base64Decode(tokenB64));
  return json.decode(jsonStr) as Map<String, dynamic>;
}

// Setelah login:
final env = parseAuthEnvelope(response.data);
final access = env['access'] as String;
final refresh = env['refresh'] as String;
```

### PHP (debug / skrip)

```php
$j = json_decode(base64_decode($data['data']['token'], true), true, 512, JSON_THROW_ON_ERROR);
// $j['access'], $j['refresh'], ...
```

### jq (dari curl, butuh `base64` di PATH)

```bash
echo "$TOKEN_B64" | base64 -d | jq .
```

---

## Catatan keamanan

- String `token` memuat **refresh plaintext**; transpor harus **HTTPS** di produksi.
- Setelah **refresh**, refresh lama **tidak valid**; selalu pakai envelope terbaru dari respons terakhir.
