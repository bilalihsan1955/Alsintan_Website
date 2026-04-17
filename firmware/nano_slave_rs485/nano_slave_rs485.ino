/**
 * Arduino Nano — Slave RS485 (MAX485) untuk Alsintan
 * Sensor: SW420 (getaran digital), sensor debit/aliran air (analog), DHT22 (suhu & kelembaban)
 *
 * Pustaka (Library Manager):
 * - ArduinoJson (Benoit Blanchon)
 * - DHT sensor library (Adafruit) + Adafruit Unified Sensor
 *
 * Protokol: saat menerima urutan byte 0xAA 0x55 0x0A dari master,
 * modul membalas SATU baris JSON diakhiri \n (mode kirim RS485: DE/RE = HIGH).
 *
 * JSON balasan (contoh):
 * {"v":1.0,"i":2.35,"t":28.5,"h":62.0,"mv":true}
 *   i = nilai debit (skala Anda, mis. L/menit); tetap pakai key "i" agar kompatibel dengan ESP32.
 *
 * Pin (sesuaikan wiring):
 * - MAX485 RO -> D10, DI -> D11, DE+RE -> D7
 * - DHT22 DATA -> D4 (+ resistor 10k ke VCC jika modul tanpa onboard)
 * - SW420 DOUT -> D5
 * - Keluaran analog sensor aliran air -> A0 (contoh: modul dengan tegangan proporsional debit)
 *
 * Kalibrasi: sesuaikan FLOW_ZERO_VOLTS dan FLOW_VOLTS_PER_LPM dengan cara ukur di lapangan.
 */

#include <Arduino.h>
#include <SoftwareSerial.h>
#include <ArduinoJson.h>
#include <DHT.h>

#define RS485_RX_PIN 10
#define RS485_TX_PIN 11
#define RS485_DE_PIN 7

#define DHTPIN 4
#define DHTTYPE DHT22
#define SW420_PIN 5
#define FLOW_ANALOG A0

/** Tegangan ADC referensi (Nano klasik 5V) */
static const float ADC_REF = 5.0f;
static const int ADC_MAX = 1023;

/**
 * Kalibrasi sensor aliran (contoh: 0 V = nol aliran, slope ke L/menit).
 * Ganti sesuai datasheet modul / uji empirik.
 */
static const float FLOW_ZERO_VOLTS = 0.0f;
static const float FLOW_VOLTS_PER_LPM = 1.0f;

SoftwareSerial RS485(RS485_RX_PIN, RS485_TX_PIN);
DHT dht(DHTPIN, DHTTYPE);

static float round2f(float x) {
  return (float)((int)(x * 100.0f + (x >= 0 ? 0.5f : -0.5f))) / 100.0f;
}

static float round1f(float x) {
  return (float)((int)(x * 10.0f + (x >= 0 ? 0.5f : -0.5f))) / 10.0f;
}

static unsigned long lastDhtMs = 0;
static float lastTemp = NAN;
static float lastHum = NAN;
static const unsigned long DHT_INTERVAL_MS = 2200;

void rs485SetTx(bool tx) {
  digitalWrite(RS485_DE_PIN, tx ? HIGH : LOW);
}

float readFlowLpm() {
  long sum = 0;
  const int n = 16;
  for (int i = 0; i < n; i++) {
    sum += analogRead(FLOW_ANALOG);
    delayMicroseconds(150);
  }
  float raw = (float)sum / (float)n;
  float v = raw * (ADC_REF / (float)ADC_MAX);
  float lpm = (v - FLOW_ZERO_VOLTS) / FLOW_VOLTS_PER_LPM;
  if (lpm < 0.0f) {
    lpm = 0.0f;
  }
  return lpm;
}

void refreshDhtIfNeeded() {
  unsigned long now = millis();
  if (now - lastDhtMs < DHT_INTERVAL_MS) {
    return;
  }
  lastDhtMs = now;
  float t = dht.readTemperature();
  float h = dht.readHumidity();
  if (!isnan(t)) {
    lastTemp = t;
  }
  if (!isnan(h)) {
    lastHum = h;
  }
}

void sendJsonResponse() {
  refreshDhtIfNeeded();

  const bool moving = digitalRead(SW420_PIN) == HIGH;
  const float vib = moving ? 1.0f : 0.0f;
  const float flowLpm = readFlowLpm();

  StaticJsonDocument<192> doc;
  doc["v"] = vib;
  doc["i"] = round2f(flowLpm);
  doc["t"] = isnan(lastTemp) ? 0.0f : round1f(lastTemp);
  doc["h"] = isnan(lastHum) ? 0.0f : round1f(lastHum);
  doc["mv"] = moving;

  char line[160];
  size_t n = serializeJson(doc, line, sizeof(line));
  if (n == 0 || n >= sizeof(line)) {
    return;
  }

  rs485SetTx(true);
  RS485.write((const uint8_t *)line, n);
  RS485.write('\n');
  RS485.flush();
  rs485SetTx(false);
}

void setup() {
  Serial.begin(115200);
  pinMode(RS485_DE_PIN, OUTPUT);
  rs485SetTx(false);
  RS485.begin(9600);

  pinMode(SW420_PIN, INPUT);
  dht.begin();

  Serial.println(F("Nano Alsintan slave RS485 siap (sensor aliran air + DHT22 + SW420)."));
}

void loop() {
  static uint8_t step = 0;

  while (RS485.available()) {
    int b = RS485.read();
    if (b < 0) {
      break;
    }
    if (step == 0 && b == 0xAA) {
      step = 1;
    } else if (step == 1 && b == 0x55) {
      step = 2;
    } else if (step == 2 && b == 0x0A) {
      sendJsonResponse();
      step = 0;
    } else if (b == 0xAA) {
      step = 1;
    } else {
      step = 0;
    }
  }
}
