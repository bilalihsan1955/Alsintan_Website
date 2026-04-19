# Pelacakan traktor di peta OSM — aplikasi mobile

Dokumen ini **khusus** alur **menampilkan posisi & lintasan traktor** di **peta berbasis OpenStreetMap (OSM)** di klien mobile (mis. Flutter + `flutter_map` atau widget peta lain yang memakai tile OSM / WGS84).

**Base URL API:** `https://<host>/api/v1` (dev: `http://127.0.0.1:8000/api/v1`).  
Semua REST di bawah memakai header **`Authorization: Bearer <access_jwt>`** kecuali dijelaskan lain. Cara login & refresh token ada di **`docs/api-contract.md`** dan **`docs/openapi.yaml`** — tidak diurai panjang di sini.

---

## 1. Peta OSM di mobile (kontrak visual)

| Topik | Yang dipakai |
|--------|----------------|
| Sistem koordinat | API mengembalikan **`lat` / `lng` dalam WGS84** (derajat desimal, EPSG:4326). Pasangkan langsung ke `LatLng(lat, lng)` (atau setara) — **tanpa proyeksi** tambahan untuk marker/polyline di atas tile raster OSM. |
| Tile | URL pola umum: `https://tile.openstreetmap.org/{z}/{x}/{y}.png`. Patuhi [kebijakan tile OSM](https://operations.osmfoundation.org/policies/tiles/) (User-Agent bermakna, batas unduhan, atribusi “© OpenStreetMap”). Untuk produksi skala besar disarankan **tile server sendiri** atau penyedia komersial. |
| Marker posisi terkini | Satu titik per traktor: `lat`, `lng` dari REST atau terakhir dari WebSocket. |
| Polyline + **node** (seperti Home web / Leaflet) | Di web, **setiap** titik GPS = `L.circleMarker` + garis `L.polyline`. Di mobile: **satu marker (lingkaran) per elemen** `route_history[]` atau `route-history` → `data[]` — jangan hanya garis tipis dengan 2–3 titik; itu bukan sumber lain, itu **jumlah titik yang dikirim** terlalu sedikit atau klien tidak menggambar per-node. |
| Zona (opsional) | `polygon[]` dari `GET /work-zones` — tutup ring di klien jika perlu poligon utuh. |

---

## 2. Ringkasan alur (data → layer OSM)

| Layer di peta | Sumber |
|---------------|--------|
| Marker “sekarang” | **Sekali** `GET /tractors/latest-positions` atau `GET /tractors/{id}/location`; lalu **WebSocket** `.tractor.updated` untuk geser marker (hindari polling REST untuk posisi live). |
| Garis lintasan (historis) | `GET /tractors/{id}/route-history` → `data[]` sebagai urutan titik polyline. |
| Overlay zona | `GET /work-zones` (polygon `{ lat, lng }`). |

---

## 3. Sumber titik di backend

Tidak ada tabel `gps` terpisah. Titik untuk peta web dan mobile sama: **`telemetry_logs`**.  
`GET /tractors/{id}/route-history` mengembalikan `meta.source` = `telemetry_logs`.

---

## 4. REST — `GET /tractors/latest-positions`

**Header:** `Authorization: Bearer <access_jwt>`

### Query (opsional)

| Parameter | Wajib | Keterangan |
|-----------|-------|------------|
| `search` | tidak | LIKE `id`, `name`, `plate_number` |
| `status` | tidak | `active` \| `idle` \| `maintenance` \| `offline` |
| `page` | tidak | Default 1 |
| `per_page` | tidak | Default 15, maks. 100 |
| `include_route_history` | tidak | `true` = **daftar titik** jejak per unit (sama sumber `telemetry_logs` dengan peta Home web) |
| `route_history_limit` | tidak | 2–**800**, default 30 — pakai **800** bila ingin kepadatan titik **sama** dengan dashboard web (800 titik terbaru). |

### Field yang dipakai langsung di peta OSM

| Field | Keterangan |
|-------|------------|
| `id` | Kunci layer / lookup saat event WS |
| `lat`, `lng` | Posisi marker WGS84 |
| `route_history` | `{ lat, lng, ts? }[]` urut waktu naik — **satu objek = satu node** di peta (gambar lingkaran + opsional garis antar titik). `ts` ISO-8601 UTC untuk popup / label seperti web. |

### Samakan tampilan dengan Home / Strategic web

1. Panggil `include_route_history=true` & `route_history_limit=800` (atau layar detail: `GET /tractors/{id}/route-history` dengan `limit` hingga 1200).  
2. Untuk **setiap** item di `route_history` / `data`, gambar **marker bulat** di koordinat tersebut (setara `L.circleMarker`), lalu opsional `Polyline` menghubungkan urutan yang sama.  
3. Titik terakhir array = posisi paling baru di segmen (perbesar radius / warna beda seperti web bila perlu).

**`meta`:** pagination + `online_stale_minutes` (config `alsintan.fleet_online_stale_minutes`).

### Contoh ringkas (satu unit)

```json
{
  "data": [
    {
      "id": "TRC-MALANG-01",
      "display_name": "TRC-MALANG-01",
      "lat": -6.2,
      "lng": 106.8,
      "route_history": [
        { "lat": -6.201, "lng": 106.816, "ts": "2026-04-19T06:00:01.000000Z" }
      ]
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1,
    "online_stale_minutes": 15
  }
}
```

---

## 5. REST — `GET /tractors/{id}/location`

Satu traktor: struktur **sama** seperti satu elemen `latest-positions`, plus **`work_zones[]`** untuk overlay zona yang di-assign.

**Query:** `include_route_history`, `route_history_limit` (sama §4).

---

## 6. REST — `GET /work-zones`

| Query | Keterangan |
|-------|------------|
| `active_only` | `true` = hanya zona aktif |

Tiap item: `polygon` = array `{ lat, lng }` (WGS84). Muat ulang REST jika zona berubah (tidak di-push lewat WS saat ini).

---

## 7. REST — `GET /tractors/{id}/route-history` (polyline penuh)

**Sumber:** `telemetry_logs`, titik diurut **`ts` naik** untuk digambar berurutan di OSM.

| Parameter | Keterangan |
|-----------|------------|
| `from`, `to` | Opsional, **berpasangan** `YYYY-MM-DD`; filter `ts` |
| `limit` | Default 800, maks. 1200 |

- Tanpa rentang: **`limit` titik terakhir** (terbaru di DB), lalu urut naik — sama ide dengan polyline ringkas `include_route_history`.  
- Dengan rentang: filter tanggal, lalu **`limit` titik terakhir** dalam rentang, urut naik.

**Respons:** `data[]` = `{ lat, lng, ts }`; `meta` menyertakan `segment: "recent"`, `source`, `count`, `limit`.

```json
{
  "data": [
    { "lat": -6.201, "lng": 106.816, "ts": "2026-04-19T06:00:01.000000Z" },
    { "lat": -6.202, "lng": 106.817, "ts": "2026-04-19T06:05:12.000000Z" }
  ],
  "meta": {
    "tractor_id": "T-01",
    "source": "telemetry_logs",
    "segment": "recent",
    "from": null,
    "to": null,
    "count": 2,
    "limit": 800
  }
}
```

**Node live (WebSocket)** memakai bentuk yang sama dengan satu elemen `data[]` di atas (`payload.node`: `lat`, `lng`, `ts`).

---

## 8. WebSocket — geser marker di peta (live)

### Prasyarat server

`BROADCAST_CONNECTION=reverb`, Reverb jalan, env `REVERB_*` cocok dengan host klien.

### Channel & event

| Properti | Nilai |
|----------|--------|
| Channel | `alsintan.public` |
| Event | `.tractor.updated` (`tractor.updated`) |

**Payload** (inti untuk peta): `tractor_id`, **`node`** = `{ lat, lng, ts }` (sama §7), plus `position` `{ lat, lng }` untuk kompatibilitas. **Perbarui marker** dari `node` atau `position`; opsional **tambahkan** titik ke polyline in-memory sesuai UX.

Throttle server ~2 detik per traktor; data tetap tersimpan di `telemetry_logs`.

### Flutter

Library kompatibel **Pusher Channels** (mis. `pusher_channels_flutter`), parameter sesuai Reverb (`REVERB_APP_KEY`, host, port, TLS).

---

## 9. Checklist integrasi peta OSM

- [ ] Login → simpan JWT → Bearer.  
- [ ] Tile layer OSM + atribusi + User-Agent sesuai kebijakan.  
- [ ] Muat marker + opsional polyline ringkas: `latest-positions` atau `location`.  
- [ ] Muat jejak panjang (layar “detail lintasan”): `route-history`.  
- [ ] Subscribe WS → update marker (tanpa polling REST untuk posisi).  
- [ ] Opsional: `work-zones` sebagai polygon overlay.

---

## 10. Error REST (ringkas)

`{ "message", "code" }`; 422 + `errors`. 401 / 404 sesuai konteks.

---

## 11. Asal data titik (bukan UI peta)

Agar titik baru masuk DB (lalu muncul di peta setelah REST/WS): perangkat mengirim **`POST /telemetry/ingest`** dengan header **`X-Device-Token`** (bukan Bearer user). Detail body: `docs/openapi.yaml`. Legacy: `POST /sync-data`.

---

## 12. Berkas OpenAPI (codegen / Postman)

| Berkas | Dipakai untuk |
|--------|----------------|
| `docs/api/mobile/openapi.yaml` | Path §4–§7 (traktor, lokasi, route-history, work-zones) + skema respons. |
| `docs/openapi.yaml` | Auth, `/me`, `/telemetry/ingest`. |

---

## 13. Referensi kode backend

| Topik | Lokasi |
|-------|--------|
| Posisi & route history API | `App\Http\Controllers\Api\TractorMapController` |
| Broadcast + `node` | `App\Services\TelemetryIngestService`, `App\Events\TractorUpdated` |
| Ambang “online” fleet | `config/alsintan.php` |
| Zona kerja | `App\Http\Controllers\Api\WorkZoneController` |
