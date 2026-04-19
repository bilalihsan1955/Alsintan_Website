# Alsintan — API untuk Flutter

Dokumen ini kontrak API yang dipakai aplikasi mobile (Flutter). Semua endpoint di bawah **sudah ter-implementasi di backend Laravel** dan siap dipakai tim Flutter.

> **Pelacakan traktor di peta OSM (mobile):** [`README-LENGKAP-MOBILE-UNIT-PETA-GPS.md`](./README-LENGKAP-MOBILE-UNIT-PETA-GPS.md). **Strategic & indeks OpenAPI mobile:** [`api/mobile/README.md`](./api/mobile/README.md).

- **Base URL dev**: `http://localhost:8000/api/v1`
- **Base URL prod**: `https://<domain>/api/v1`
- **Auth mobile**: JWT bearer (`Authorization: Bearer {access}` dari envelope — lihat §2 & [`auth-token-envelope.md`](./auth-token-envelope.md))
- **Format semua response**: JSON UTF-8. Error memakai skema konsisten (lihat §4).
- **Timezone**: timestamp dikirim dalam ISO-8601 UTC (`Z`). Konversi ke lokal di klien.

---

## 1) Daftar endpoint (ringkas)

| Grup | Method | Path | Auth | Guna |
| --- | --- | --- | --- | --- |
| Auth | POST | `/auth/login` | — | Tukar email+password → access & refresh token |
| Auth | POST | `/auth/refresh` | — | Perbarui access token (rotasi refresh) |
| Auth | POST | `/auth/logout` | Bearer | Cabut refresh token yang dikirim |
| Auth | POST | `/auth/forgot-password` | — | Kirim OTP ke email |
| Auth | POST | `/auth/reset-password` | — | Tukar OTP + password baru |
| Me | GET | `/me` | Bearer | Profil + role + preferensi |
| Me | PATCH | `/me` | Bearer | Ubah nama / telepon |
| Me | POST | `/me/avatar` | Bearer | Upload foto (multipart) |
| Me | DELETE | `/me/avatar` | Bearer | Hapus foto |
| Me | PATCH | `/me/password` | Bearer | Ganti password |
| Me | GET | `/me/preferences` | Bearer | Ambil `theme_mode` & `language` |
| Me | PUT | `/me/preferences` | Bearer | Simpan preferensi |
| Strategic | GET | `/strategic/kpi` | Bearer | KPI dashboard (impact, scorecards) |
| Strategic | GET | `/strategic/coverage` | Bearer | Cakupan tanam harian |
| Strategic | GET | `/strategic/fuel-efficiency` | Bearer | Efisiensi BBM harian |
| Strategic | GET | `/performance/groups` | Bearer | Rapor kelompok tani |
| Alerts | GET | `/alerts/geofence` | Bearer | Event keluar/masuk zona |
| Alerts | GET | `/alerts/anomalies` | Bearer | Daftar anomali |
| Alerts | PATCH | `/alerts/anomalies/{id}/resolve` | Bearer (admin) | Tandai selesai |
| Utilisasi | GET | `/utilization` | Bearer | Utilisasi alat harian |
| Maintenance | GET | `/maintenance/plans` | Bearer | Rencana servis |
| Maintenance | GET | `/maintenance/records` | Bearer | Riwayat servis |
| Traktor | GET | `/tractors/latest-positions` | Bearer | Posisi terbaru seluruh alat |
| Traktor | GET | `/tractors/{id}/route-history` | Bearer | Jejak GPS alat |

> Akun Flutter dibuat admin (`php artisan user:create`). Tidak ada self-register di aplikasi ini.

---

## 2) Alur auth (mobile)

1. `POST /auth/login` → respons berisi **`data.user`** + **`data.token`** (satu string Base64). **Decode** `data.token` → JSON berisi `access`, `refresh`, `token_type`, `access_expires_in`, `refresh_expires_in` (lihat [`auth-token-envelope.md`](./auth-token-envelope.md)).
2. Simpan **`access`** dan **`refresh`** hasil decode ke secure storage (§7).
3. Tiap request: `Authorization: Bearer {access}`.
4. **401** → `POST /auth/refresh` dengan body `{ "refresh_token": "<refresh>" }` → respons lagi `data.user` + `data.token`; decode dan **ganti** storage (refresh lama revoked).
5. Reuse refresh yang sudah di-revoke → server dapat memaksa logout semua device.
6. **Logout** → `POST /auth/logout` + Bearer + body `{ "refresh_token": "<refresh>" }`.

---

## 3) Contract masing-masing endpoint (detail)

### 3.1 `POST /auth/login`
Request:
```json
{ "email": "admin@alsintan.id", "password": "rahasia123" }
```
Response `200` — di `data` **hanya** `user` + **`token`** (satu string; tidak ada objek `tokens` / field `access_token` terpisah):

```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "Admin Alsintan",
      "email": "admin@alsintan.id",
      "phone": null,
      "avatar_url": null,
      "role": "admin",
      "preferences": { "theme_mode": "system", "language": "id" },
      "email_verified_at": null,
      "created_at": "2026-04-18T02:11:45.000000Z"
    },
    "token": "ZXlKaGJHY2lPaUpJVXpJMU5pSjkuLi4="
  }
}
```

Decode `data.token` (Base64 → JSON) untuk mendapat `access`, `refresh`, dll. — lihat [`auth-token-envelope.md`](./auth-token-envelope.md).

Error: `422` (field salah), `401` (email/password salah), `429` (rate limit: 5/menit per email+IP).

### 3.2 `POST /auth/refresh`
```json
{ "refresh_token": "rt_8f2c..." }
```
Response `200` = **sama seperti login**: `{ "data": { "user", "token" } }` dengan `token` envelope baru. Server menerbitkan refresh **baru** dan men-revoke yang lama.

### 3.3 `POST /auth/logout`
Header `Authorization: Bearer {access}`. Body:
```json
{ "refresh_token": "rt_8f2c..." }
```
Response `200`: `{ "data": { "message": "Logout berhasil" } }`.

### 3.4 `POST /auth/forgot-password`
```json
{ "email": "admin@alsintan.id" }
```
Selalu `200` walau email tidak terdaftar (enumeration protection). Rate limit: 3/jam/email. Body sukses: `{ "data": { "message": "...", "otp_ttl_seconds": 900 } }`.

### 3.5 `POST /auth/reset-password`
```json
{
  "email": "admin@alsintan.id",
  "code": "123456",
  "password": "passwordBaru1",
  "password_confirmation": "passwordBaru1"
}
```
Field OTP di API bernama **`code`** (bukan `otp`). Sukses `200` = **sama seperti login**: `data.user` + `data.token` (envelope baru) + opsional `data.message` — decode `token` dan simpan `access`/`refresh` di klien (sesi baru, tidak wajib login ulang).

### 3.6 `GET /me`
Return user object (skema sama dengan `user` pada login).

### 3.7 `PATCH /me`
```json
{ "name": "Nama Baru", "phone": "081234567890" }
```
Kedua field optional. Return user object terbaru.

### 3.8 `POST /me/avatar` (multipart)
Field: `avatar` (jpg/jpeg/png/webp, max 2 MB). Return user object.

### 3.9 `DELETE /me/avatar`
Response `200` + `{ "data": { ...user } }` (profil terbaru tanpa avatar).

### 3.10 `PATCH /me/password`
```json
{ "current_password": "lama", "password": "baru8char", "password_confirmation": "baru8char" }
```
Sukses `200` = **`data.user` + `data.token`** + `data.message` (semua refresh lama dicabut, sesi baru). Klien harus **mengganti** token tersimpan dari envelope yang baru (sama seperti setelah login).

### 3.11 `GET /me/preferences`
```json
{ "data": { "theme_mode": "system", "language": "id" } }
```

### 3.12 `PUT /me/preferences`
```json
{ "theme_mode": "dark", "language": "id" }
```
Nilai valid: `theme_mode` ∈ {`system`, `light`, `dark`}, `language` ∈ {`id`, `en`}.

> **Tentang sinkronisasi tema**: server adalah source of truth. Setelah login, klien ambil `preferences.theme_mode` dari `/me` lalu terapkan ke app. Saat user mengganti tema di app, klien simpan cache lokal + `PUT /me/preferences`.

### 3.13 Endpoint bisnis (read-only untuk operator)
Semua menerima query opsional `from=YYYY-MM-DD&to=YYYY-MM-DD` dan sebagian menerima `tractor_id`. Response memakai skema list:
```json
{ "data": [ ... ], "meta": { "current_page": 1, "per_page": 20, "total": 0, "last_page": 1 } }
```
Field dalam `data[i]` mengikuti kontrak terbaru; untuk **peta OSM & lintasan** lihat [`README-LENGKAP-MOBILE-UNIT-PETA-GPS.md`](./README-LENGKAP-MOBILE-UNIT-PETA-GPS.md). Postman/Swagger: impor **`docs/openapi.yaml`** + **`docs/api/mobile/openapi.yaml`** (dua file).

---

## 4) Format error

```json
// 401
{ "message": "Token tidak valid atau kedaluwarsa", "code": "unauthorized" }

// 403
{ "message": "Hanya admin yang bisa mengakses", "code": "forbidden" }

// 404
{ "message": "Resource tidak ditemukan", "code": "not_found" }

// 422 (validation)
{
  "message": "Validasi gagal",
  "code": "validation_failed",
  "errors": { "email": ["Email wajib diisi."] }
}

// 429
{ "message": "Terlalu banyak percobaan. Coba lagi nanti.", "code": "too_many_requests" }

// 500
{ "message": "Kesalahan server", "code": "server_error" }
```

Klien Flutter disarankan punya interceptor yang:
- Deteksi `401` → coba refresh → retry.
- Tampilkan `errors` (422) di form field.
- Tampilkan `message` (4xx/5xx selain 422) di snackbar.

---

## 5) Contoh curl

```bash
# 1. Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@alsintan.id","password":"rahasia123"}'

# 2. GET me
curl http://localhost:8000/api/v1/me \
  -H "Authorization: Bearer {ACCESS_TOKEN}"

# 3. PUT preferences
curl -X PUT http://localhost:8000/api/v1/me/preferences \
  -H "Authorization: Bearer {ACCESS_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"theme_mode":"dark","language":"id"}'

# 4. Refresh
curl -X POST http://localhost:8000/api/v1/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"refresh_token":"rt_xxx"}'

# 5. Upload avatar
curl -X POST http://localhost:8000/api/v1/me/avatar \
  -H "Authorization: Bearer {ACCESS_TOKEN}" \
  -F "avatar=@/path/to/photo.jpg"
```

---

## 6) Contoh Dart (dio)

```dart
// pubspec: dio: ^5.0.0, flutter_secure_storage: ^9.0.0

import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

final storage = const FlutterSecureStorage();
final dio = Dio(BaseOptions(
  baseUrl: 'http://10.0.2.2:8000/api/v1', // emulator Android → host
  connectTimeout: const Duration(seconds: 10),
  receiveTimeout: const Duration(seconds: 15),
  headers: {'Accept': 'application/json'},
));

/// Decode `data.token` (Base64 → JSON) dari login / refresh.
Map<String, dynamic> parseAuthEnvelope(Map<String, dynamic> body) {
  final b64 = body['data']['token'] as String;
  final inner = utf8.decode(base64Decode(b64));
  return json.decode(inner) as Map<String, dynamic>;
}

Future<void> persistFromEnvelope(Map<String, dynamic> body) async {
  final env = parseAuthEnvelope(body);
  await storage.write(key: 'access_token', value: env['access'] as String);
  await storage.write(key: 'refresh_token', value: env['refresh'] as String);
}

Future<void> attachInterceptor() async {
  dio.interceptors.add(InterceptorsWrapper(
    onRequest: (options, handler) async {
      final token = await storage.read(key: 'access_token');
      if (token != null) options.headers['Authorization'] = 'Bearer $token';
      handler.next(options);
    },
    onError: (e, handler) async {
      final isUnauth = e.response?.statusCode == 401 && e.requestOptions.path != '/auth/refresh';
      if (!isUnauth) return handler.next(e);

      final rt = await storage.read(key: 'refresh_token');
      if (rt == null) return handler.next(e);

      try {
        final r = await dio.post('/auth/refresh', data: {'refresh_token': rt});
        await persistFromEnvelope(Map<String, dynamic>.from(r.data as Map));

        final env = parseAuthEnvelope(Map<String, dynamic>.from(r.data as Map));
        final retry = e.requestOptions;
        retry.headers['Authorization'] = 'Bearer ${env['access']}';
        final resp = await dio.fetch(retry);
        return handler.resolve(resp);
      } catch (_) {
        await storage.deleteAll();
        return handler.next(e);
      }
    },
  ));
}

// --- login ---
Future<Map<String, dynamic>> login(String email, String password) async {
  final r = await dio.post('/auth/login', data: {'email': email, 'password': password});
  await persistFromEnvelope(Map<String, dynamic>.from(r.data as Map));
  return (r.data['data']['user'] as Map).cast<String, dynamic>();
}

// --- get profile ---
Future<Map<String, dynamic>> fetchMe() async {
  final r = await dio.get('/me');
  return r.data['data'];
}

// --- update preferences ---
Future<void> putPreferences({required String themeMode, String language = 'id'}) async {
  await dio.put('/me/preferences', data: {'theme_mode': themeMode, 'language': language});
}

// --- upload avatar ---
Future<void> uploadAvatar(String filePath) async {
  final form = FormData.fromMap({
    'avatar': await MultipartFile.fromFile(filePath, filename: 'avatar.jpg'),
  });
  await dio.post('/me/avatar', data: form);
}

// --- logout ---
Future<void> logout() async {
  final rt = await storage.read(key: 'refresh_token');
  if (rt != null) {
    try { await dio.post('/auth/logout', data: {'refresh_token': rt}); } catch (_) {}
  }
  await storage.deleteAll();
}
```

---

## 7) Rekomendasi klien

- **Secure storage**: setelah decode envelope, simpan nilai `access` & `refresh` (boleh tetap pakai key internal `access_token` / `refresh_token`) di `flutter_secure_storage`.
- **Refresh race**: gunakan singleton lock agar tidak dua request paralel sama-sama refresh.
- **Base URL**: simpan di `.env` dart; emulator Android pakai `http://10.0.2.2:8000` untuk akses host `localhost`.
- **Sinkron tema**:
  - Saat `GET /me` sukses → replace cache lokal.
  - Saat user ubah tema di app → `PUT /me/preferences` + update cache lokal.
  - Offline → set lokal sebagai optimistic; retry `PUT` ketika online.
- **Role**: operator tidak boleh melihat tombol edit di list data. Field `user.role` menentukannya (`admin` | `operator`).

---

## 8) Referensi lain

- **Spesifikasi envelope `data.token`:** [`docs/auth-token-envelope.md`](./auth-token-envelope.md)
- OpenAPI YAML inti: [`docs/openapi.yaml`](./openapi.yaml) (Auth, Me, Telemetry).
- OpenAPI mobile: [`docs/api/mobile/openapi.yaml`](./api/mobile/openapi.yaml) (traktor, zona, KPI).
- Kontrak penuh (termasuk endpoint device IoT): [`docs/api-contract.md`](./api-contract.md).

---

## 9) Changelog

| Tanggal | Versi | Catatan |
| --- | --- | --- |
| 2026-04-18 | v1.0.0 | Initial contract (auth JWT, /me, strategic read, alerts, telemetry ingest). |
| 2026-04-18 | v1.1.0 | Login/refresh: satu field `data.token` (Base64 JSON); tidak ada `tokens` / `access_token` di root `data`. |
| 2026-04-18 | v1.2.0 | Envelope juga untuk `PATCH /me/password` & `POST /auth/reset-password`; forgot/logout dibungkus `data`. |
