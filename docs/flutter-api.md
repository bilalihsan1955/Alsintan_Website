# Alsintan — API untuk Flutter

Dokumen ini kontrak API yang dipakai aplikasi mobile (Flutter). Semua endpoint di bawah **sudah ter-implementasi di backend Laravel** dan siap dipakai tim Flutter.

- **Base URL dev**: `http://localhost:8000/api/v1`
- **Base URL prod**: `https://<domain>/api/v1`
- **Auth mobile**: JWT bearer (`Authorization: Bearer {access_token}`)
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

1. User isi email+password → `POST /auth/login` → dapat **access (15 m)** + **refresh (30 h)**.
2. Simpan `access_token` di memory (atau secure storage, lihat §7).
3. Tiap request berproteksi: set header `Authorization: Bearer {access}`.
4. Jika dapat **401** → panggil `POST /auth/refresh` dengan `refresh_token`. Server mengembalikan pasangan token **baru**, refresh lama dicabut.
5. Reuse refresh lama = otomatis **semua** refresh user dicabut (paksa login ulang di semua device).
6. Logout = `POST /auth/logout` mengirim `refresh_token` untuk dicabut di server. Access tetap hidup hingga expired (klien buang saja).

---

## 3) Contract masing-masing endpoint (detail)

### 3.1 `POST /auth/login`
Request:
```json
{ "email": "admin@alsintan.id", "password": "rahasia123" }
```
Response `200`:
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
    "tokens": {
      "access_token": "eyJhbGciOi...",
      "refresh_token": "rt_8f2c...",
      "token_type": "Bearer",
      "access_expires_in": 900,
      "refresh_expires_in": 2592000
    }
  }
}
```
Error: `422` (field salah), `401` (email/password salah), `429` (rate limit: 5/menit per email+IP).

### 3.2 `POST /auth/refresh`
```json
{ "refresh_token": "rt_8f2c..." }
```
Response `200` = `tokens` baru (skema sama seperti login). Server menerbitkan refresh **baru** dan men-revoke yang lama.

### 3.3 `POST /auth/logout`
Header `Authorization: Bearer {access}`. Body:
```json
{ "refresh_token": "rt_8f2c..." }
```
Response `204`.

### 3.4 `POST /auth/forgot-password`
```json
{ "email": "admin@alsintan.id" }
```
Selalu `200` walau email tidak terdaftar (enumeration protection). Rate limit: 3/jam/email.

### 3.5 `POST /auth/reset-password`
```json
{ "email": "admin@alsintan.id", "otp": "123456", "password": "passwordBaru1" }
```
Sukses `204`. OTP 6 digit, TTL 15 menit, maks 5 percobaan. Setelah sukses, **semua** refresh token user lama dicabut.

### 3.6 `GET /me`
Return user object (skema sama dengan `user` pada login).

### 3.7 `PATCH /me`
```json
{ "name": "Nama Baru", "phone": "081234567890" }
```
Kedua field optional. Return user object terbaru.

### 3.8 `POST /me/avatar` (multipart)
Field: `avatar` (jpg/jpeg/png/webp, max 2 MB). Return user object.

### 3.9 `DELETE /me/avatar` → `204`.

### 3.10 `PATCH /me/password`
```json
{ "current_password": "lama", "password": "baru8char", "password_confirmation": "baru8char" }
```

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
Field dalam `data[i]` mengikuti nama kolom yang sama dengan dashboard web. Gunakan Postman/Swagger (`docs/openapi.yaml`) untuk contoh payload.

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

import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

final storage = const FlutterSecureStorage();
final dio = Dio(BaseOptions(
  baseUrl: 'http://10.0.2.2:8000/api/v1', // emulator Android → host
  connectTimeout: const Duration(seconds: 10),
  receiveTimeout: const Duration(seconds: 15),
  headers: {'Accept': 'application/json'},
));

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
        final tokens = r.data['data']['tokens'];
        await storage.write(key: 'access_token', value: tokens['access_token']);
        await storage.write(key: 'refresh_token', value: tokens['refresh_token']);

        final retry = e.requestOptions;
        retry.headers['Authorization'] = 'Bearer ${tokens['access_token']}';
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
  final tokens = r.data['data']['tokens'];
  await storage.write(key: 'access_token', value: tokens['access_token']);
  await storage.write(key: 'refresh_token', value: tokens['refresh_token']);
  return r.data['data']['user'];
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

- **Secure storage**: simpan `access_token` & `refresh_token` di `flutter_secure_storage` (iOS Keychain / Android EncryptedSharedPreferences).
- **Refresh race**: gunakan singleton lock agar tidak dua request paralel sama-sama refresh.
- **Base URL**: simpan di `.env` dart; emulator Android pakai `http://10.0.2.2:8000` untuk akses host `localhost`.
- **Sinkron tema**:
  - Saat `GET /me` sukses → replace cache lokal.
  - Saat user ubah tema di app → `PUT /me/preferences` + update cache lokal.
  - Offline → set lokal sebagai optimistic; retry `PUT` ketika online.
- **Role**: operator tidak boleh melihat tombol edit di list data. Field `user.role` menentukannya (`admin` | `operator`).

---

## 8) Referensi lain

- OpenAPI YAML: [`docs/openapi.yaml`](./openapi.yaml) — bisa di-import ke Swagger UI / Postman untuk tes cepat.
- Kontrak penuh (termasuk endpoint device IoT): [`docs/api-contract.md`](./api-contract.md).

---

## 9) Changelog

| Tanggal | Versi | Catatan |
| --- | --- | --- |
| 2026-04-18 | v1.0.0 | Initial contract (auth JWT, /me, strategic read, alerts, telemetry ingest). |
