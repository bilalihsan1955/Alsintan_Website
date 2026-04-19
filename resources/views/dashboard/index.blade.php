<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Alsintan — {{ config('app.name', 'Laravel') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['DM Sans', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                },
            },
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <style>
        html, body { height: 100%; }
        #map { min-height: 280px; position: relative; z-index: 0; isolation: isolate; }
        @media (min-width: 768px) {
            #map { min-height: 420px; }
        }
        /* Kontrol Leaflet di bawah agar tidak tertutup navbar fixed */
        #map .leaflet-bottom .leaflet-control { margin-bottom: 0.5rem; }
        @keyframes als-pulse {
            50% { opacity: 0.5; }
        }
        .als-live-dot {
            animation: als-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        nav[role="navigation"] a,
        nav[role="navigation"] span {
            background: #fff !important;
            color: #0f172a !important;
            border-color: #e2e8f0 !important;
        }
        nav[role="navigation"] span[aria-current="page"] span,
        nav[role="navigation"] span[aria-current="page"] {
            color: #059669 !important;
            font-weight: 700 !important;
        }
    </style>
</head>
<body class="min-h-full bg-slate-50 text-slate-900 antialiased">
    @include('partials.alsintan-nav', ['active' => 'dashboard'])
    <div class="als-page-content mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700">Monitoring peralatan pertanian</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Dashboard Alsintan</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">Data sensor dan peta diperbarui otomatis setiap beberapa detik.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span id="d-poll-status" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-800">
                    <span class="h-2 w-2 rounded-full bg-emerald-500 als-live-dot" aria-hidden="true"></span>
                    Pembaruan otomatis
                </span>
                @php
                    $tractorSelectOptions = $tractors->mapWithKeys(fn ($t) => [$t->id => ($t->name ?: $t->device_id)])->all();
                    if (count($tractorSelectOptions) === 0) {
                        $tractorSelectOptions = ['' => 'Belum ada traktor'];
                    }
                @endphp
                <form method="get" action="{{ route('dashboard') }}" class="flex flex-wrap items-center gap-2" id="tractor-form">
                    <label for="tractor-select" class="sr-only">Traktor</label>
                    <x-ui.custom-select name="tractor" id="tractor-select" variant="hero" :options="$tractorSelectOptions" :selected="$tractor?->id" class="block w-full min-w-[200px] sm:w-auto" />
                    <noscript>
                        <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white">Terapkan</button>
                    </noscript>
                </form>
            </div>
        </header>

        @if (!$tractor)
            <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-10 text-center shadow-sm">
                <p class="text-lg font-semibold text-slate-800">Belum ada data traktor</p>
                <p class="mt-2 text-sm text-slate-600">Kirim telemetri dari perangkat ESP32 ke API agar traktor terdaftar otomatis, lalu muat ulang halaman ini.</p>
            </div>
        @else
            @php
                $fmt = fn ($v, $u = '') => $v === null ? '—' : number_format((float) $v, 1).$u;
                $pathKm = $pathLengthM / 1000;
                $flowVal = (float) ($flowValue ?? 0);
            @endphp

            <section class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100/80">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-medium text-slate-500">Suhu</h2>
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-orange-50 text-lg" aria-hidden="true">🌡️</span>
                    </div>
                    <p id="d-temp" class="mt-3 text-2xl font-bold tabular-nums text-slate-900">{{ $fmt($latestSensor?->temperature, ' °C') }}</p>
                    <p class="mt-1 text-xs text-slate-500">Sensor suhu mesin</p>
                </article>
                <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100/80">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-medium text-slate-500">Kelembapan</h2>
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-sky-50 text-lg" aria-hidden="true">💧</span>
                    </div>
                    <p id="d-hum" class="mt-3 text-3xl font-bold tabular-nums text-slate-900">{{ $fmt($latestSensor?->humidity, ' %') }}</p>
                    <p class="mt-1 text-xs text-slate-500">Kelembapan lingkungan mesin</p>
                </article>
                <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100/80">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-medium text-slate-500">Flow BBM</h2>
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-cyan-50 text-lg" aria-hidden="true">🌀</span>
                    </div>
                    <p id="d-flow" class="mt-3 text-3xl font-bold tabular-nums text-slate-900">{{ number_format($flowVal, 1) }} L/jam</p>
                    <p class="mt-1 text-xs text-slate-500">Aliran bahan bakar · <span id="d-live">{{ $liveUpdatedAt ? $liveUpdatedAt->timezone(config('app.timezone'))->diffForHumans() : '—' }}</span></p>
                </article>
                <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100/80">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-medium text-slate-500">SW420 · mesin hidup/mati</h2>
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-violet-50 text-lg" aria-hidden="true">📳</span>
                    </div>
                    <p id="d-sw" class="mt-3 text-3xl font-bold tabular-nums text-slate-900">
                        @if ($latestSensor?->is_moving !== null)
                            {{ $latestSensor->is_moving ? 'ON' : 'OFF' }}
                        @else
                            —
                        @endif
                    </p>
                    <p class="mt-1 text-xs text-slate-500">Deteksi mesin menyala atau tidak; ON = mesin berjalan, OFF = berhenti.</p>
                </article>
            </section>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm ring-1 ring-slate-100/80">
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <div>
                                <h2 class="text-base font-semibold text-slate-900">Peta &amp; jalur</h2>
                                <p class="text-xs text-slate-500">Leaflet + Google Satellite · setiap titik GPS ditampilkan di peta (klik untuk detail)</p>
                            </div>
                            <span id="d-track-count" class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-800">{{ count($trackPoints) }} titik</span>
                        </div>
                        <div id="map" class="w-full rounded-b-2xl" aria-label="Peta jalur GPS dengan titik lokasi"></div>
                    </div>
                </div>
                <aside class="flex flex-col gap-4">
                    <div class="relative overflow-hidden rounded-2xl border border-emerald-100/80 bg-gradient-to-br from-white via-white to-emerald-50/40 p-5 shadow-sm ring-1 ring-slate-100/90">
                        <div class="pointer-events-none absolute -right-6 -top-8 h-28 w-28 rounded-full bg-emerald-400/15 blur-2xl"></div>
                        <div class="relative flex gap-3">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-800 shadow-inner ring-1 ring-emerald-200/60" aria-hidden="true">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700">Panjang jalur</p>
                                <h2 class="mt-0.5 text-base font-bold leading-tight text-slate-900">Akumulasi jarak antar titik</h2>
                                <p class="mt-2 text-xs leading-relaxed text-slate-600">Menjumlahkan segmen lurus antar titik GPS berurutan di peta. Bukan keliling lahan.</p>
                                <div class="mt-4 flex flex-wrap items-end gap-x-2 gap-y-1 border-t border-emerald-100/80 pt-4">
                                    @if ($pathLengthM > 0)
                                        @if ($pathKm >= 1)
                                            <span id="d-path" class="text-3xl font-bold tabular-nums tracking-tight text-slate-900">{{ number_format($pathKm, 2) }}</span>
                                            <span id="d-path-unit" class="mb-1 text-sm font-semibold text-emerald-700">km</span>
                                        @else
                                            <span id="d-path" class="text-3xl font-bold tabular-nums tracking-tight text-slate-900">{{ number_format($pathLengthM, 0) }}</span>
                                            <span id="d-path-unit" class="mb-1 text-sm font-semibold text-emerald-700">m</span>
                                        @endif
                                    @else
                                        <span id="d-path" class="text-base font-medium text-slate-400">Belum ada titik GPS</span>
                                        <span id="d-path-unit" class="hidden text-sm font-semibold text-emerald-700" aria-hidden="true"></span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100/80">
                        <p class="text-sm font-semibold text-slate-900">Perangkat: {{ $tractor->device_id }}</p>
                        @if ($tractor->name)
                            <p class="mt-1 text-sm text-slate-600">{{ $tractor->name }}</p>
                        @endif
                        <p class="mt-3 text-xs text-slate-500">
                            Pembaruan otomatis lewat <span class="font-mono text-[11px] text-slate-600">/dashboard/data</span> setiap 5 detik.
                        </p>
                    </div>
                </aside>
            </div>

            <section id="riwayat-sensor" class="mt-10 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm ring-1 ring-slate-100/80">
                <div class="border-b border-slate-100 px-5 py-4">
                    <div class="flex flex-col gap-4">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">Riwayat sensor mesin</h2>
                            <p class="mt-1 text-xs text-slate-500">Tabel ini berisi riwayat pembacaan telemetri per waktu; gunakan filter untuk mempercepat penelusuran.</p>
                        </div>
                        @php
                            $histDateCtrl = 'min-h-[38px] w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20';
                        @endphp
                        <form id="history-filter-form" method="get" action="{{ route('dashboard') }}" class="min-w-0 w-full">
                            <input type="hidden" name="tractor" value="{{ $tractor->id }}">
                            {{-- Sama pola dengan x-strategic.filter-toolbar: filter mengisi tengah, Tombol Terapkan/Reset ml-auto tanpa celah kosong --}}
                            <div class="flex flex-wrap items-end gap-2 lg:flex-nowrap lg:overflow-x-auto lg:pb-0.5 lg:[scrollbar-width:thin]">
                                <label class="flex w-[calc(50%-0.25rem)] flex-col gap-0.5 text-xs font-medium text-slate-600 sm:w-[9.5rem] sm:flex-shrink-0">
                                    <span>Dari tanggal</span>
                                    <input type="date" name="hist_from" value="{{ request('hist_from') }}" class="{{ $histDateCtrl }}">
                                </label>
                                <label class="flex w-[calc(50%-0.25rem)] flex-col gap-0.5 text-xs font-medium text-slate-600 sm:w-[9.5rem] sm:flex-shrink-0">
                                    <span>Sampai tanggal</span>
                                    <input type="date" name="hist_to" value="{{ request('hist_to') }}" class="{{ $histDateCtrl }}">
                                </label>
                                <div class="flex w-full min-w-0 flex-wrap items-end gap-2 lg:w-auto lg:flex-1 lg:flex-nowrap">
                                    <label class="flex min-w-[10rem] flex-1 flex-col gap-0.5 text-xs font-medium text-slate-600">
                                        <span>Cari</span>
                                        <input type="text" name="hist_q" value="{{ request('hist_q') }}" placeholder="status / payload" class="min-h-[38px] w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                                    </label>
                                    <label class="flex w-full min-w-[7.5rem] max-w-[9rem] flex-shrink-0 flex-col gap-0.5 text-xs font-medium text-slate-600 sm:w-[7.5rem]">
                                        <span>Urutkan</span>
                                        <x-ui.custom-select name="hist_order" :options="['desc' => 'Terkini', 'asc' => 'Awal']" :selected="request('hist_order') === 'asc' ? 'asc' : 'desc'" class="w-full" />
                                    </label>
                                </div>
                                <div class="ml-auto flex shrink-0 gap-2">
                                    <button type="submit" class="inline-flex min-h-[38px] items-center justify-center whitespace-nowrap rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">Terapkan</button>
                                    <a href="{{ route('dashboard', ['tractor' => $tractor->id]) }}" class="inline-flex min-h-[38px] items-center justify-center whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-4 py-3">Waktu</th>
                                <th class="px-4 py-3">Suhu</th>
                                <th class="px-4 py-3">Kelembapan</th>
                                <th class="px-4 py-3">SW420</th>
                                <th class="px-4 py-3">Flow BBM</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($historyRows as $row)
                                @php
                                    $rp = is_array($row->raw_payload) ? $row->raw_payload : (json_decode((string) ($row->raw_payload ?? ''), true) ?: []);
                                    $rs = $rp['sensor'] ?? [];
                                    $rTemp = $rs['temperature'] ?? null;
                                    $rHum = $rs['humidity'] ?? null;
                                    $rFlow = $rs['flow'] ?? $row->fuel_lph;
                                    $rSwDigital = null;
                                    if (array_key_exists('is_moving', $rs)) {
                                        $rSwDigital = (bool) $rs['is_moving'];
                                    } elseif (array_key_exists('sw420', $rs)) {
                                        $rSwDigital = (bool) $rs['sw420'];
                                    }
                                    if ($rSwDigital === null && $row->engine_on !== null) {
                                        $rSwDigital = (bool) $row->engine_on;
                                    }
                                @endphp
                                <tr class="hover:bg-slate-50/80">
                                    <td class="whitespace-nowrap px-4 py-2.5 text-xs font-mono text-slate-600">{{ optional($row->ts)->timezone(config('app.timezone'))->format('d/m H:i:s') }}</td>
                                    <td class="px-4 py-2.5 text-xs tabular-nums text-slate-700">{{ $rTemp !== null ? number_format((float) $rTemp, 1).' °C' : '—' }}</td>
                                    <td class="px-4 py-2.5 text-xs tabular-nums text-slate-700">{{ $rHum !== null ? number_format((float) $rHum, 1).' %' : '—' }}</td>
                                    <td class="px-4 py-2.5 text-xs text-slate-700">
                                        @if ($rSwDigital !== null)
                                            <span class="font-medium">{{ $rSwDigital ? 'ON' : 'OFF' }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-xs tabular-nums text-slate-700">{{ $rFlow !== null ? number_format((float) $rFlow, 1).' L/jam' : '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">Belum ada data telemetri pada filter ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-nowrap items-center justify-between gap-2 overflow-x-auto border-t border-slate-100 bg-slate-50/50 px-5 py-2.5 [scrollbar-width:thin]">
                    <form method="get" action="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-2">
                        <x-strategic.preserver :except="['hist_per_page', 'hist_page']" />
                        <label class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600">
                            <span class="whitespace-nowrap">Data/hal.</span>
                            <x-ui.custom-select name="hist_per_page" :options="['10' => '10', '50' => '50']" :selected="(string) request('hist_per_page', '10')" submit-on-change class="w-[4.5rem]" />
                        </label>
                    </form>
                    <div class="flex min-w-0 flex-1 items-center justify-end gap-3">
                        <p class="shrink-0 text-xs tabular-nums text-slate-600">
                            <span class="font-medium text-slate-800">{{ $historyRows->firstItem() ?? 0 }}</span>
                            –
                            <span class="font-medium text-slate-800">{{ $historyRows->lastItem() ?? 0 }}</span>
                            <span class="text-slate-400"> / </span>
                            <span class="font-medium text-slate-800">{{ $historyRows->total() }}</span>
                        </p>
                        <div class="als-pagination-wrap flex shrink-0 justify-end">{{ $historyRows->links('pagination.strategic-simple') }}</div>
                    </div>
                </div>
            </section>
        @endif
    </div>

    <script src="{{ asset('js/als-custom-select.js') }}"></script>
    @if ($tractor)
        <script>
            (function () {
                const DASH_DATA_URL = @json(route('dashboard.data'));
                const initialTrack = @json($trackPoints);

                let map = null;
                let polyline = null;
                let nodeLayer = null;
                let didAutoFit = false;

                function fmtSensor(v, dec, suffix) {
                    if (v === null || v === undefined || Number.isNaN(Number(v))) return '—';
                    return Number(v).toFixed(dec) + suffix;
                }

                function initMap(track) {
                    map = L.map('map', { scrollWheelZoom: true, zoomControl: false });
                    (function () {
                        var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            subdomains: 'abc',
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" rel="noreferrer">OpenStreetMap</a>'
                        });
                        var sat = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
                            maxZoom: 22,
                            attribution: '&copy; Google'
                        });
                        sat.addTo(map);
                        L.control.layers({ Satelit: sat, Peta: osm }, null, { position: 'bottomright' }).addTo(map);
                        L.control.zoom({ position: 'bottomleft' }).addTo(map);
                    })();
                    updateTrack(track);
                }

                function updateTrack(track) {
                    if (!map) return;
                    if (polyline) {
                        map.removeLayer(polyline);
                        polyline = null;
                    }
                    if (nodeLayer) {
                        map.removeLayer(nodeLayer);
                        nodeLayer = null;
                    }
                    if (!track || track.length === 0) {
                        try {
                            map.fitWorld();
                        } catch (e) { /* leaflet */ }
                        return;
                    }
                    const n = track.length;
                    const latlngs = track.map(function (p) { return [p.lat, p.lng]; });
                    polyline = L.polyline(latlngs, { color: '#059669', weight: 4, opacity: 0.85 }).addTo(map);

                    nodeLayer = L.layerGroup();
                    track.forEach(function (p, i) {
                        const isFirst = i === 0;
                        const isLast = i === n - 1;
                        let radius = 4;
                        let color = '#0f766e';
                        let fill = '#5eead4';
                        let weight = 1;
                        if (n === 1) {
                            radius = 11;
                            color = '#047857';
                            fill = '#10b981';
                            weight = 2;
                        } else if (isFirst) {
                            radius = 7;
                            color = '#1d4ed8';
                            fill = '#93c5fd';
                            weight = 2;
                        } else if (isLast) {
                            radius = 10;
                            color = '#047857';
                            fill = '#10b981';
                            weight = 2;
                        }
                        const cm = L.circleMarker([p.lat, p.lng], {
                            radius: radius,
                            color: color,
                            fillColor: fill,
                            fillOpacity: 0.92,
                            weight: weight
                        });
                        var title = 'Titik ' + (i + 1) + ' dari ' + n;
                        if (isFirst && n > 1) title += ' (awal)';
                        if (isLast && n > 1) title += ' (terakhir)';
                        var html = '<div class="text-sm"><strong>' + title + '</strong><br>' +
                            '<span class="font-mono text-xs text-slate-600">' +
                            Number(p.lat).toFixed(6) + ', ' + Number(p.lng).toFixed(6) + '</span>';
                        if (p.ts) {
                            html += '<br><span class="text-xs text-slate-500">' + String(p.ts) + '</span>';
                        }
                        html += '</div>';
                        cm.bindPopup(html);
                        nodeLayer.addLayer(cm);
                    });
                    nodeLayer.addTo(map);

                    const last = track[track.length - 1];
                    if (!didAutoFit) {
                        try {
                            map.fitBounds(polyline.getBounds().pad(0.12));
                        } catch (e) {
                            map.setView([last.lat, last.lng], 15);
                        }
                        didAutoFit = true;
                    }
                }

                function applyPayload(d) {
                    const s = d.sensor;
                    const g = d.gps;

                    document.getElementById('d-temp').textContent = fmtSensor(s.temperature, 1, ' °C');
                    document.getElementById('d-hum').textContent = fmtSensor(s.humidity, 1, ' %');
                    document.getElementById('d-flow').textContent = Number(s.flow).toFixed(1) + ' L/jam';
                    (function () {
                        var el = document.getElementById('d-sw');
                        var a;
                        if (s.is_moving === true) a = 'ON';
                        else if (s.is_moving === false) a = 'OFF';
                        else a = '—';
                        el.textContent = a || '—';
                    })();

                    const cnt = document.getElementById('d-track-count');
                    cnt.textContent = g.point_count + ' titik';
                    updateTrack(g.track);

                    const pathEl = document.getElementById('d-path');
                    const unitEl = document.getElementById('d-path-unit');
                    const pm = Number(g.path_length_m);
                    if (pm > 0) {
                        pathEl.className = 'text-3xl font-bold tabular-nums tracking-tight text-slate-900';
                        if (pm >= 1000) {
                            pathEl.textContent = (pm / 1000).toFixed(2);
                            unitEl.textContent = 'km';
                        } else {
                            pathEl.textContent = String(Math.round(pm));
                            unitEl.textContent = 'm';
                        }
                        unitEl.classList.remove('hidden');
                        unitEl.removeAttribute('aria-hidden');
                    } else {
                        pathEl.className = 'text-base font-medium text-slate-400';
                        pathEl.textContent = 'Belum ada titik GPS';
                        unitEl.textContent = '';
                        unitEl.classList.add('hidden');
                        unitEl.setAttribute('aria-hidden', 'true');
                    }

                    const live = document.getElementById('d-live');
                    live.textContent = d.live_updated_human || '—';
                }

                async function refreshDashboard() {
                    const sel = document.getElementById('tractor-select');
                    const tid = sel && sel.value ? sel.value : '';
                    if (!tid) return;
                    try {
                        const r = await fetch(DASH_DATA_URL + '?tractor=' + encodeURIComponent(tid), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        if (!r.ok) return;
                        const d = await r.json();
                        if (d.ok) applyPayload(d);
                    } catch (e) { /* abaikan jaringan putus */ }
                }

                initMap(initialTrack);

                document.getElementById('tractor-select').addEventListener('change', function () {
                    didAutoFit = false;
                    const v = this.value;
                    const u = new URL(window.location.href);
                    if (v) u.searchParams.set('tractor', v);
                    else u.searchParams.delete('tractor');
                    window.history.replaceState({}, '', u);
                    refreshDashboard();
                });

                const pollMs = 5000;
                setInterval(refreshDashboard, pollMs);
                refreshDashboard();
            })();
        </script>
    @endif
</body>
</html>
