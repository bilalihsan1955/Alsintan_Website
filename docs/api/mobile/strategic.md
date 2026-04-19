# API Mobile — Strategic (Flutter)

Base URL `/api/v1`, Bearer JWT, error & list sama kontrak umum ([`api-contract.md`](../../api-contract.md)).

Semua endpoint di bawah (kecuali resolve anomali) **read-only** untuk operator; `PATCH /alerts/anomalies/{id}/resolve` membutuhkan role **admin** (`code: forbidden` jika bukan admin).

---

## Ringkasan pemetaan UI → endpoint

| Domain UI | Method + path | Query utama |
|-----------|---------------|-------------|
| KPI + dampak | `GET /strategic/kpi` | `from`, `to` (wajib, `YYYY-MM-DD`) |
| Cakupan lahan harian | `GET /strategic/coverage` | `from`, `to`, opsional `tractor_id` |
| Efisiensi BBM harian | `GET /strategic/fuel-efficiency` | `from`, `to`, opsional `tractor_id` |
| Kinerja kelompok tani | `GET /performance/groups` | opsional `from`, `to` (filter `updated_at`), `group_id`, `page`, `per_page` |
| Event geofence | `GET /alerts/geofence` | `from`, `to` (wajib), `tractor_id`, `zone_id`, `event_type`, `page`, `per_page` |
| Anomali | `GET /alerts/anomalies` | `from`, `to` (wajib), `tractor_id`, `severity`, `status`, `page`, `per_page` |
| Utilisasi | `GET /utilization` | `from`, `to` (wajib), opsional `tractor_id`, `page`, `per_page` |
| Rencana servis | `GET /maintenance/plans` | opsional `from`, `to` (filter `updated_at`), `tractor_id`, `page`, `per_page` |
| Riwayat servis | `GET /maintenance/records` | `from`, `to` (wajib), opsional `tractor_id`, `page`, `per_page` |
| Peta kecil | `GET /tractors/latest-positions` + `GET /work-zones` | Detail: [README-LENGKAP-MOBILE-UNIT-PETA-GPS.md](../../README-LENGKAP-MOBILE-UNIT-PETA-GPS.md) |
| Resolve anomali (admin) | `PATCH /alerts/anomalies/{id}/resolve` | body JSON opsional `{ "note": "..." }` |

---

## `GET /strategic/kpi`

### Respons 200 — bentuk `data` (agregat periode)

```json
{
  "data": {
    "total_alsintan": 12,
    "total_data_log": 458920,
    "total_fuel_liters": 1840.5,
    "average_score": 82.3,
    "anomalies_unresolved": 4,
    "total_repair_cost_idr": 12500000,
    "impact": {
      "fuel_savings_percent": 0,
      "productivity_up_percent": 14.48,
      "downtime_down_percent": 20
    }
  },
  "meta": {
    "from": "2026-03-01",
    "to": "2026-04-18"
  }
}
```

Angka mayoritas dari agregat `kpi_daily` per rentang tanggal; **`total_repair_cost_idr`** dihitung dari **jumlah kolom biaya** pada **`maintenance_records`** di rentang `from`–`to` (selaras tabel riwayat servis).

---

## `GET /strategic/coverage` & `GET /strategic/fuel-efficiency`

Keduanya membutuhkan `from` & `to`. Respons:

- **Coverage** — `data[]`: `{ "date", "hectare_covered", "cumulative" }`
- **Fuel efficiency** — `data[]`: `{ "date", "fuel_used_l", "efficiency_value" }`

`meta` berisi `from`, `to`, `tractor_id` (null jika agregat fleet), `count`.

---

## Daftar berpaginasi

`GET /performance/groups`, `GET /alerts/geofence`, `GET /alerts/anomalies`, `GET /utilization`, `GET /maintenance/plans`, `GET /maintenance/records` mengembalikan:

```json
{
  "data": [ {} ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 120,
    "last_page": 8,
    "from": "2026-04-01",
    "to": "2026-04-18"
  }
}
```

Field `from`/`to` pada `meta` bisa `null` jika endpoint tidak memakai filter tanggal (mis. rencana servis tanpa query tanggal).

---

## Anomali — normalisasi status di JSON

Baris anomali pada respons API memakai **huruf kecil** untuk `status` dan `severity` agar konsisten dengan klien mobile (`open`, `resolved`, dll.), meskipun penyimpanan internal bisa berupa huruf besar.

---

## OpenAPI

Skema agregat KPI (`StrategicKpiData`) dan path traktor/zona: [`openapi.yaml`](./openapi.yaml). Ringkasan lain: [`flutter-api.md`](../../flutter-api.md).
