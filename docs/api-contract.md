# API Contract — Alsintan

Kontrak API yang dipakai bersama Flutter (mobile), ESP32/RPi (IoT), dan web admin Laravel.

## Base URL & versi

- Development: `http://localhost:8000/api/v1`
- Produksi: `https://<domain>/api/v1`

Semua endpoint memakai prefix `/api/v1`. Jangan panggil versi tanpa prefix — versi baru akan naik ke `/v2`.

## Autentikasi

| Konsumen | Mekanisme | Header |
| --- | --- | --- |
| Flutter (user) | **JWT** bearer (HS256) dari `POST /auth/login` | `Authorization: Bearer {access_token}` |
| Web admin (Blade) | Session (CSRF) — rute `/` Laravel | — |
| ESP32 / RPi (device) | API token per-traktor | `X-Device-Token: {plain_token}` |

- Access token TTL default **15 menit** (`JWT_ACCESS_TTL`).
- Refresh token TTL default **30 hari** (`JWT_REFRESH_TTL`), disimpan sebagai hash SHA-256 di tabel `refresh_tokens` → bisa di-revoke.
- Refresh token **di-rotasi** setiap `POST /auth/refresh` (token lama langsung revoked). Pemakaian ulang dianggap pelanggaran → semua refresh token user dicabut (dipaksa login ulang di semua device).

## Keputusan produk (tertulis)

| Topik | Keputusan |
| --- | --- |
| Stack auth mobile | **JWT** (`firebase/php-jwt`, HS256) — access + refresh |
| Satu akun lintas platform | **Ya** — `users` tunggal dipakai web & mobile |
| Register mandiri dari mobile | **Tidak** — akun dibuat admin (`php artisan user:create`) |
| Lupa password | **OTP 6 digit via email**, TTL 15 menit, maks 5 percobaan |
| Role | **admin** (CRUD strategis) & **operator** (read + telemetri) |
| Theme `theme_mode` | **Server = source of truth**; lokal = cache (sinkron saat online) |
| Device auth (IoT) | **API token per-traktor** (`X-Device-Token`), regen via `php artisan tractor:token` |
| Login sosial | **Ditunda ke fase 2** |

## Format response

### Sukses resource tunggal
```json
{ "data": { ... } }
```

### Sukses list + pagination (saat berlaku)
```json
{
  "data": [ ... ],
  "meta": { "current_page": 1, "per_page": 20, "total": 0, "last_page": 1 }
}
```

### Error konsisten (ditangani di `bootstrap/app.php`)
```json
// 400/404/403/500
{ "message": "Resource tidak ditemukan", "code": "not_found" }

// 401
{ "message": "Token tidak valid atau kedaluwarsa", "code": "unauthorized" }

// 422 (validation)
{
  "message": "Data tidak valid",
  "code": "validation_error",
  "errors": { "email": ["Email wajib diisi."] }
}

// 429
{ "message": "Terlalu banyak percobaan. Coba lagi dalam 47 detik.", "code": "too_many_requests", "retry_after": 47 }
```

## Rate limit

| Aksi | Limit |
| --- | --- |
| `POST /auth/login` | 5 percobaan gagal per `email+IP` per menit |
| `POST /auth/forgot-password` | 3 request per `email+IP` per jam |
| Lainnya | Bawaan Laravel (`throttle:api` jika ditambahkan) |

## Endpoint

### 1. Auth

| Method | Path | Auth | Deskripsi |
| --- | --- | --- | --- |
| POST | `/auth/login` | — | Terbitkan access+refresh token |
| POST | `/auth/refresh` | — | Rotasi refresh → access+refresh baru |
| POST | `/auth/logout` | Bearer | Revoke refresh token yang dikirim di body |
| POST | `/auth/forgot-password` | — | Kirim OTP 6 digit ke email |
| POST | `/auth/reset-password` | — | Tukar OTP + password baru |

#### Login
```http
POST /api/v1/auth/login
Content-Type: application/json

{ "email": "demo@alsintan.id", "password": "password123", "device_name": "Pixel 8 — Flutter" }
```
Response `200`:
```json
{
  "access_token": "eyJ0eXAiOi...",
  "token_type": "Bearer",
  "expires_in": 900,
  "refresh_token": "rt_...",
  "refresh_expires_in": 2592000,
  "user": {
    "id": 2, "name": "Demo Admin", "email": "demo@alsintan.id", "phone": null,
    "avatar_url": null, "role": "admin",
    "preferences": { "theme_mode": "system", "language": "id" },
    "email_verified_at": null, "created_at": "2026-04-18T10:00:00+07:00"
  }
}
```

#### Refresh
```http
POST /api/v1/auth/refresh
{ "refresh_token": "rt_..." }
```
Response = sama strukturnya dengan login. Token refresh lama otomatis revoked.

#### Forgot/Reset password
```http
POST /api/v1/auth/forgot-password
{ "email": "demo@alsintan.id" }
```
Server **selalu** membalas `200` (anti email enumeration). Jika email terdaftar, OTP 6 digit dikirim ke email.

```http
POST /api/v1/auth/reset-password
{
  "email": "demo@alsintan.id",
  "code": "482091",
  "password": "passwordBaru!9",
  "password_confirmation": "passwordBaru!9"
}
```
Setelah reset, **semua refresh token user dicabut** (logout di semua device).

### 2. Profil (Me)

Semua butuh `Authorization: Bearer {access_token}`.

| Method | Path | Deskripsi |
| --- | --- | --- |
| GET | `/me` | Profil + role + preferences |
| PATCH | `/me` | Update `name`, `phone` |
| POST | `/me/avatar` | Upload avatar (multipart, field `avatar`, max 2MB) |
| DELETE | `/me/avatar` | Hapus avatar |
| PATCH | `/me/password` | Ganti password (butuh `current_password`) |
| GET | `/me/preferences` | Ambil preferensi |
| PUT | `/me/preferences` | Simpan preferensi (`theme_mode`, `language`, `extras`) |

Response `GET /me` dan update profil:
```json
{ "data": { "id":2, "name":"Demo Admin", "email":"...", "phone":"08xxx", "avatar_url":"https://.../avatars/2/xxx.jpg", "role":"admin", "preferences":{ "theme_mode":"dark", "language":"id" } } }
```

Response `GET /me/preferences`:
```json
{ "data": { "theme_mode": "system", "language": "id" } }
```

Catatan theme:
- Klien mobile menerapkan `theme_mode` yang diterima dari server.
- Saat user ubah tema: klien **optimistik** ubah lokal → `PUT /me/preferences`. Jika gagal → rollback.
- Saat app dibuka online: panggil `GET /me/preferences` → timpa lokal.

### 3. Business endpoints (butuh Bearer)

| Method | Path | Deskripsi |
| --- | --- | --- |
| GET | `/strategic/kpi` | KPI agregat harian |
| GET | `/strategic/coverage` | Cakupan area |
| GET | `/strategic/fuel-efficiency` | Efisiensi BBM |
| GET | `/performance/groups` | Rapor kinerja kelompok |
| GET | `/alerts/geofence` | Log geofence (auto-catat) |
| GET | `/alerts/anomalies` | Log anomali |
| PATCH | `/alerts/anomalies/{id}/resolve` | Tandai anomali selesai (admin) |
| GET | `/utilization` | Utilisasi harian |
| GET | `/maintenance/plans` | Rencana perawatan |
| GET | `/maintenance/records` | Riwayat perawatan |
| GET | `/tractors/latest-positions` | Posisi terkini semua traktor |
| GET | `/tractors/{id}/route-history` | Riwayat rute satu traktor |

> **TODO (product-owner):** tegaskan kebijakan per-role. Saat ini operator masih bisa akses semua read endpoint; admin-only action perlu policy (lihat `TODO role enforcement`).

### 4. Device IoT (telemetri)

| Method | Path | Auth | Deskripsi |
| --- | --- | --- | --- |
| POST | `/telemetry/ingest` | `X-Device-Token` | Kirim payload telemetri |

Legacy (masih aktif, akan dihapus setelah firmware migrasi):
- `POST /api/v1/sync-data`
- `POST /api/v1/device/telemetry`

> **TODO:** telemetri lama belum wajib `X-Device-Token` (backward-compat). Setelah semua device dirilis ulang dengan token, hapus kedua route legacy dan paksa auth device di semua ingest endpoint.

## Contoh curl

Login:
```bash
curl -sS -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"demo@alsintan.id","password":"password123","device_name":"curl-test"}'
```

Refresh:
```bash
curl -sS -X POST http://localhost:8000/api/v1/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"refresh_token":"rt_XXXX"}'
```

GET profil:
```bash
curl -sS http://localhost:8000/api/v1/me \
  -H "Authorization: Bearer eyJ0eXAi..."
```

Update preferensi tema:
```bash
curl -sS -X PUT http://localhost:8000/api/v1/me/preferences \
  -H "Authorization: Bearer eyJ0eXAi..." \
  -H "Content-Type: application/json" \
  -d '{"theme_mode":"dark","language":"id"}'
```

Logout:
```bash
curl -sS -X POST http://localhost:8000/api/v1/auth/logout \
  -H "Authorization: Bearer eyJ0eXAi..." \
  -H "Content-Type: application/json" \
  -d '{"refresh_token":"rt_XXXX"}'
```

Telemetri (device):
```bash
curl -sS -X POST http://localhost:8000/api/v1/telemetry/ingest \
  -H "X-Device-Token: dt_XXXXXXXX" \
  -H "Content-Type: application/json" \
  -d '{"tractor_id":"T-01","lat":-6.1,"lng":106.8,"fuel_lph":2.3,"ts":"2026-04-18T07:00:00Z"}'
```

## Setup backend (operasional)

1. `.env` — tambahkan:
    ```env
    JWT_SECRET=base64:xxxxx     # kosongkan untuk fallback ke APP_KEY
    JWT_ALGO=HS256
    JWT_ACCESS_TTL=900          # 15 menit
    JWT_REFRESH_TTL=2592000     # 30 hari
    JWT_ISSUER=alsintan
    JWT_AUDIENCE=alsintan-mobile

    # SMTP untuk OTP (produksi)
    MAIL_MAILER=smtp
    MAIL_HOST=...
    MAIL_FROM_ADDRESS="no-reply@alsintan.id"
    MAIL_FROM_NAME="Alsintan"
    ```
2. `php artisan migrate` (wajib, sudah dijalankan saat setup).
3. `php artisan storage:link` untuk avatar (sudah dijalankan).
4. Buat user admin pertama:
    ```bash
    php artisan user:create admin@alsintan.id --name="Admin" --password="ganti_ini" --role=admin
    ```
5. Generate device token untuk tiap traktor:
    ```bash
    php artisan tractor:token T-01
    # salin output "dt_..." ke firmware
    ```
6. Rotasi / cabut bila device hilang:
    ```bash
    php artisan tractor:token T-01             # generate baru (lama otomatis invalid)
    php artisan tractor:token T-01 --revoke    # cabut tanpa ganti
    ```

## Yang masih butuh konfirmasi product owner

Ditandai dengan `TODO(product-owner)` di kode/dokumen.

- [ ] Endpoint `admin-only` (mana yang boleh operator, mana tidak) — saat ini semua read terbuka untuk user terautentikasi.
- [ ] Ownership per kelompok: perlu tambah `tractors.group_id` agar operator hanya lihat traktor kelompoknya.
- [ ] Kapan route legacy ingest (`/sync-data`, `/device/telemetry`) dihapus (timeline migrasi firmware).
- [ ] Apakah `/me/avatar` perlu batas ukuran lebih kecil (<1 MB) untuk menghemat storage mobile upload.
- [ ] Kebijakan password strength (saat ini min 8 karakter, tidak ada aturan kompleksitas).
- [ ] Realtime (Reverb/Pusher) — event apa saja yang di-broadcast ke mobile (posisi traktor? alert?) dan struktur payloadnya.
- [ ] Logging audit (login, reset password, rotasi device token) — perlu tabel `audit_logs`?
