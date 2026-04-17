/**
 * Salin file ini menjadi secrets.h dan isi nilai sesungguhnya.
 * secrets.h tidak perlu di-commit ke repositori.
 */
#ifndef SECRETS_H
#define SECRETS_H

#define WIFI_SSID "nama_wifi"
#define WIFI_PASSWORD "password_wifi"

/** URL endpoint Laravel, contoh: http://192.168.1.10:8000/api/v1/device/telemetry */
#define API_BASE_URL "http://192.168.1.10:8000/api/v1/device/telemetry"

/** Harus sama dengan device_id di tabel tractors */
#define DEVICE_ID "TRACTOR-001"

/** Nama traktor opsional (akan di-update di server jika dikirim) */
#define TRACTOR_NAME "Traktor Ladang A"

#endif
