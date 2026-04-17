/**
 * Gateway ESP32 — Alsintan
 * - RS485 (MAX485) polling Arduino Nano (sensor)
 * - GPS NEO-6M/7M via UART
 * - SD card: antrean store-and-forward saat offline
 * - HTTP POST ke API Laravel
 *
 * Pustaka Arduino (Library Manager):
 * - ArduinoJson (oleh Benoit Blanchon) v6 atau v7
 * - TinyGPSPlus (oleh Mikal Hart)
 *
 * Kabel & pin (sesuaikan dengan board Anda):
 * SD (SPI): CS=GPIO5, MOSI=23, MISO=19, SCK=18
 * RS485:    RX=GPIO16, TX=GPIO17, DE/RE=GPIO21 (HIGH=transmit)
 * GPS:      RX ESP=GPIO27 (ke TX modul GPS), TX ESP=GPIO26 (ke RX modul GPS)
 *
 * Protokol RS485 ke slave: master mengirim 3 byte 0xAA 0x55 0x0A,
 * slave membalas SATU baris JSON UTF-8 diakhiri \n, contoh:
 * {"v":0.12,"i":1.5,"t":28.3,"h":65.0,"mv":true}
 *   v  = getaran (nilai sensor SW420 / skala Anda)
 *   i  = debit/aliran air dari sensor flow (skala di sisi Nano, mis. L/min)
 *   t  = suhu °C (DHT22)
 *   h  = kelembaban % (DHT22)
 *   mv = indikasi bergerak (boolean)
 */

#include <Arduino.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <SD.h>
#include <SPI.h>
#include <HardwareSerial.h>
#include <TinyGPSPlus.h>
#include <ArduinoJson.h>

#if __has_include("secrets.h")
#include "secrets.h"
#else
#error "Buat secrets.h dari secrets.example.h dan isi WIFI / API / DEVICE_ID"
#endif

// ---------- Pin ----------
static const int PIN_SD_CS = 5;
static const int PIN_RS485_RX = 16;
static const int PIN_RS485_TX = 17;
static const int PIN_RS485_DE = 21;
static const int PIN_GPS_RX = 27;
static const int PIN_GPS_TX = 26;

static const char *QUEUE_PATH = "/als_queue.jsonl";
static const char *QUEUE_TMP = "/als_queue.tmp";

static const unsigned long RS485_BAUD = 9600;
static const unsigned long GPS_BAUD = 9600;
static const unsigned POLL_INTERVAL_MS = 5000;
static const unsigned GPS_READ_MS = 500;
static const unsigned WIFI_RETRY_MS = 15000;
static const size_t RS485_LINE_MAX = 512;
static const int QUEUE_FLUSH_BATCH = 25;
static const int MAX_LINE_BYTES = 480;

HardwareSerial RS485(2);
HardwareSerial GPSSerial(1);
TinyGPSPlus gps;

// ---------- Utilitas RS485 ----------
void rs485SetTx(bool tx) {
  digitalWrite(PIN_RS485_DE, tx ? HIGH : LOW);
}

bool pollSlaveSensors(JsonObject sensorOut) {
  while (RS485.available()) {
    (void)RS485.read();
  }

  rs485SetTx(true);
  RS485.write((uint8_t)0xAA);
  RS485.write((uint8_t)0x55);
  RS485.write((uint8_t)0x0A);
  RS485.flush();
  rs485SetTx(false);

  char buf[RS485_LINE_MAX];
  size_t n = 0;
  unsigned long t0 = millis();
  while (millis() - t0 < 800) {
    while (RS485.available()) {
      int c = RS485.read();
      if (c < 0) {
        continue;
      }
      if (c == '\r') {
        continue;
      }
      if (c == '\n') {
        buf[n] = '\0';
        goto parse;
      }
      if (n + 1 < sizeof(buf)) {
        buf[n++] = (char)c;
      } else {
        n = 0;
      }
    }
    delay(2);
  }
  return false;

parse:
  StaticJsonDocument<512> doc;
  DeserializationError err = deserializeJson(doc, buf);
  if (err) {
    return false;
  }
  if (!doc.containsKey("v") || !doc.containsKey("i") || !doc.containsKey("t") ||
      !doc.containsKey("h")) {
    return false;
  }
  sensorOut["vibration"] = doc["v"].as<float>();
  sensorOut["flow"] = doc["i"].as<float>();
  sensorOut["temperature"] = doc["t"].as<float>();
  sensorOut["humidity"] = doc["h"].as<float>();
  sensorOut["is_moving"] = doc["mv"] | false;
  return true;
}

// ---------- GPS ----------
void feedGps(unsigned long ms) {
  unsigned long start = millis();
  while (millis() - start < ms) {
    while (GPSSerial.available()) {
      gps.encode(GPSSerial.read());
    }
    delay(1);
  }
}

void fillGpsJson(JsonObject gpsOut, bool *hasFix) {
  *hasFix = gps.location.isValid() && gps.location.age() < 5000;
  if (*hasFix) {
    gpsOut["latitude"] = gps.location.lat();
    gpsOut["longitude"] = gps.location.lng();
  } else {
    gpsOut["latitude"] = nullptr;
    gpsOut["longitude"] = nullptr;
  }
  if (gps.speed.isValid()) {
    gpsOut["speed"] = gps.speed.kmph();
  } else {
    gpsOut["speed"] = nullptr;
  }

  if (gps.date.isValid() && gps.time.isValid()) {
    char iso[32];
    snprintf(iso, sizeof(iso), "%04u-%02u-%02uT%02u:%02u:%02uZ",
             gps.date.year(), gps.date.month(), gps.date.day(),
             gps.time.hour(), gps.time.minute(), gps.time.second());
    gpsOut["device_time"] = iso;
  } else {
    gpsOut["device_time"] = nullptr;
  }
}

// ---------- Waktu rekaman (UTC) ----------
void iso8601Now(char *out, size_t len) {
  struct tm ti;
  if (!getLocalTime(&ti, 500)) {
    time_t now = time(nullptr);
    gmtime_r(&now, &ti);
  }
  strftime(out, len, "%Y-%m-%dT%H:%M:%SZ", &ti);
}

// ---------- SD antrean ----------
bool appendQueueLine(const String &line) {
  File f = SD.open(QUEUE_PATH, FILE_APPEND);
  if (!f) {
    return false;
  }
  f.print(line);
  f.print('\n');
  f.close();
  return true;
}

bool postJsonPayload(const String &json, int *httpCodeOut) {
  if (WiFi.status() != WL_CONNECTED) {
    return false;
  }
  HTTPClient http;
  http.begin(API_BASE_URL);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  int code = http.POST(json);
  *httpCodeOut = code;
  http.end();
  return code >= 200 && code < 300;
}

/**
 * Membaca hingga QUEUE_FLUSH_BATCH baris dari queue, mengirim bulk ke API.
 * Jika sukses, menulis sisa file ke tmp lalu rename.
 * Jika gagal, tidak mengubah file.
 */
static void shallowCopyJsonObject(JsonObject dst, JsonObject src) {
  for (JsonPair kv : src) {
    dst[kv.key()] = kv.value();
  }
}

bool flushQueueFile() {
  if (!SD.exists(QUEUE_PATH)) {
    return true;
  }

  File in = SD.open(QUEUE_PATH, FILE_READ);
  if (!in) {
    return false;
  }

  String lines[QUEUE_FLUSH_BATCH];
  int count = 0;
  int linesConsumed = 0;
  while (in.available() && count < QUEUE_FLUSH_BATCH) {
    String ln = in.readStringUntil('\n');
    linesConsumed++;
    ln.trim();
    if (ln.length() == 0) {
      continue;
    }
    if (ln.length() > MAX_LINE_BYTES) {
      continue;
    }
    lines[count++] = ln;
  }

  const bool moreInFile = in.available() > 0;
  in.close();

  if (count == 0) {
    if (!moreInFile) {
      SD.remove(QUEUE_PATH);
    }
    return true;
  }

  DynamicJsonDocument body(20480);
  body["device_id"] = DEVICE_ID;
  body["tractor_name"] = TRACTOR_NAME;
  JsonArray recs = body.createNestedArray("records");
  for (int i = 0; i < count; i++) {
    StaticJsonDocument<1024> one;
    if (deserializeJson(one, lines[i])) {
      continue;
    }
    JsonObject slot = recs.createNestedObject();
    shallowCopyJsonObject(slot, one.as<JsonObject>());
  }

  if (recs.size() == 0) {
    SD.remove(QUEUE_PATH);
    return true;
  }

  String payload;
  serializeJson(body, payload);

  int httpCode = 0;
  if (!postJsonPayload(payload, &httpCode)) {
    return false;
  }

  if (!moreInFile) {
    SD.remove(QUEUE_PATH);
    return true;
  }

  File in2 = SD.open(QUEUE_PATH, FILE_READ);
  File out = SD.open(QUEUE_TMP, FILE_WRITE);
  if (!in2 || !out) {
    if (in2) {
      in2.close();
    }
    if (out) {
      out.close();
    }
    return true;
  }
  int skip = linesConsumed;
  while (in2.available()) {
    String ln = in2.readStringUntil('\n');
    if (skip > 0) {
      skip--;
      continue;
    }
    out.print(ln);
    out.print('\n');
  }
  in2.close();
  out.close();
  SD.remove(QUEUE_PATH);
  SD.rename(QUEUE_TMP, QUEUE_PATH);
  return true;
}

void tryFlushAllQueue() {
  while (SD.exists(QUEUE_PATH) && WiFi.status() == WL_CONNECTED) {
    if (!flushQueueFile()) {
      break;
    }
    delay(100);
  }
}

// ---------- Kirim satu paket (online langsung API, offline ke SD) ----------
void sendOrQueueRecord(JsonObject record) {
  String line;
  serializeJson(record, line);

  int httpCode = 0;
  if (WiFi.status() == WL_CONNECTED) {
    DynamicJsonDocument body(4096);
    body["device_id"] = DEVICE_ID;
    body["tractor_name"] = TRACTOR_NAME;
    JsonArray recs = body.createNestedArray("records");
    JsonObject one = recs.createNestedObject();
    shallowCopyJsonObject(one, record);
    String payload;
    serializeJson(body, payload);
    if (postJsonPayload(payload, &httpCode)) {
      return;
    }
  }

  if (!SD.begin(PIN_SD_CS)) {
    return;
  }
  appendQueueLine(line);
}

void setup() {
  Serial.begin(115200);
  delay(500);

  pinMode(PIN_RS485_DE, OUTPUT);
  rs485SetTx(false);
  RS485.begin(RS485_BAUD, SERIAL_8N1, PIN_RS485_RX, PIN_RS485_TX);
  GPSSerial.begin(GPS_BAUD, SERIAL_8N1, PIN_GPS_RX, PIN_GPS_TX);

  if (!SD.begin(PIN_SD_CS)) {
    Serial.println(F("[SD] init gagal — mode antrean offline tidak tersedia"));
  } else {
    Serial.println(F("[SD] OK"));
  }

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print(F("[WiFi] Menghubungkan"));
  unsigned long w0 = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - w0 < 20000) {
    delay(500);
    Serial.print('.');
  }
  Serial.println();
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println(WiFi.localIP());
    configTime(0, 0, "pool.ntp.org", "time.nist.gov");
    unsigned long n0 = millis();
    while (time(nullptr) < 100000 && millis() - n0 < 8000) {
      delay(200);
    }
    tryFlushAllQueue();
  } else {
    Serial.println(F("[WiFi] belum terhubung — data akan di-SD jika memungkinkan"));
  }
}

void loop() {
  static unsigned long lastPoll = 0;
  static unsigned long lastWifiTry = 0;

  if (WiFi.status() != WL_CONNECTED) {
    if (millis() - lastWifiTry > WIFI_RETRY_MS) {
      lastWifiTry = millis();
      WiFi.disconnect();
      WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    }
  } else {
    tryFlushAllQueue();
  }

  feedGps(GPS_READ_MS);

  if (millis() - lastPoll < POLL_INTERVAL_MS) {
    delay(10);
    return;
  }
  lastPoll = millis();

  StaticJsonDocument<1024> record;
  JsonObject sensor = record.createNestedObject("sensor");
  if (!pollSlaveSensors(sensor)) {
    sensor["vibration"] = nullptr;
    sensor["flow"] = nullptr;
    sensor["temperature"] = nullptr;
    sensor["humidity"] = nullptr;
    sensor["is_moving"] = false;
  }

  bool fix = false;
  JsonObject gj = record.createNestedObject("gps");
  fillGpsJson(gj, &fix);

  char recTime[40];
  iso8601Now(recTime, sizeof(recTime));
  record["recorded_at"] = recTime;

  sendOrQueueRecord(record.as<JsonObject>());

  Serial.print(F("[TX] sensor OK="));
  Serial.print(sensor["temperature"].isNull() ? 0 : 1);
  Serial.print(F(" WiFi="));
  Serial.println(WiFi.status() == WL_CONNECTED ? F("up") : F("down"));
}
