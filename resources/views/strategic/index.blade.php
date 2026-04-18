@php
    $impact = $kpi['impact'] ?? [];
    $summaryText = $impact['summary_text'] ?? ($kpi['summary_text'] ?? 'Ringkasan dampak operasional Alsintan untuk periode terpilih.');
    $fuelSave = (float) ($impact['fuel_saving_pct'] ?? $kpi['fuel_saving_pct'] ?? 0);
    $downReduce = (float) ($impact['downtime_reduction_pct'] ?? $kpi['downtime_reduction_pct'] ?? 0);
    $utilInc = (float) ($impact['utilization_increase_pct'] ?? $kpi['utilization_increase_pct'] ?? 0);

    $gradeVariant = fn ($g) => match (strtoupper((string) $g)) {
        'A' => 'grade-a',
        'B' => 'grade-b',
        'C' => 'grade-c',
        'D' => 'grade-d',
        default => 'neutral',
    };
    $sevVariant = fn ($s) => match (strtoupper((string) $s)) {
        'HIGH' => 'sev-high',
        'MEDIUM' => 'sev-medium',
        'LOW' => 'sev-low',
        default => 'neutral',
    };
    $utilVariant = fn ($s) => match (strtoupper((string) $s)) {
        'HIGH', 'TINGGI' => 'ut-high',
        'MEDIUM', 'SEDANG', 'MID' => 'ut-mid',
        'LOW', 'RENDAH' => 'ut-low',
        default => 'neutral',
    };
    $utilLabel = fn ($s) => match (strtoupper((string) $s)) {
        'HIGH' => 'TINGGI',
        'MEDIUM', 'MID' => 'SEDANG',
        'LOW' => 'RENDAH',
        default => $s ?: '—',
    };
    $mpVariant = fn ($s) => match (strtoupper((string) $s)) {
        'DONE' => 'mp-done',
        'PENDING' => 'mp-pending',
        'OVERDUE' => 'mp-overdue',
        default => 'neutral',
    };
    $healthTypeLabel = fn ($t) => match (strtolower((string) $t)) {
        'replacement', 'penggantian' => 'Penggantian',
        'repair', 'perbaikan' => 'Perbaikan',
        'damage', 'kerusakan' => 'Kerusakan',
        default => ucfirst((string) $t),
    };
    $zonePolygonMeta = function (\App\Models\GeofenceZone $z): array {
        $raw = $z->polygon_json;
        if ($raw === null || trim((string) $raw) === '') {
            return ['pts' => 0, 'preview' => '—'];
        }
        $j = json_decode((string) $raw, true);
        if (! is_array($j)) {
            return ['pts' => 0, 'preview' => '—'];
        }
        $ring = $j;
        if (isset($j['type'], $j['coordinates'])) {
            $t = strtolower((string) $j['type']);
            if ($t === 'polygon' && is_array($j['coordinates'][0] ?? null)) {
                $ring = $j['coordinates'][0];
            } elseif ($t === 'multipolygon' && is_array($j['coordinates'][0][0] ?? null)) {
                $ring = $j['coordinates'][0][0];
            }
        }
        if (! is_array($ring)) {
            return ['pts' => 0, 'preview' => '—'];
        }
        $n = count($ring);
        $first = $ring[0] ?? null;
        if (is_array($first)) {
            if (array_key_exists(0, $first) && array_key_exists(1, $first)) {
                $a = (float) $first[0];
                $b = (float) $first[1];

                return ['pts' => $n, 'preview' => number_format($a, 5).', '.number_format($b, 5)];
            }
            if (isset($first['lat'], $first['lng'])) {
                $a = (float) $first['lat'];
                $b = (float) $first['lng'];

                return ['pts' => $n, 'preview' => number_format($a, 5).', '.number_format($b, 5)];
            }
        }

        return ['pts' => $n, 'preview' => '—'];
    };

    $gfTypeSel = strtoupper((string) request('gf_type'));
    $gfTypeSel = in_array($gfTypeSel, ['ENTER', 'EXIT'], true) ? $gfTypeSel : '';

    $gpGradeSel = strtoupper((string) request('gp_grade'));
    $gpGradeSel = in_array($gpGradeSel, ['A', 'B', 'C', 'D'], true) ? $gpGradeSel : '';

    $anStatusSel = (string) request('an_status');
    $anStatusSel = in_array($anStatusSel, ['OPEN', 'RESOLVED'], true) ? $anStatusSel : '';

    $anSevSel = (string) request('an_severity');
    $anSevSel = in_array($anSevSel, ['HIGH', 'MEDIUM', 'LOW'], true) ? $anSevSel : '';

    $utStatusSel = strtoupper((string) request('ut_status'));
    $utStatusSel = in_array($utStatusSel, ['TINGGI', 'SEDANG', 'RENDAH', 'HIGH', 'MEDIUM', 'LOW'], true) ? $utStatusSel : '';

    $mpStatusSel = strtoupper((string) request('mp_status'));
    $mpStatusSel = in_array($mpStatusSel, ['DONE', 'PENDING', 'OVERDUE'], true) ? $mpStatusSel : '';

    $mrTypeSel = strtolower((string) request('mr_type'));
    $mrTypeSel = in_array($mrTypeSel, ['penggantian', 'perbaikan', 'kerusakan', 'replacement', 'repair', 'damage'], true) ? $mrTypeSel : '';

    $strategicQuery = request()->getQueryString();
    $strategicSuffix = ($strategicQuery !== null && $strategicQuery !== '') ? '?'.$strategicQuery : '';
@endphp
<!DOCTYPE html>
<html lang="id" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Strategic Dashboard — {{ config('app.name', 'Alsintan') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['DM Sans', 'ui-sans-serif', 'system-ui'] } } } };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
    <style>
        #strategic-fleet-map { min-height: 320px; position: relative; z-index: 0; isolation: isolate; }
        @media (min-width: 768px) { #strategic-fleet-map { min-height: 420px; } }
        .als-strat-map-card { overflow: visible; }
        /* Sorotan chip zona (kelas di-toggle dari JS; inset agar tidak terpotong overflow-y) */
        .als-zone-pick-btn.als-zone-pick-active {
            background-color: rgb(254 252 232);
            border-color: rgb(245 158 11);
            box-shadow: inset 0 0 0 2px rgb(245 158 11);
        }
        .als-pagination-wrap nav[role="navigation"] a,
        .als-pagination-wrap nav[role="navigation"] span { background: #fff !important; color: #0f172a !important; border-color: #e2e8f0 !important; }
        .als-section-title::before { content: ''; display: inline-block; width: 4px; border-radius: 9999px; background: linear-gradient(180deg, #10b981, #059669); margin-right: 0.75rem; min-height: 1.5rem; vertical-align: middle; }
        .als-vertex-root { background: transparent !important; border: none !important; }
        .als-vertex-dot {
            width: 14px; height: 14px; margin: 4px; border-radius: 9999px;
            background: #fff; border: 2px solid #f59e0b; box-shadow: 0 1px 4px rgb(0 0 0 / 0.25);
            cursor: grab;
        }
        .als-vertex-dot:active { cursor: grabbing; }
        /* Modal zona: kontrol display hanya lewat .is-open (hindari bentrok Tailwind vs inline style). */
        .als-zone-modal-layer {
            position: fixed;
            inset: 0;
            z-index: 999999 !important;
            box-sizing: border-box;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .als-zone-modal-layer.is-open {
            display: flex !important;
        }
        /* Peta gambar polygon di dalam modal tambah zona */
        #als-zone-modal-map,
        #als-zone-edit-map {
            min-height: 14rem;
            height: 14rem;
            width: 100%;
            border-radius: 0.5rem;
            z-index: 0;
        }
        /* Kontrol Leaflet di bawah (zoom + lapisan) agar tidak tertutup navbar fixed */
        #strategic-fleet-map .leaflet-bottom .leaflet-control,
        #als-zone-modal-map .leaflet-bottom .leaflet-control,
        #als-zone-edit-map .leaflet-bottom .leaflet-control { margin-bottom: 0.5rem; }
        /* Modal tambah / edit zona: lebar panel + grid 2 kolom alat */
        #als-zone-modal-add .als-zone-modal-add-panel,
        #als-zone-modal-edit .als-zone-modal-add-panel {
            width: 100%;
            max-width: min(92vw, 40rem);
        }
        #als-zone-modal-add .als-zone-tractor-grid,
        #als-zone-modal-edit .als-zone-tractor-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            column-gap: 0.75rem;
            row-gap: 0.375rem;
        }
        @media (max-width: 360px) {
            #als-zone-modal-add .als-zone-tractor-grid,
            #als-zone-modal-edit .als-zone-tractor-grid {
                grid-template-columns: 1fr;
            }
        }
        .als-strategic-flash {
            transition: opacity 0.25s ease, transform 0.25s ease;
        }
        .als-strategic-flash.is-leaving {
            opacity: 0;
            transform: translateY(-0.25rem);
        }
        #als-zone-native-confirm::backdrop {
            background: rgba(15, 23, 42, 0.5);
        }
    </style>
</head>
<body class="h-full bg-slate-50 pb-28 text-slate-900 antialiased md:pb-8">
    @include('partials.alsintan-nav', ['active' => 'strategic'])

    <div class="als-page-content mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        @if (session('ok'))
            <div class="als-strategic-flash relative mb-6 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 pr-10 text-sm font-medium text-emerald-900" role="status" data-auto-dismiss="8">
                <span class="min-w-0 flex-1">{{ session('ok') }}</span>
                <button type="button" class="als-strategic-flash-close absolute right-2 top-2 rounded-md p-1 text-emerald-800/70 hover:bg-emerald-100 hover:text-emerald-950 focus:outline-none focus:ring-2 focus:ring-emerald-500" aria-label="Tutup pemberitahuan">&times;</button>
            </div>
        @endif
        @if ($errors->any())
            <div class="als-strategic-flash relative mb-6 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 pr-10 text-sm text-rose-900" role="alert" data-auto-dismiss="12">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">Tidak dapat menyimpan zona:</p>
                    <ul class="mt-2 list-inside list-disc text-xs">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
                <button type="button" class="als-strategic-flash-close absolute right-2 top-2 rounded-md p-1 text-rose-800/70 hover:bg-rose-100 hover:text-rose-950 focus:outline-none focus:ring-2 focus:ring-rose-500" aria-label="Tutup pesan kesalahan">&times;</button>
            </div>
        @endif
        <script>
            (function () {
                function dismissFlash(el) {
                    if (!el || el.classList.contains('is-leaving')) return;
                    el.classList.add('is-leaving');
                    setTimeout(function () {
                        el.remove();
                    }, 280);
                }
                document.querySelectorAll('.als-strategic-flash').forEach(function (box) {
                    var btn = box.querySelector('.als-strategic-flash-close');
                    if (btn) btn.addEventListener('click', function () { dismissFlash(box); });
                    var secs = parseInt(box.getAttribute('data-auto-dismiss'), 10);
                    if (Number.isFinite(secs) && secs > 0) {
                        setTimeout(function () { dismissFlash(box); }, secs * 1000);
                    }
                });
            })();
        </script>
        <header class="mb-8">
            <p class="text-sm font-medium text-emerald-700">Analisis strategis</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Strategic Dashboard</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-600">Ringkasan KPI, geofence, dan kinerja armada untuk periode yang dipilih.</p>
        </header>

        {{-- 2) Stat cards — grid responsif --}}
        <section class="mb-8">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                @foreach([
                    ['label' => 'Total Alsintan', 'value' => number_format($kpi['total_tractors'] ?? 0), 'text' => 'text-sky-600'],
                    ['label' => 'Total Data Log', 'value' => number_format($kpi['total_telemetry_logs'] ?? 0), 'text' => 'text-violet-600'],
                    ['label' => 'Total BBM (L)', 'value' => number_format((float)($kpi['total_fuel_l'] ?? 0), 1), 'text' => 'text-amber-600'],
                    ['label' => 'Rata-rata Skor', 'value' => number_format((float)($kpi['avg_score'] ?? 0), 2), 'text' => 'text-emerald-600'],
                    ['label' => 'Anomali Belum Resolved', 'value' => number_format($kpi['open_anomalies'] ?? 0), 'text' => 'text-rose-600'],
                    ['label' => 'Total Biaya Perbaikan', 'value' => 'Rp '.number_format((float)($kpi['repair_cost_total'] ?? 0), 0, ',', '.'), 'text' => 'text-slate-800'],
                ] as $card)
                    <article class="min-w-0 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm ring-1 ring-slate-100/80">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                        <p class="mt-2 text-2xl font-bold tabular-nums {{ $card['text'] }}">{{ $card['value'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        {{-- 3) Impact ROI --}}
        <section class="mb-10 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm ring-1 ring-slate-100/80">
            <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-2xl">📈</span>
                    <div>
                        <h2 class="text-lg font-bold">Impact Analysis &amp; ROI</h2>
                        <p class="text-sm text-slate-500">Dampak strategis terhadap efisiensi armada</p>
                    </div>
                </div>
            </div>
            <div class="px-5 py-5">
                <p class="text-sm leading-relaxed text-slate-700">{{ $summaryText }}</p>
                <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="flex min-w-0 gap-3 rounded-2xl border border-emerald-100 bg-emerald-50/80 p-4">
                        <span class="text-2xl" aria-hidden="true">⛽</span>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-emerald-800">Penghematan BBM</p>
                            <p class="truncate text-2xl font-bold text-emerald-900">{{ number_format($fuelSave, 2) }}%</p>
                        </div>
                    </div>
                    <div class="flex min-w-0 gap-3 rounded-2xl border border-sky-100 bg-sky-50/80 p-4">
                        <span class="text-2xl" aria-hidden="true">⏱️</span>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-sky-800">Penurunan Downtime</p>
                            <p class="truncate text-2xl font-bold text-sky-900">{{ number_format($downReduce, 2) }}%</p>
                        </div>
                    </div>
                    <div class="flex min-w-0 gap-3 rounded-2xl border border-violet-100 bg-violet-50/80 p-4">
                        <span class="text-2xl" aria-hidden="true">📊</span>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-violet-800">Peningkatan Utilisasi</p>
                            <p class="truncate text-2xl font-bold text-violet-900">{{ number_format($utilInc, 2) }}%</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- 4) BBM — laju aliran (fuel_lph), satu grafik semua alat --}}
        <section id="fuel-flow-section" class="mb-10">
            <h2 class="als-section-title text-lg font-bold">BBM — laju aliran</h2>
            <p class="mt-1 max-w-3xl text-sm text-slate-600">
                Nilai tiap titik sama dengan kolom <strong>Flow BBM</strong> di Home (<span class="font-mono text-xs">sensor.flow</span> pada payload, jika ada, lalu <span class="font-mono text-xs">fuel_lph</span>). Rentang waktu mengikuti tanggal di atas (bukan filter tabel riwayat di Home).
            </p>
            <p class="mt-1 text-xs text-slate-500">
                {{ $from->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                —
                {{ $to->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
            </p>
            <div class="mt-6 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm ring-1 ring-slate-100/80">
                <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-slate-900">Laju BBM — semua alat</h3>
                        <span class="text-[11px] font-medium text-amber-800/90">L/jam</span>
                    </div>
                </div>
                <div class="p-4 sm:p-5">
                    @if (count($fuelFlowChart['datasets'] ?? []) === 0)
                        <p class="py-8 text-center text-sm text-slate-500">Belum ada data aliran BBM untuk periode ini.</p>
                    @else
                        <div class="relative h-80 w-full min-h-[16rem]">
                            <canvas id="als-fuel-flow-chart-all" class="als-fuel-flow-canvas" aria-label="Grafik laju BBM semua traktor"></canvas>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- 6) Geofencing --}}
        <section class="mb-10 space-y-6">
            <h2 class="als-section-title flex items-center text-lg font-bold">Smart Geofencing — Anti Pencurian</h2>
            @php
                $zoneStoreUrl = route('strategic.zones.store').$strategicSuffix;
                $zoneFormQuerySuffix = $strategicSuffix;
                $zoneApiBase = url('/strategic/zones');
            @endphp
            <div class="als-strat-map-card rounded-2xl border border-slate-100 bg-white shadow-sm ring-1 ring-slate-100/80">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4 sm:px-5">
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-semibold text-slate-900">Peta armada &amp; area kerja</h3>
                        <p class="mt-0.5 text-xs text-slate-600">Satu jenis zona (area kerja) untuk geofence. Jalur tipis = riwayat GPS per alat; antara log EXIT dan ENTER berikutnya alat dapat dilacak di telemetri.</p>
                    </div>
                    <div class="flex w-full shrink-0 flex-wrap items-center justify-end gap-2 text-[11px] sm:w-auto sm:flex-col sm:items-end sm:gap-1.5">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 font-medium text-emerald-800"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500" aria-hidden="true"></span>Area kerja</span>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2 py-0.5 text-slate-600"><span class="h-2 w-2 rounded-full bg-sky-500" aria-hidden="true"></span>Posisi alat</span>
                    </div>
                </div>
                <div class="relative p-3 sm:p-4">
                    <div id="strategic-fleet-map" class="w-full rounded-xl border border-slate-100 bg-slate-100/40 shadow-inner"></div>

                    @if ($geofenceZonesAll->isNotEmpty())
                        <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Pilih zona di peta</p>
                            <div class="als-zone-pick-scroll mt-1.5 flex max-h-28 flex-wrap gap-1.5 overflow-y-auto px-0.5 py-0.5">
                                @foreach ($geofenceZonesAll as $zPick)
                                    <button type="button" class="als-zone-pick-btn rounded-md border border-slate-200 bg-white px-2 py-1 text-left text-[11px] font-medium text-slate-800 shadow-sm hover:border-amber-400 hover:bg-amber-50" data-zone-id="{{ $zPick->id }}">{{ $zPick->name }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm ring-1 ring-slate-100/80">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 sm:flex-row sm:items-start sm:justify-between sm:px-5">
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-slate-900">Daftar zona geofence</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Polygon tidak valid = tidak digambar di peta. Kolom Alat = traktor yang dikaitkan (opsional). Klik baris untuk sorot di peta.</p>
                    </div>
                    <div class="flex shrink-0 flex-col gap-2 sm:items-end">
                        <button type="button" id="als-zone-draw-work" class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 sm:w-auto">+ Tambah area kerja</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-4 py-3">Nama</th>
                                <th class="px-4 py-3">Aktif</th>
                                <th class="px-4 py-3">Titik</th>
                                <th class="min-w-[10rem] px-4 py-3">Alat</th>
                                <th class="whitespace-nowrap px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($geofenceZonesAll as $zone)
                                @php
                                    $zm = $zonePolygonMeta($zone);
                                    $toggleUrl = route('strategic.zones.toggle', $zone);
                                    $deleteUrl = route('strategic.zones.delete', $zone);
                                    if ($strategicSuffix !== '') {
                                        $toggleUrl .= $strategicSuffix;
                                        $deleteUrl .= $strategicSuffix;
                                    }
                                @endphp
                                <tr class="als-zone-table-row cursor-pointer hover:bg-slate-50/80" data-zone-id="{{ $zone->id }}" title="Klik baris untuk sorot di peta">
                                    <td class="px-4 py-2.5 font-medium text-slate-900">{{ $zone->name }}</td>
                                    <td class="px-4 py-2.5" onclick="event.stopPropagation();">
                                        <form method="post" action="{{ $toggleUrl }}" class="inline-block">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="inline-flex min-w-[5.5rem] items-center justify-center rounded-full border px-2.5 py-1 text-[11px] font-semibold transition {{ $zone->is_active ? 'border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100' : 'border-slate-200 bg-slate-100 text-slate-600 hover:bg-slate-200' }}" title="Ubah status aktif">
                                                {{ $zone->is_active ? 'Aktif' : 'Nonaktif' }}
                                            </button>
                                        </form>
                                    </td>
                                    <td class="px-4 py-2.5 tabular-nums">{{ $zm['pts'] }}</td>
                                    <td class="max-w-[14rem] px-4 py-2.5 text-xs text-slate-700">
                                        @if ($zone->tractors->isEmpty())
                                            <span class="text-slate-400" title="Belum dikaitkan alat lewat edit zona">—</span>
                                        @else
                                            <ul class="list-none space-y-0.5 p-0 m-0">
                                                @foreach ($zone->tractors as $tz)
                                                    <li class="truncate font-mono text-[11px] leading-snug" title="{{ $tz->id }}{{ $tz->name ? ' — '.$tz->name : '' }}">{{ $tz->id }}@if ($tz->name)<span class="font-sans text-slate-500"> {{ $tz->name }}</span>@endif</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-right" onclick="event.stopPropagation();">
                                        <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                            <button type="button" class="als-zone-btn-edit rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-900 hover:bg-amber-100" data-zone-id="{{ $zone->id }}">Edit</button>
                                            <form method="post" action="{{ $deleteUrl }}" class="als-zone-row-delete-form inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="als-zone-row-delete-btn rounded-md border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-800 hover:bg-rose-100">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">Belum ada zona geofence di database.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <x-strategic.table-shell title="Geofence Alerts" subtitle="Keluar area kerja tercatat; masuk kembali tercatat. Di antara keduanya, lacak pergerakan lewat log telemetri / peta." icon="alert" fragment="gf-log-section" per-page-key="gf_per_page" :paginator="$geofenceAlerts" new-tab-anchor="#gf-log-section">
                <x-slot name="toolbar">
                    <x-strategic.filter-toolbar :preserver-except="['gf_q', 'gf_type', 'gf_page']" :reset-keys="['gf_q', 'gf_type', 'gf_page', 'gf_per_page']" reset-fragment="gf-log-section">
                        <input type="search" name="gf_q" value="{{ request('gf_q') }}" placeholder="Cari alat / pesan…" class="min-h-[38px] min-w-[7rem] max-w-full flex-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                        <x-ui.custom-select name="gf_type" :options="['' => 'Semua tipe', 'ENTER' => 'ENTER', 'EXIT' => 'EXIT']" :selected="$gfTypeSel" class="w-[7.25rem] flex-shrink-0" />
                    </x-strategic.filter-toolbar>
                </x-slot>
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3">Waktu</th>
                            <th class="whitespace-nowrap px-4 py-3">Alat</th>
                            <th class="whitespace-nowrap px-4 py-3">Tipe</th>
                            <th class="min-w-[12rem] px-4 py-3">Pesan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($geofenceAlerts as $log)
                            <tr class="hover:bg-slate-50/80">
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-600">{{ optional($log->event_ts)->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-sm">{{ $log->tractor_id }}</td>
                                <td class="px-4 py-3">
                                    <x-strategic.badge :variant="strtoupper($log->event_type) === 'ENTER' ? 'enter' : 'exit'">{{ strtoupper($log->event_type) }}</x-strategic.badge>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $log->message }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">Belum ada alert geofence.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-strategic.table-shell>
        </section>

        {{-- 7) Kinerja & Anomali --}}
        <section class="mb-10 space-y-8">
            <h2 class="als-section-title text-lg font-bold">Analisis Kinerja &amp; Anomali</h2>

            <x-strategic.table-shell title="Rapor Kinerja Kelompok Tani (Gamification)" subtitle="Periode: {{ $periodLabel }}" icon="chart" fragment="strategic-gp-section" per-page-key="gp_per_page" :paginator="$groupScores">
                <x-slot name="headerActions">
                    <button type="button" class="strategic-crud-add inline-flex h-8 shrink-0 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100" data-strategic-kind="gp">+ Tambah</button>
                </x-slot>
                <x-slot name="toolbar">
                    <x-strategic.filter-toolbar :preserver-except="['gp_q', 'gp_grade', 'gp_period', 'gp_page']" :reset-keys="['gp_q', 'gp_grade', 'gp_period', 'gp_page', 'gp_per_page']" reset-fragment="strategic-gp-section">
                        <input type="search" name="gp_q" value="{{ request('gp_q') }}" placeholder="Kelompok / catatan…" class="min-h-[38px] min-w-[7rem] max-w-full flex-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                        <x-ui.custom-select name="gp_grade" :options="array_merge(['' => 'Grade'], array_combine(['A','B','C','D'], ['A','B','C','D']))" :selected="$gpGradeSel" class="w-[4.75rem] flex-shrink-0" />
                        <input type="text" name="gp_period" value="{{ request('gp_period', $periodLabel) }}" placeholder="2026-Q2" class="min-h-[38px] w-[6.75rem] flex-shrink-0 rounded-lg border border-slate-200 bg-white px-2 py-1.5 font-mono text-sm text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                    </x-strategic.filter-toolbar>
                </x-slot>
                <table class="min-w-[800px] w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-3 py-3">#</th>
                            <th class="px-3 py-3">Kelompok Tani</th>
                            <th class="px-3 py-3">Desa</th>
                            <th class="px-3 py-3">Periode</th>
                            <th class="px-3 py-3">Keaktifan</th>
                            <th class="px-3 py-3">Perawatan</th>
                            <th class="px-3 py-3">Total</th>
                            <th class="px-3 py-3">Grade</th>
                            <th class="min-w-[8rem] px-3 py-3">Catatan</th>
                            <th class="whitespace-nowrap px-3 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($groupScores as $g)
                            @php
                                $gpPayload = [
                                    'id' => $g->id,
                                    'group_id' => $g->group_id,
                                    'period' => $g->period,
                                    'activity_score' => (float) $g->activity_score,
                                    'maintenance_score' => (float) $g->maintenance_score,
                                    'total_score' => (float) $g->total_score,
                                    'grade' => $g->grade,
                                    'notes' => $g->notes,
                                ];
                            @endphp
                            <tr class="hover:bg-slate-50/80" data-strategic-kind="gp" data-strategic-item="{{ e(json_encode($gpPayload)) }}">
                                <td class="px-3 py-3 tabular-nums text-slate-600">{{ ($groupScores->currentPage() - 1) * $groupScores->perPage() + $loop->iteration }}</td>
                                <td class="px-3 py-3 font-medium">{{ optional($g->group)->name ?? '—' }}</td>
                                <td class="px-3 py-3">{{ optional($g->group)->village ?? '—' }}</td>
                                <td class="px-3 py-3 font-mono text-xs">{{ $g->period }}</td>
                                <td class="px-3 py-3 tabular-nums">{{ number_format((float) $g->activity_score, 2) }}</td>
                                <td class="px-3 py-3 tabular-nums">{{ number_format((float) $g->maintenance_score, 2) }}</td>
                                <td class="px-3 py-3 tabular-nums font-semibold">{{ number_format((float) $g->total_score, 2) }}</td>
                                <td class="px-3 py-3"><x-strategic.badge :variant="$gradeVariant($g->grade)">{{ $g->grade }}</x-strategic.badge></td>
                                <td class="max-w-xs truncate px-3 py-3 text-slate-600" title="{{ $g->notes }}">{{ $g->notes ?: '—' }}</td>
                                <td class="px-3 py-3 text-right" onclick="event.stopPropagation();">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                        <button type="button" class="strategic-crud-edit rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-900 hover:bg-amber-100">Edit</button>
                                        <form method="post" action="{{ route('strategic.group-scores.delete', $g).$strategicSuffix }}" class="inline als-strategic-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="als-strategic-delete-btn rounded-md border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-800 hover:bg-rose-100">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-4 py-10 text-center text-slate-500">Belum ada data rapor untuk periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-strategic.table-shell>

            <x-strategic.table-shell title="Anomaly Detection System" subtitle="Insiden &amp; status penanganan" icon="alert" fragment="strategic-an-section" per-page-key="an_per_page" :paginator="$anomalies">
                <x-slot name="headerActions">
                    <button type="button" class="strategic-crud-add inline-flex h-8 shrink-0 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100" data-strategic-kind="an">+ Tambah</button>
                </x-slot>
                <x-slot name="toolbar">
                    <x-strategic.filter-toolbar :preserver-except="['an_q', 'an_status', 'an_severity', 'an_page']" :reset-keys="['an_q', 'an_status', 'an_severity', 'an_page', 'an_per_page']" reset-fragment="strategic-an-section">
                        <input type="search" name="an_q" value="{{ request('an_q') }}" placeholder="Cari…" class="min-h-[38px] min-w-[6.5rem] max-w-full flex-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                        <x-ui.custom-select name="an_status" :options="['' => 'Status', 'OPEN' => 'OPEN', 'RESOLVED' => 'RESOLVED']" :selected="$anStatusSel" class="w-[8rem] flex-shrink-0" />
                        <x-ui.custom-select name="an_severity" :options="['' => 'Severity', 'HIGH' => 'HIGH', 'MEDIUM' => 'MEDIUM', 'LOW' => 'LOW']" :selected="$anSevSel" class="w-[8rem] flex-shrink-0" />
                    </x-strategic.filter-toolbar>
                </x-slot>
                <table class="min-w-[900px] w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-3">Waktu</th>
                            <th class="px-4 py-3">Alat</th>
                            <th class="px-4 py-3">Tipe</th>
                            <th class="px-4 py-3">Severity</th>
                            <th class="min-w-[10rem] px-4 py-3">Deskripsi</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($anomalies as $a)
                            @php
                                $tz = config('app.timezone');
                                $anPayload = [
                                    'id' => $a->id,
                                    'detected_at' => $a->detected_at ? $a->detected_at->timezone($tz)->format('Y-m-d\TH:i') : '',
                                    'tractor_id' => $a->tractor_id,
                                    'anomaly_type' => $a->anomaly_type,
                                    'severity' => strtoupper((string) $a->severity),
                                    'description' => $a->description,
                                    'status' => strtoupper((string) $a->status),
                                    'resolved_at' => $a->resolved_at ? $a->resolved_at->timezone($tz)->format('Y-m-d\TH:i') : '',
                                    'resolved_note' => $a->resolved_note,
                                ];
                            @endphp
                            <tr class="hover:bg-slate-50/80" data-strategic-kind="an" data-strategic-item="{{ e(json_encode($anPayload)) }}">
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ optional($a->detected_at)->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 font-mono text-sm">{{ $a->tractor_id }}</td>
                                <td class="px-4 py-3">{{ $a->anomaly_type }}</td>
                                <td class="px-4 py-3"><x-strategic.badge :variant="$sevVariant($a->severity)">{{ strtoupper($a->severity) }}</x-strategic.badge></td>
                                <td class="max-w-md px-4 py-3 text-slate-700">{{ \Illuminate\Support\Str::limit($a->description, 120) }}</td>
                                <td class="px-4 py-3">
                                    <x-strategic.badge :variant="strtoupper((string) $a->status) === 'OPEN' ? 'st-open' : 'st-resolved'">{{ strtoupper($a->status) }}</x-strategic.badge>
                                </td>
                                <td class="px-4 py-3 text-right" onclick="event.stopPropagation();">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                        <button type="button" class="strategic-crud-edit rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-900 hover:bg-amber-100">Edit</button>
                                        <form method="post" action="{{ route('strategic.anomalies.delete', $a).$strategicSuffix }}" class="inline als-strategic-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="als-strategic-delete-btn rounded-md border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-800 hover:bg-rose-100">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Belum ada data anomali.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-strategic.table-shell>
        </section>

        {{-- 8) Utilization --}}
        <section class="mb-10">
            <h2 class="als-section-title mb-4 text-lg font-bold">Utilization Rate</h2>
            <x-strategic.table-shell title="Utilization Rate" subtitle="Estimasi jam &amp; tingkat utilisasi" icon="table" fragment="strategic-ut-section" per-page-key="ut_per_page" :paginator="$utilizationRows">
                <x-slot name="headerActions">
                    <button type="button" class="strategic-crud-add inline-flex h-8 shrink-0 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100" data-strategic-kind="ut">+ Tambah</button>
                </x-slot>
                <x-slot name="toolbar">
                    <x-strategic.filter-toolbar :preserver-except="['ut_q', 'ut_status', 'ut_page']" :reset-keys="['ut_q', 'ut_status', 'ut_page', 'ut_per_page']" reset-fragment="strategic-ut-section">
                        <input type="search" name="ut_q" value="{{ request('ut_q') }}" placeholder="ID alat…" class="min-h-[38px] min-w-[6.5rem] max-w-full flex-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                        @php
                            $utOpts = ['' => 'Status', 'TINGGI' => 'TINGGI', 'SEDANG' => 'SEDANG', 'RENDAH' => 'RENDAH', 'HIGH' => 'HIGH', 'MEDIUM' => 'MEDIUM', 'LOW' => 'LOW'];
                        @endphp
                        <x-ui.custom-select name="ut_status" :options="$utOpts" :selected="$utStatusSel" class="w-[8.5rem] flex-shrink-0" />
                    </x-strategic.filter-toolbar>
                </x-slot>
                <table class="min-w-[720px] w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-3">Alat</th>
                            <th class="px-4 py-3">Hari Aktif</th>
                            <th class="px-4 py-3">Est. Jam</th>
                            <th class="min-w-[12rem] px-4 py-3">Utilisasi</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($utilizationRows as $u)
                            @php
                                $pct = min(100, max(0, (float) $u->utilization_pct));
                                $utPayload = [
                                    'id' => $u->id,
                                    'tractor_id' => $u->tractor_id,
                                    'date' => $u->date ? $u->date->format('Y-m-d') : '',
                                    'active_days_rolling' => (int) $u->active_days_rolling,
                                    'estimated_hours' => (float) $u->estimated_hours,
                                    'utilization_pct' => (float) $u->utilization_pct,
                                    'utilization_status' => $u->utilization_status,
                                ];
                            @endphp
                            <tr class="hover:bg-slate-50/80" data-strategic-kind="ut" data-strategic-item="{{ e(json_encode($utPayload)) }}">
                                <td class="px-4 py-3 font-mono text-sm">{{ $u->tractor_id }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ (int) $u->active_days_rolling }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ number_format((float) $u->estimated_hours, 1) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 min-w-[6rem] flex-1 overflow-hidden rounded-full bg-slate-200">
                                            <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-500" style="width: {{ $pct }}%"></div>
                                        </div>
                                        <span class="text-sm font-semibold tabular-nums">{{ number_format($pct, 1) }}%</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <x-strategic.badge :variant="$utilVariant($u->utilization_status)">{{ $utilLabel($u->utilization_status) }}</x-strategic.badge>
                                </td>
                                <td class="px-4 py-3 text-right" onclick="event.stopPropagation();">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                        <button type="button" class="strategic-crud-edit rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-900 hover:bg-amber-100">Edit</button>
                                        <form method="post" action="{{ route('strategic.utilization-daily.delete', $u).$strategicSuffix }}" class="inline als-strategic-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="als-strategic-delete-btn rounded-md border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-800 hover:bg-rose-100">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada data utilisasi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-strategic.table-shell>
        </section>

        {{-- 9) Maintenance --}}
        <section class="mb-10 space-y-8">
            <h2 class="als-section-title text-lg font-bold">Maintenance &amp; Health Record</h2>

            <x-strategic.table-shell title="Predictive Maintenance Alert" subtitle="Interval &amp; jam kerja mesin" icon="alert" fragment="strategic-mp-section" per-page-key="mp_per_page" :paginator="$maintenancePlans">
                <x-slot name="headerActions">
                    <button type="button" class="strategic-crud-add inline-flex h-8 shrink-0 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100" data-strategic-kind="mp">+ Tambah</button>
                </x-slot>
                <x-slot name="toolbar">
                    <x-strategic.filter-toolbar :preserver-except="['mp_q', 'mp_status', 'mp_page']" :reset-keys="['mp_q', 'mp_status', 'mp_page', 'mp_per_page']" reset-fragment="strategic-mp-section">
                        <input type="search" name="mp_q" value="{{ request('mp_q') }}" placeholder="Alat / task…" class="min-h-[38px] min-w-[7rem] max-w-full flex-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                        <x-ui.custom-select name="mp_status" :options="['' => 'Status', 'DONE' => 'DONE', 'PENDING' => 'PENDING', 'OVERDUE' => 'OVERDUE']" :selected="$mpStatusSel" class="w-[7.25rem] flex-shrink-0" />
                    </x-strategic.filter-toolbar>
                </x-slot>
                <table class="min-w-[800px] w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-3">Alat</th>
                            <th class="px-4 py-3">Tipe</th>
                            <th class="min-w-[14rem] px-4 py-3">Progress Jam Kerja</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($maintenancePlans as $m)
                            @php
                                $due = (float) ($m->due_hours ?? 0);
                                $cur = (float) ($m->current_hours ?? 0);
                                $prog = $due > 0 ? min(100, ($cur / $due) * 100) : 0;
                                $mpPayload = [
                                    'id' => $m->id,
                                    'tractor_id' => $m->tractor_id,
                                    'task_type' => $m->task_type,
                                    'interval_hours' => $m->interval_hours,
                                    'current_hours' => $m->current_hours,
                                    'due_hours' => $m->due_hours,
                                    'status' => strtoupper((string) $m->status),
                                ];
                            @endphp
                            <tr class="hover:bg-slate-50/80" data-strategic-kind="mp" data-strategic-item="{{ e(json_encode($mpPayload)) }}">
                                <td class="px-4 py-3 font-mono text-sm">{{ $m->tractor_id }}</td>
                                <td class="px-4 py-3">{{ $m->task_type }}</td>
                                <td class="px-4 py-3">
                                    <div class="mb-1 flex justify-between text-xs text-slate-600">
                                        <span>{{ number_format($cur, 0) }} jam</span>
                                        <span>{{ number_format($due, 0) }} jam</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-slate-200">
                                        <div class="h-full rounded-full bg-gradient-to-r from-amber-400 to-orange-500" style="width: {{ $prog }}%"></div>
                                    </div>
                                </td>
                                <td class="px-4 py-3"><x-strategic.badge :variant="$mpVariant($m->status)">{{ strtoupper($m->status) }}</x-strategic.badge></td>
                                <td class="px-4 py-3 text-right" onclick="event.stopPropagation();">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                        <button type="button" class="strategic-crud-edit rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-900 hover:bg-amber-100">Edit</button>
                                        <form method="post" action="{{ route('strategic.maintenance-plans.delete', $m).$strategicSuffix }}" class="inline als-strategic-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="als-strategic-delete-btn rounded-md border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-800 hover:bg-rose-100">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">Belum ada rencana maintenance.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-strategic.table-shell>

            <x-strategic.table-shell title="Digital Health Record (Kartu Riwayat)" subtitle="Biaya &amp; teknisi" icon="table" fragment="strategic-mr-section" per-page-key="mr_per_page" :paginator="$maintenanceRecords">
                <x-slot name="headerActions">
                    <button type="button" class="strategic-crud-add inline-flex h-8 shrink-0 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100" data-strategic-kind="mr">+ Tambah</button>
                </x-slot>
                <x-slot name="toolbar">
                    <x-strategic.filter-toolbar :preserver-except="['mr_q', 'mr_type', 'mr_page']" :reset-keys="['mr_q', 'mr_type', 'mr_page', 'mr_per_page']" reset-fragment="strategic-mr-section">
                        <input type="search" name="mr_q" value="{{ request('mr_q') }}" placeholder="Cari…" class="min-h-[38px] min-w-[6.5rem] max-w-full flex-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                        @php
                            $mrTypeOpts = ['' => 'Tipe', 'penggantian' => 'penggantian', 'perbaikan' => 'perbaikan', 'kerusakan' => 'kerusakan', 'replacement' => 'replacement', 'repair' => 'repair', 'damage' => 'damage'];
                        @endphp
                        <x-ui.custom-select name="mr_type" :options="$mrTypeOpts" :selected="$mrTypeSel" class="min-w-[8.5rem] max-w-[11rem] flex-shrink-0" />
                    </x-strategic.filter-toolbar>
                </x-slot>
                <table class="min-w-[900px] w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Alat</th>
                            <th class="px-4 py-3">Tipe</th>
                            <th class="min-w-[10rem] px-4 py-3">Deskripsi</th>
                            <th class="px-4 py-3">Biaya</th>
                            <th class="px-4 py-3">Teknisi</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($maintenanceRecords as $r)
                            @php
                                $mrPayload = [
                                    'id' => $r->id,
                                    'tractor_id' => $r->tractor_id,
                                    'record_date' => $r->record_date ? $r->record_date->format('Y-m-d') : '',
                                    'record_type' => $r->record_type,
                                    'description' => $r->description,
                                    'cost' => (float) $r->cost,
                                    'technician' => $r->technician,
                                    'workshop' => $r->workshop,
                                ];
                            @endphp
                            <tr class="hover:bg-slate-50/80" data-strategic-kind="mr" data-strategic-item="{{ e(json_encode($mrPayload)) }}">
                                <td class="whitespace-nowrap px-4 py-3">{{ optional($r->record_date)->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 font-mono text-sm">{{ $r->tractor_id }}</td>
                                <td class="px-4 py-3"><x-strategic.badge variant="health">{{ $healthTypeLabel($r->record_type) }}</x-strategic.badge></td>
                                <td class="max-w-md px-4 py-3 text-slate-700">{{ \Illuminate\Support\Str::limit($r->description, 100) }}</td>
                                <td class="px-4 py-3 tabular-nums font-medium">Rp {{ number_format((float) $r->cost, 0, ',', '.') }}</td>
                                <td class="px-4 py-3">{{ $r->technician ?: '—' }}</td>
                                <td class="px-4 py-3 text-right" onclick="event.stopPropagation();">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                        <button type="button" class="strategic-crud-edit rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-900 hover:bg-amber-100">Edit</button>
                                        <form method="post" action="{{ route('strategic.maintenance-records.delete', $r).$strategicSuffix }}" class="inline als-strategic-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="als-strategic-delete-btn rounded-md border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-800 hover:bg-rose-100">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Belum ada riwayat kesehatan alat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-strategic.table-shell>
        </section>
    </div>

    {{-- 10) Bottom navigation --}}
    <nav class="fixed bottom-0 left-0 right-0 z-50 border-t border-slate-200/80 bg-white/95 backdrop-blur-md md:hidden" aria-label="Menu utama">
        <div class="mx-auto flex max-w-lg items-stretch justify-around py-2">
            <a href="{{ route('dashboard') }}" class="flex flex-1 flex-col items-center gap-0.5 px-2 py-2 text-xs font-medium text-slate-500 hover:text-emerald-700">
                <span class="text-xl" aria-hidden="true">🏠</span>
                Home
            </a>
            <a href="{{ route('strategic') }}" class="flex flex-1 flex-col items-center gap-0.5 px-2 py-2 text-xs font-bold text-emerald-700" aria-current="page">
                <span class="text-xl" aria-hidden="true">📊</span>
                Strategic
            </a>
            <a href="{{ route('profile') }}" class="flex flex-1 flex-col items-center gap-0.5 px-2 py-2 text-xs font-medium text-slate-500 hover:text-emerald-700">
                <span class="text-xl" aria-hidden="true">👤</span>
                Profile
            </a>
        </div>
    </nav>

    {{-- Modal tambah: tengah vertikal; polygon digambar di peta di dalam modal (bukan peta utama) --}}
    <div id="als-zone-modal-add" class="als-zone-modal-layer" role="dialog" aria-modal="true" aria-labelledby="als-zone-modal-add-title" aria-hidden="true">
        <button type="button" id="als-zone-modal-add-backdrop" class="absolute inset-0 z-0 bg-slate-900/50" aria-label="Tutup"></button>
        <div class="als-zone-modal-add-panel relative z-10 flex max-h-[min(92vh,44rem)] w-full flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200">
            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-100 bg-emerald-50/40 px-5 py-4">
                <h4 id="als-zone-modal-add-title" class="text-base font-semibold text-slate-900">Tambah zona baru</h4>
                <button type="button" id="als-zone-modal-add-close" class="rounded-lg p-1.5 text-slate-500 transition hover:bg-white hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                <p class="text-xs text-slate-600"><strong>Tambah titik di peta di bawah</strong> (bukan peta utama). Minimal 3 titik. Urung atau Hapus semua titik untuk mengatur ulang.</p>
                <form method="post" class="mt-3 space-y-3" action="{{ $zoneStoreUrl }}" id="als-zone-add-form">
                    @csrf
                    <input type="hidden" name="is_active" value="1">
                    <div>
                        <label for="als-zone-name" class="text-xs font-medium text-slate-700">Nama zona</label>
                        <input type="text" name="name" id="als-zone-name" value="{{ old('name') }}" required maxlength="120" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" placeholder="Contoh: Area kerja Blok A">
                    </div>
                    <textarea name="polygon_json" id="als-zone-polygon-json" class="sr-only" tabindex="-1"></textarea>
                    @if ($tractors->isNotEmpty() && ($zoneTractorFeatureReady ?? false))
                        <div class="text-xs">
                            <p class="font-medium text-slate-600">Kaitkan alat (opsional)</p>
                            <div class="als-zone-tractor-grid mt-2 max-h-40 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50/80 p-2">
                                @foreach ($tractors as $tr)
                                    <label class="flex min-w-0 cursor-pointer items-center gap-2 rounded px-1 py-0.5 hover:bg-white">
                                        <input type="checkbox" name="tractor_ids[]" value="{{ $tr->id }}" class="shrink-0 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                        <span class="min-w-0 truncate font-mono text-[11px] text-slate-800">{{ $tr->id }}</span>
                                        @if ($tr->name)
                                            <span class="min-w-0 truncate text-slate-500">{{ $tr->name }}</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <div class="space-y-2 pt-1">
                        <p id="als-zone-draw-hint" class="hidden text-xs leading-snug text-slate-700"></p>
                        <div id="als-zone-modal-draw-toolbar" class="flex flex-wrap items-center gap-1.5">
                            <button type="button" id="als-zone-draw-undo" class="hidden shrink-0 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50">Urung titik</button>
                            <button type="button" id="als-zone-draw-clear" class="hidden shrink-0 rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-medium text-rose-800 shadow-sm hover:bg-rose-50">Hapus semua titik</button>
                        </div>
                        <div id="als-zone-modal-map" class="rounded-lg border border-slate-200 bg-slate-100"></div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">Simpan</button>
                        <button type="button" id="als-zone-draw-cancel" class="hidden rounded-lg border border-rose-200 bg-white px-4 py-2 text-xs font-semibold text-rose-800 shadow-sm hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500/30">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal zona: edit — layout sama seperti tambah: polygon di peta dalam modal (bukan peta utama OSM) --}}
    <div id="als-zone-modal-edit" class="als-zone-modal-layer" role="dialog" aria-modal="true" aria-labelledby="als-zone-modal-edit-title" aria-hidden="true">
        <button type="button" id="als-zone-modal-edit-backdrop" class="absolute inset-0 z-0 bg-slate-900/50" aria-label="Tutup"></button>
        <div class="als-zone-modal-add-panel relative z-10 flex max-h-[min(92vh,44rem)] w-full flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200">
            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/70 px-5 py-3.5">
                <h4 id="als-zone-modal-edit-title" class="text-base font-semibold text-slate-900">Edit zona</h4>
                <button type="button" id="als-zone-edit-close" class="shrink-0 rounded-lg p-1.5 text-slate-500 transition hover:bg-white hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                <form id="als-zone-edit-form" method="post" class="space-y-3" action="#">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="polygon_json" id="als-zone-edit-polygon" value="">
                    <div>
                        <label for="als-zone-edit-name" class="text-xs font-medium text-slate-700">Nama zona</label>
                        <input type="text" name="name" id="als-zone-edit-name" required maxlength="120" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" placeholder="Nama area kerja">
                    </div>
                    <label class="flex cursor-pointer items-center gap-2 text-xs text-slate-700">
                        <input type="checkbox" name="is_active" id="als-zone-edit-active" value="1" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        Zona aktif (dipakai evaluasi geofence)
                    </label>
                    @if ($tractors->isNotEmpty() && ($zoneTractorFeatureReady ?? false))
                        <div class="text-xs">
                            <p class="font-medium text-slate-600">Kaitkan alat (opsional)</p>
                            <div class="als-zone-tractor-grid mt-2 max-h-40 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50/80 p-2">
                                @foreach ($tractors as $tr)
                                    <label class="flex min-w-0 cursor-pointer items-center gap-2 rounded px-1 py-0.5 hover:bg-white">
                                        <input type="checkbox" name="tractor_ids[]" value="{{ $tr->id }}" class="als-edit-tractor shrink-0 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                        <span class="min-w-0 truncate font-mono text-[11px] text-slate-800">{{ $tr->id }}</span>
                                        @if ($tr->name)<span class="min-w-0 truncate text-slate-500">{{ $tr->name }}</span>@endif
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <div class="space-y-2 pt-1">
                        <p id="als-zone-edit-draw-hint" class="text-xs leading-snug text-slate-700">Klik peta di bawah untuk menambah atau mengubah titik polygon (minimal 3). Urung / Hapus semua untuk mengatur ulang.</p>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <button type="button" id="als-zone-edit-draw-undo" class="shrink-0 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50">Urung titik</button>
                            <button type="button" id="als-zone-edit-draw-clear" class="shrink-0 rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-medium text-rose-800 shadow-sm hover:bg-rose-50">Hapus semua titik</button>
                        </div>
                        <div id="als-zone-edit-map" class="rounded-lg border border-slate-200 bg-slate-100"></div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <button type="button" id="als-zone-edit-submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">Simpan</button>
                        <button type="button" id="als-zone-edit-form-cancel" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <dialog id="als-zone-native-confirm" class="w-[min(92vw,22rem)] max-w-lg rounded-xl border border-slate-200 bg-white p-0 shadow-2xl ring-1 ring-slate-200">
        <div class="px-4 py-3 border-b border-slate-100">
            <p class="text-sm font-semibold text-slate-900">Konfirmasi</p>
        </div>
        <div class="px-4 py-3">
            <p id="als-zone-native-confirm-msg" class="text-sm leading-relaxed text-slate-700 whitespace-pre-wrap"></p>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-3">
            <button type="button" id="als-zone-native-confirm-no" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
            <button type="button" id="als-zone-native-confirm-yes" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700">Ya, lanjutkan</button>
        </div>
    </dialog>

    @include('strategic.partials.crud-dialogs')

    @php
        $zonesJs = $geofenceZonesAll->values()->map(function ($z) use ($zoneTractorFeatureReady) {
            $tractorIds = ($zoneTractorFeatureReady ?? false)
                ? $z->tractors->pluck('id')->map(fn ($id) => (string) $id)->values()->all()
                : [];

            return [
                'id' => $z->id,
                'name' => $z->name,
                'zone_type' => $z->zone_type,
                'is_active' => (bool) $z->is_active,
                'polygon_json' => $z->polygon_json,
                'tractor_ids' => $tractorIds,
            ];
        })->all();
        $positionsJs = $latestPositions->values()->map(fn ($p) => [
            'tractor_id' => $p->tractor_id,
            'lat' => (float) $p->lat,
            'lng' => (float) $p->lng,
            'status' => $p->status,
            'engine_on' => (bool) $p->engine_on,
        ])->all();
    @endphp
    <script src="{{ asset('js/als-custom-select.js') }}"></script>
    <script>
        (function () {
            const zones = @json($zonesJs);
            const positions = @json($positionsJs);
            const routes = @json($routeHistoryByTractor);
            const zoneUrlBase = @json(rtrim($zoneApiBase, '/'));
            const zoneFormQuerySuffix = @json($zoneFormQuerySuffix);

            (function relocateZoneModals() {
                ['als-zone-modal-add', 'als-zone-modal-edit', 'als-zone-native-confirm', 'strategic-dialog-gp', 'strategic-dialog-an', 'strategic-dialog-ut', 'strategic-dialog-mp', 'strategic-dialog-mr'].forEach(function (mid) {
                    var node = document.getElementById(mid);
                    if (node && node.parentNode !== document.body) {
                        document.body.appendChild(node);
                    }
                });
            })();

            /** Baca GPS terkini (tanpa cache); dipakai semua peta modal + peta armada. */
            function alsZoneGeolocationReadOptions() {
                return { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 };
            }

            /** Konfirmasi in-page: window.confirm sering diblokir di iframe / preview IDE. */
            function alsZoneConfirmPromise(message) {
                return new Promise(function (resolve) {
                    var dlg = document.getElementById('als-zone-native-confirm');
                    var msgEl = document.getElementById('als-zone-native-confirm-msg');
                    var yesBtn = document.getElementById('als-zone-native-confirm-yes');
                    var noBtn = document.getElementById('als-zone-native-confirm-no');
                    if (!dlg || !msgEl || !yesBtn || !noBtn) {
                        resolve(window.confirm(message));
                        return;
                    }
                    msgEl.textContent = message;
                    function done(ok) {
                        yesBtn.removeEventListener('click', onYes);
                        noBtn.removeEventListener('click', onNo);
                        dlg.removeEventListener('cancel', onCancel);
                        try {
                            dlg.close();
                        } catch (e2) { /* */ }
                        resolve(ok);
                    }
                    function onYes() { done(true); }
                    function onNo() { done(false); }
                    function onCancel(ev) {
                        ev.preventDefault();
                        done(false);
                    }
                    yesBtn.addEventListener('click', onYes);
                    noBtn.addEventListener('click', onNo);
                    dlg.addEventListener('cancel', onCancel);
                    if (typeof dlg.showModal === 'function') {
                        dlg.showModal();
                    } else {
                        resolve(window.confirm(message));
                    }
                });
            }

            const map = L.map('strategic-fleet-map', { scrollWheelZoom: true, zoomControl: false });
            map.setView([0, 0], 2);
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
            (function () {
                var vp = map.createPane('alsVertexPane');
                vp.style.zIndex = '750';
            })();
            const statusColor = (s) => ({ active: '#16a34a', idle: '#eab308', maintenance: '#f97316', offline: '#64748b' }[(s || '').toLowerCase()] || '#0ea5e9');
            function extractRing(raw) {
                if (raw == null || raw === '') return [];
                var p = raw;
                if (typeof p === 'string') {
                    try { p = JSON.parse(p); } catch (e) { return []; }
                }
                if (!p) return [];
                if (p.type === 'Polygon' && Array.isArray(p.coordinates) && p.coordinates[0]) {
                    return p.coordinates[0];
                }
                if (p.type === 'MultiPolygon' && Array.isArray(p.coordinates) && p.coordinates[0] && p.coordinates[0][0]) {
                    return p.coordinates[0][0];
                }
                if (Array.isArray(p) && p.length && (Array.isArray(p[0]) || (p[0] && (typeof p[0].lat === 'number' || typeof p[0].lng === 'number')))) {
                    return p;
                }
                return [];
            }
            function toLatLngPair(obj) {
                if (!obj) return null;
                if (typeof obj.lat === 'number' && typeof obj.lng === 'number') {
                    return [obj.lat, obj.lng];
                }
                if (typeof obj.latitude === 'number' && typeof obj.longitude === 'number') {
                    return [obj.latitude, obj.longitude];
                }
                if (Array.isArray(obj) && obj.length >= 2) {
                    var a = Number(obj[0]), b = Number(obj[1]);
                    if (!Number.isFinite(a) || !Number.isFinite(b)) return null;
                    if (a >= 90 && a <= 150 && b >= -15 && b <= 14) return [b, a];
                    if (b >= 90 && b <= 150 && a >= -15 && a <= 14) return [a, b];
                    if (Math.abs(a) <= 90 && Math.abs(b) <= 180) return [a, b];
                    if (Math.abs(a) > 90) return [b, a];
                }
                return null;
            }
            const bounds = L.latLngBounds([]);
            const zonePolygonLayer = L.layerGroup().addTo(map);
            const vertexLayer = L.layerGroup().addTo(map);
            const zoneRegistry = {};
            var selectedZoneId = null;
            var modalMap = null;
            var drawLayer = null;
            var editMap = null;
            var editDrawLayer = null;
            var editDraftPoints = [];
            var onEditMapClick = null;
            /** Salinan latlng polygon di peta utama saat modal edit dibuka (dipulihkan jika Batal tanpa simpan). */
            var editMainPolygonBackup = null;
            var editMainPolygonBackupZoneId = null;

            function ensureModalMap() {
                if (modalMap) return;
                var el = document.getElementById('als-zone-modal-map');
                if (!el) return;
                modalMap = L.map('als-zone-modal-map', { scrollWheelZoom: true, zoomControl: false });
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
                    sat.addTo(modalMap);
                    L.control.layers({ Satelit: sat, Peta: osm }, null, { position: 'bottomright' }).addTo(modalMap);
                    L.control.zoom({ position: 'bottomleft' }).addTo(modalMap);
                })();
                drawLayer = L.layerGroup().addTo(modalMap);
                /** Pusat awal: lokasi perangkat (geolocation). Bukan koordinat tetap di kode. Fallback = data armada di peta utama. */
                function alsModalMapFallbackView() {
                    if (!modalMap) return;
                    if (bounds.isValid()) {
                        modalMap.fitBounds(bounds.pad(0.08), { maxZoom: 16 });
                    } else {
                        modalMap.setView(map.getCenter(), Math.min(map.getZoom(), 16));
                    }
                }
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        function (pos) {
                            if (!modalMap) return;
                            var lat = pos.coords.latitude;
                            var lng = pos.coords.longitude;
                            if (Number.isFinite(lat) && Number.isFinite(lng)) {
                                var acc = pos.coords.accuracy;
                                var z = 16;
                                if (Number.isFinite(acc) && acc > 80) z = 14;
                                else if (Number.isFinite(acc) && acc > 40) z = 15;
                                modalMap.setView([lat, lng], z);
                            } else {
                                alsModalMapFallbackView();
                            }
                            try {
                                modalMap.invalidateSize();
                            } catch (g) { /* */ }
                        },
                        function () {
                            alsModalMapFallbackView();
                            try {
                                if (modalMap) modalMap.invalidateSize();
                            } catch (g2) { /* */ }
                        },
                        alsZoneGeolocationReadOptions()
                    );
                } else {
                    alsModalMapFallbackView();
                }
            }

            function ensureEditModalMap() {
                if (editMap) return;
                var el = document.getElementById('als-zone-edit-map');
                if (!el) return;
                editMap = L.map('als-zone-edit-map', { scrollWheelZoom: true, zoomControl: false });
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
                    sat.addTo(editMap);
                    L.control.layers({ Satelit: sat, Peta: osm }, null, { position: 'bottomright' }).addTo(editMap);
                    L.control.zoom({ position: 'bottomleft' }).addTo(editMap);
                })();
                editDrawLayer = L.layerGroup().addTo(editMap);
                function alsEditModalFallbackView() {
                    if (!editMap) return;
                    if (bounds.isValid()) {
                        editMap.fitBounds(bounds.pad(0.08), { maxZoom: 16 });
                    } else {
                        editMap.setView(map.getCenter(), Math.min(map.getZoom(), 16));
                    }
                }
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        function (pos) {
                            if (!editMap) return;
                            var lat = pos.coords.latitude;
                            var lng = pos.coords.longitude;
                            if (Number.isFinite(lat) && Number.isFinite(lng)) {
                                var acc = pos.coords.accuracy;
                                var z = 16;
                                if (Number.isFinite(acc) && acc > 80) z = 14;
                                else if (Number.isFinite(acc) && acc > 40) z = 15;
                                editMap.setView([lat, lng], z);
                            } else {
                                alsEditModalFallbackView();
                            }
                            try {
                                editMap.invalidateSize();
                            } catch (g) { /* */ }
                        },
                        function () {
                            alsEditModalFallbackView();
                            try {
                                if (editMap) editMap.invalidateSize();
                            } catch (g2) { /* */ }
                        },
                        alsZoneGeolocationReadOptions()
                    );
                } else {
                    alsEditModalFallbackView();
                }
            }

            /** Pusatkan peta modal ke GPS pengguna (setelah modal dibuka / gambar baru). */
            function panLeafletMapToLatestUser(targetMap) {
                if (!targetMap || !navigator.geolocation) return;
                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        var lat = pos.coords.latitude;
                        var lng = pos.coords.longitude;
                        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
                        var acc = pos.coords.accuracy;
                        var z = 16;
                        if (Number.isFinite(acc) && acc > 80) z = 14;
                        else if (Number.isFinite(acc) && acc > 40) z = 15;
                        targetMap.setView([lat, lng], z);
                        try {
                            targetMap.invalidateSize();
                        } catch (e) { /* */ }
                    },
                    function () { /* biarkan posisi saat ini */ },
                    alsZoneGeolocationReadOptions()
                );
            }

            function stopEditMapListen() {
                if (editMap && onEditMapClick) {
                    editMap.off('click', onEditMapClick);
                    onEditMapClick = null;
                }
                if (editMap) editMap.doubleClickZoom.enable();
            }

            function redrawEditDraftPreview() {
                if (!editDrawLayer) return;
                editDrawLayer.clearLayers();
                editDraftPoints.forEach(function (ll) {
                    L.circleMarker(ll, { radius: 5, color: '#047857', fillColor: '#fff', weight: 2, fillOpacity: 1 }).addTo(editDrawLayer);
                });
                if (editDraftPoints.length >= 2) {
                    L.polyline(editDraftPoints, {
                        dashArray: '5 5',
                        color: '#059669',
                        weight: 2
                    }).addTo(editDrawLayer);
                }
                if (editDraftPoints.length >= 3) {
                    var ring = editDraftPoints.slice().concat([editDraftPoints[0]]);
                    L.polygon(ring, {
                        color: '#047857',
                        weight: 2,
                        fillColor: '#a7f3d0',
                        fillOpacity: 0.38
                    }).addTo(editDrawLayer);
                }
            }

            function syncEditPolygonFromDraft() {
                var el = document.getElementById('als-zone-edit-polygon');
                if (!el) return;
                if (editDraftPoints.length >= 3) {
                    el.value = latlngsToPolygonJson(editDraftPoints);
                } else {
                    el.value = '';
                }
            }

            function applyEditDraftToMainPolygon() {
                if (selectedZoneId == null) return;
                var r = zoneRegistry[selectedZoneId];
                syncEditPolygonFromDraft();
                if (!r) return;
                if (editDraftPoints.length < 3) {
                    applyZonePolygonStyles();
                    return;
                }
                var latlngs = editDraftPoints.map(function (ll) { return L.latLng(ll.lat, ll.lng); });
                r.latlngs = latlngs;
                r.polygon.setLatLngs(latlngs);
                applyZonePolygonStyles();
            }

            function startEditMapClickListen() {
                ensureEditModalMap();
                if (!editMap || !editDrawLayer) return;
                stopEditMapListen();
                editMap.doubleClickZoom.disable();
                onEditMapClick = function (e) {
                    editDraftPoints.push(e.latlng);
                    redrawEditDraftPreview();
                    applyEditDraftToMainPolygon();
                };
                editMap.on('click', onEditMapClick);
            }

            function loadEditDraftForSelectedZone() {
                editDraftPoints = [];
                if (selectedZoneId == null) return;
                var sid = selectedZoneId;
                var r = zoneRegistry[sid];
                editMainPolygonBackup = null;
                editMainPolygonBackupZoneId = null;
                if (r && r.latlngs && r.latlngs.length >= 3) {
                    editMainPolygonBackupZoneId = sid;
                    editMainPolygonBackup = r.latlngs.map(function (ll) { return L.latLng(ll.lat, ll.lng); });
                    r.latlngs.forEach(function (ll) {
                        editDraftPoints.push(L.latLng(ll.lat, ll.lng));
                    });
                } else {
                    var meta = findZoneMeta(sid);
                    if (meta && meta.polygon_json != null && meta.polygon_json !== '') {
                        var ring = extractRing(meta.polygon_json);
                        ring.forEach(function (pt) {
                            var pair = toLatLngPair(pt);
                            if (pair) editDraftPoints.push(L.latLng(pair[0], pair[1]));
                        });
                        if (editDraftPoints.length > 1 && editDraftPoints[0].equals(editDraftPoints[editDraftPoints.length - 1])) {
                            editDraftPoints.pop();
                        }
                    }
                }
                redrawEditDraftPreview();
                syncEditPolygonFromDraft();
                ensureEditModalMap();
                if (editMap) {
                    var b = L.latLngBounds([]);
                    editDraftPoints.forEach(function (ll) { b.extend(ll); });
                    if (b.isValid()) {
                        editMap.fitBounds(b.pad(0.08), { maxZoom: 17 });
                    } else if (bounds.isValid()) {
                        editMap.fitBounds(bounds.pad(0.08), { maxZoom: 16 });
                    } else {
                        editMap.setView(map.getCenter(), Math.min(map.getZoom(), 16));
                    }
                    try {
                        editMap.invalidateSize();
                    } catch (e) { /* */ }
                }
                startEditMapClickListen();
            }

            function teardownEditModalMap() {
                stopEditMapListen();
                editDraftPoints = [];
                if (editDrawLayer) editDrawLayer.clearLayers();
                editMainPolygonBackup = null;
                editMainPolygonBackupZoneId = null;
            }

            function restoreEditMainPolygonIfNeeded() {
                if (editMainPolygonBackupZoneId == null || !editMainPolygonBackup) return;
                var sid = editMainPolygonBackupZoneId;
                var r = zoneRegistry[sid];
                if (r) {
                    var latlngs = editMainPolygonBackup.map(function (ll) { return L.latLng(ll.lat, ll.lng); });
                    r.latlngs = latlngs;
                    r.polygon.setLatLngs(latlngs);
                }
                editMainPolygonBackup = null;
                editMainPolygonBackupZoneId = null;
            }

            function resetAddZoneForm() {
                var pj = document.getElementById('als-zone-polygon-json');
                if (pj) pj.value = '';
                var nm = document.getElementById('als-zone-name');
                if (nm) nm.value = '';
                document.querySelectorAll('#als-zone-add-form input[name="tractor_ids[]"]').forEach(function (cb) { cb.checked = false; });
            }

            function openAddModal() {
                var m = document.getElementById('als-zone-modal-add');
                if (m) {
                    m.classList.add('is-open');
                    m.setAttribute('aria-hidden', 'false');
                }
                setTimeout(function () {
                    ensureModalMap();
                    try {
                        if (modalMap) modalMap.invalidateSize();
                    } catch (e) { /* */ }
                    try { map.invalidateSize(); } catch (e2) { /* */ }
                    var pj = document.getElementById('als-zone-polygon-json');
                    var hasPolygon = pj && pj.value && String(pj.value).trim();
                    if (!hasPolygon) {
                        startDraw();
                        panLeafletMapToLatestUser(modalMap);
                    }
                    var n = document.getElementById('als-zone-name');
                    if (n) n.focus();
                    requestAnimationFrame(function () {
                        try {
                            if (modalMap) modalMap.invalidateSize();
                        } catch (e3) { /* */ }
                    });
                }, 200);
            }

            function closeAddModal(clearForm) {
                if (clearForm) exitDrawFully();
                var m = document.getElementById('als-zone-modal-add');
                if (m) {
                    m.classList.remove('is-open');
                    m.setAttribute('aria-hidden', 'true');
                }
                if (clearForm) resetAddZoneForm();
                setTimeout(function () {
                    try { map.invalidateSize(); } catch (e) { /* */ }
                    try {
                        if (modalMap) modalMap.invalidateSize();
                    } catch (e2) { /* */ }
                }, 50);
            }

            function openEditModal() {
                var m = document.getElementById('als-zone-modal-edit');
                if (m) {
                    m.classList.add('is-open');
                    m.setAttribute('aria-hidden', 'false');
                }
            }

            function closeEditModal() {
                var m = document.getElementById('als-zone-modal-edit');
                if (m) {
                    m.classList.remove('is-open');
                    m.setAttribute('aria-hidden', 'true');
                }
            }

            function zoneStyleForEntry(r, isSelected, dimOthers) {
                var st = {
                    color: '#047857',
                    fillColor: '#6ee7b7',
                    dashArray: r.meta.is_active ? null : '6 4'
                };
                if (!dimOthers) {
                    st.weight = 2;
                    st.fillOpacity = r.meta.is_active ? 0.32 : 0.07;
                    return st;
                }
                if (isSelected) {
                    st.weight = 4;
                    st.fillOpacity = r.meta.is_active ? 0.55 : 0.22;
                    st.color = '#065f46';
                    return st;
                }
                st.weight = 1;
                st.fillOpacity = 0.06;
                st.color = '#94a3b8';
                st.fillColor = '#e2e8f0';
                return st;
            }

            function applyZonePolygonStyles() {
                var dim = selectedZoneId != null && zoneRegistry[selectedZoneId] != null;
                Object.keys(zoneRegistry).forEach(function (kid) {
                    var r = zoneRegistry[kid];
                    var sid = Number(kid);
                    r.polygon.setStyle(zoneStyleForEntry(r, sid === selectedZoneId, dim));
                });
            }

            function findZoneMeta(id) {
                var sid = Number(id);
                for (var i = 0; i < zones.length; i++) {
                    if (Number(zones[i].id) === sid) return zones[i];
                }
                return null;
            }

            function syncTableRowSelection(sid) {
                document.querySelectorAll('.als-zone-table-row').forEach(function (tr) {
                    var zid = Number(tr.getAttribute('data-zone-id'));
                    tr.classList.toggle('bg-amber-100', zid === sid);
                });
            }

            function syncPickButtons(sid) {
                document.querySelectorAll('.als-zone-pick-btn').forEach(function (b) {
                    var match = Number(b.getAttribute('data-zone-id')) === sid;
                    b.classList.toggle('als-zone-pick-active', match);
                });
            }

            function showEditPanelFromMeta(meta) {
                var f = document.getElementById('als-zone-edit-form');
                if (!f || !meta) return;
                var suf = zoneFormQuerySuffix || '';
                f.setAttribute('action', zoneUrlBase + '/' + meta.id + suf);
                document.getElementById('als-zone-edit-name').value = meta.name || '';
                document.getElementById('als-zone-edit-active').checked = !!meta.is_active;
                var polyEl = document.getElementById('als-zone-edit-polygon');
                if (polyEl) {
                    if (zoneRegistry[meta.id]) {
                        polyEl.value = latlngsToPolygonJson(zoneRegistry[meta.id].latlngs);
                    } else {
                        polyEl.value = (meta.polygon_json != null && meta.polygon_json !== undefined) ? String(meta.polygon_json) : '';
                    }
                }
                document.querySelectorAll('.als-edit-tractor').forEach(function (cb) {
                    cb.checked = (meta.tractor_ids || []).indexOf(cb.value) !== -1;
                });
                requestAnimationFrame(function () {
                    openEditModal();
                    setTimeout(function () {
                        ensureEditModalMap();
                        loadEditDraftForSelectedZone();
                        try {
                            if (editMap) editMap.invalidateSize();
                        } catch (e) { /* */ }
                    }, 200);
                });
            }

            function latlngsToPolygonJson(latlngs) {
                return JSON.stringify(latlngs.map(function (ll) {
                    return { lat: Math.round(ll.lat * 1e6) / 1e6, lng: Math.round(ll.lng * 1e6) / 1e6 };
                }));
            }

            function clearVertexMarkers() {
                vertexLayer.clearLayers();
            }

            function hideEditPanel() {
                closeEditModal();
            }

            function shouldIgnoreStrategicMapDeselect() {
                var addM = document.getElementById('als-zone-modal-add');
                var editM = document.getElementById('als-zone-modal-edit');
                return !!(
                    (addM && addM.classList.contains('is-open')) ||
                    (editM && editM.classList.contains('is-open'))
                );
            }

            function deselectZone() {
                restoreEditMainPolygonIfNeeded();
                selectedZoneId = null;
                clearVertexMarkers();
                teardownEditModalMap();
                applyZonePolygonStyles();
                hideEditPanel();
                syncPickButtons(null);
                syncTableRowSelection(null);
                try {
                    if (bounds.isValid()) {
                        map.fitBounds(bounds.pad(0.08), { maxZoom: 16 });
                    }
                } catch (e2) { /* */ }
            }

            function deselectZoneFromMapBackground() {
                if (shouldIgnoreStrategicMapDeselect()) return;
                deselectZone();
            }

            /** Sorot zona di peta (tanpa modal edit); klik baris tabel / chip. */
            function highlightZoneOnMap(id) {
                var sid = Number(id);
                var meta = findZoneMeta(sid);
                if (!meta) return;
                exitDrawFully();
                closeAddModal(true);
                restoreEditMainPolygonIfNeeded();
                teardownEditModalMap();
                closeEditModal();
                clearVertexMarkers();
                selectedZoneId = zoneRegistry[sid] ? sid : null;
                applyZonePolygonStyles();
                if (zoneRegistry[sid]) {
                    zoneRegistry[sid].polygon.bringToFront();
                    try {
                        map.fitBounds(zoneRegistry[sid].polygon.getBounds().pad(0.06), { maxZoom: 17 });
                    } catch (e) { /* */ }
                }
                syncTableRowSelection(sid);
                syncPickButtons(sid);
            }

            /** Edit: polygon digambar di peta dalam modal (sama alur seperti tambah zona). */
            function selectZoneForEdit(id) {
                var sid = Number(id);
                var meta = findZoneMeta(sid);
                if (!meta) return;
                restoreEditMainPolygonIfNeeded();
                exitDrawFully();
                closeAddModal(true);
                clearVertexMarkers();
                selectedZoneId = sid;
                applyZonePolygonStyles();
                if (zoneRegistry[sid]) {
                    zoneRegistry[sid].polygon.bringToFront();
                }
                showEditPanelFromMeta(meta);
                syncTableRowSelection(sid);
                syncPickButtons(sid);
            }

            window.__alsStrategicSelectZone = selectZoneForEdit;

            zones.forEach(function (z) {
                if (z.id == null) return;
                var raw = z.polygon_json;
                if (raw == null || raw === '') return;
                var ring = extractRing(raw);
                var ll = [];
                ring.forEach(function (pt) {
                    var pair = toLatLngPair(pt);
                    if (pair) ll.push(pair);
                });
                if (ll.length < 3) return;
                var latlngs = ll.map(function (pair) { return L.latLng(pair[0], pair[1]); });
                if (latlngs.length > 1 && latlngs[0].equals(latlngs[latlngs.length - 1])) {
                    latlngs.pop();
                }
                if (latlngs.length < 3) return;
                latlngs.forEach(function (llg) { bounds.extend(llg); });
                var r = { meta: z, latlngs: latlngs, polygon: null };
                r.polygon = L.polygon(latlngs, zoneStyleForEntry(r, false, false)).addTo(zonePolygonLayer);
                r.polygon.bindTooltip((z.name || 'Zona') + ' — area kerja');
                r.polygon.on('click', function (ev) {
                    L.DomEvent.stopPropagation(ev);
                    highlightZoneOnMap(z.id);
                });
                zoneRegistry[z.id] = r;
            });

            positions.forEach(function (p) {
                if (!Number.isFinite(Number(p.lat)) || !Number.isFinite(Number(p.lng))) return;
                bounds.extend([p.lat, p.lng]);
                var cm = L.circleMarker([p.lat, p.lng], { radius: 7, color: '#0f172a', weight: 1, fillColor: statusColor(p.status), fillOpacity: 0.95 })
                    .addTo(map).bindPopup(p.tractor_id + '<br>Engine: ' + (p.engine_on ? 'ON' : 'OFF'));
                cm.on('click', function (ev) {
                    L.DomEvent.stopPropagation(ev);
                    deselectZoneFromMapBackground();
                });
                const rt = routes[p.tractor_id] || [];
                if (rt.length > 1) {
                    var pl = L.polyline(rt.map(function (x) { return [x.lat, x.lng]; }), { color: statusColor(p.status), weight: 3, opacity: 0.85 }).addTo(map);
                    pl.on('click', function (ev) {
                        L.DomEvent.stopPropagation(ev);
                        deselectZoneFromMapBackground();
                    });
                    rt.forEach(function (x) {
                        if (Number.isFinite(x.lat) && Number.isFinite(x.lng)) bounds.extend([x.lat, x.lng]);
                    });
                }
            });
            function applyStrategicFleetMapView() {
                if (!navigator.geolocation || !navigator.geolocation.getCurrentPosition) {
                    if (bounds.isValid()) {
                        map.fitBounds(bounds.pad(0.08), { maxZoom: 16 });
                    }
                    return;
                }
                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        var lat = pos.coords.latitude;
                        var lng = pos.coords.longitude;
                        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                            if (bounds.isValid()) {
                                map.fitBounds(bounds.pad(0.08), { maxZoom: 16 });
                            }
                            return;
                        }
                        var u = L.latLng(lat, lng);
                        if (bounds.isValid()) {
                            var b = L.latLngBounds(bounds.getSouthWest(), bounds.getNorthEast());
                            b.extend(u);
                            map.fitBounds(b.pad(0.08), { maxZoom: 16 });
                        } else {
                            var acc = pos.coords.accuracy;
                            var z = 15;
                            if (Number.isFinite(acc) && acc > 80) z = 13;
                            else if (Number.isFinite(acc) && acc > 40) z = 14;
                            map.setView([lat, lng], z);
                        }
                        try {
                            map.invalidateSize();
                        } catch (e) { /* */ }
                    },
                    function () {
                        if (bounds.isValid()) {
                            map.fitBounds(bounds.pad(0.08), { maxZoom: 16 });
                        } else {
                            map.setView([0, 0], 2);
                        }
                    },
                    alsZoneGeolocationReadOptions()
                );
            }
            applyStrategicFleetMapView();

            /** Klik tile / latar peta (bukan polygon zona): hapus sorotan & kembali ke tampilan seluruh armada. */
            map.on('click', function () {
                if (shouldIgnoreStrategicMapDeselect()) return;
                deselectZone();
            });

            /* —— Gambar zona baru (area kerja) —— polygon hanya di peta modal —— */
            var draftPoints = [];
            var onMapClickDraw = null;

            function drawUiHide() {
                document.getElementById('als-zone-draw-hint').classList.add('hidden');
                document.getElementById('als-zone-draw-undo').classList.add('hidden');
                var cl = document.getElementById('als-zone-draw-clear');
                if (cl) cl.classList.add('hidden');
                document.getElementById('als-zone-draw-cancel').classList.add('hidden');
            }

            function drawUiShow() {
                document.getElementById('als-zone-draw-hint').classList.remove('hidden');
                document.getElementById('als-zone-draw-undo').classList.remove('hidden');
                var cl = document.getElementById('als-zone-draw-clear');
                if (cl) cl.classList.remove('hidden');
                document.getElementById('als-zone-draw-cancel').classList.remove('hidden');
            }

            function stopDrawListen() {
                if (modalMap && onMapClickDraw) {
                    modalMap.off('click', onMapClickDraw);
                    onMapClickDraw = null;
                }
                if (modalMap) modalMap.doubleClickZoom.enable();
            }

            function clearDraft() {
                if (!drawLayer) return;
                drawLayer.clearLayers();
                draftPoints = [];
            }

            function exitDrawFully() {
                stopDrawListen();
                clearDraft();
                drawUiHide();
            }

            function redrawDraftPreview() {
                if (!drawLayer) return;
                drawLayer.clearLayers();
                draftPoints.forEach(function (ll) {
                    L.circleMarker(ll, { radius: 5, color: '#047857', fillColor: '#fff', weight: 2, fillOpacity: 1 }).addTo(drawLayer);
                });
                if (draftPoints.length >= 2) {
                    L.polyline(draftPoints, {
                        dashArray: '5 5',
                        color: '#059669',
                        weight: 2
                    }).addTo(drawLayer);
                }
                if (draftPoints.length >= 3) {
                    var ring = draftPoints.slice().concat([draftPoints[0]]);
                    L.polygon(ring, {
                        color: '#047857',
                        weight: 2,
                        fillColor: '#a7f3d0',
                        fillOpacity: 0.38
                    }).addTo(drawLayer);
                }
            }

            function startDraw() {
                ensureModalMap();
                if (!modalMap || !drawLayer) return;
                deselectZone();
                exitDrawFully();
                modalMap.doubleClickZoom.disable();
                document.getElementById('als-zone-draw-hint').textContent = 'Klik peta di bawah untuk setiap titik (minimal 3). Setelah cukup, klik Simpan di formulir.';
                drawUiShow();
                onMapClickDraw = function (e) {
                    draftPoints.push(e.latlng);
                    redrawDraftPreview();
                };
                modalMap.on('click', onMapClickDraw);
                requestAnimationFrame(function () {
                    try {
                        modalMap.invalidateSize();
                    } catch (e) { /* */ }
                });
            }

            document.getElementById('als-zone-draw-work').addEventListener('click', function () {
                resetAddZoneForm();
                requestAnimationFrame(function () {
                    openAddModal();
                });
            });
            var addFormEl = document.getElementById('als-zone-add-form');
            if (addFormEl) {
                addFormEl.addEventListener('submit', function (e) {
                    var pj = document.getElementById('als-zone-polygon-json');
                    if (draftPoints && draftPoints.length >= 3) {
                        var jsonArr = draftPoints.map(function (ll) {
                            return { lat: Math.round(ll.lat * 1e6) / 1e6, lng: Math.round(ll.lng * 1e6) / 1e6 };
                        });
                        if (pj) pj.value = JSON.stringify(jsonArr);
                        stopDrawListen();
                        drawUiHide();
                    }
                    if (!pj || !pj.value || !String(pj.value).trim()) {
                        e.preventDefault();
                        window.alert('Gambar polygon di peta minimal 3 titik dulu (klik di peta di dalam modal untuk menambah titik; bisa langsung Simpan jika sudah 3+ titik).');
                    }
                });
            }
            document.getElementById('als-zone-draw-cancel').addEventListener('click', function () {
                exitDrawFully();
                var pj = document.getElementById('als-zone-polygon-json');
                if (pj) pj.value = '';
                openAddModal();
            });
            document.getElementById('als-zone-draw-undo').addEventListener('click', function () {
                if (draftPoints.length) draftPoints.pop();
                redrawDraftPreview();
            });
            var drawClearBtn = document.getElementById('als-zone-draw-clear');
            if (drawClearBtn) {
                drawClearBtn.addEventListener('click', function () {
                    clearDraft();
                    redrawDraftPreview();
                });
            }
            var closeEditBtn = document.getElementById('als-zone-edit-close');
            if (closeEditBtn) closeEditBtn.addEventListener('click', deselectZone);

            var addCloseX = document.getElementById('als-zone-modal-add-close');
            if (addCloseX) addCloseX.addEventListener('click', function () { closeAddModal(true); });

            var addBackdrop = document.getElementById('als-zone-modal-add-backdrop');
            if (addBackdrop) addBackdrop.addEventListener('click', function () { closeAddModal(true); });

            var editBackdrop = document.getElementById('als-zone-modal-edit-backdrop');
            if (editBackdrop) editBackdrop.addEventListener('click', deselectZone);

            var editUndo = document.getElementById('als-zone-edit-draw-undo');
            if (editUndo) {
                editUndo.addEventListener('click', function () {
                    if (editDraftPoints.length) editDraftPoints.pop();
                    redrawEditDraftPreview();
                    applyEditDraftToMainPolygon();
                });
            }
            var editClear = document.getElementById('als-zone-edit-draw-clear');
            if (editClear) {
                editClear.addEventListener('click', function () {
                    editDraftPoints = [];
                    redrawEditDraftPreview();
                    applyEditDraftToMainPolygon();
                });
            }
            var editCancel = document.getElementById('als-zone-edit-form-cancel');
            if (editCancel) editCancel.addEventListener('click', deselectZone);

            var alsZoneEditFormEl = document.getElementById('als-zone-edit-form');
            var alsZoneEditSubmitBtn = document.getElementById('als-zone-edit-submit');
            function runEditZoneConfirmAndSubmit() {
                if (!alsZoneEditFormEl) return;
                syncEditPolygonFromDraft();
                var pj = document.getElementById('als-zone-edit-polygon');
                if (!pj || !pj.value || !String(pj.value).trim()) {
                    window.alert('Polygon minimal 3 titik. Klik peta di dalam modal (bukan peta utama) untuk menambah titik.');
                    return;
                }
                if (!alsZoneEditFormEl.reportValidity()) return;
                var nm = document.getElementById('als-zone-edit-name');
                var label = (nm && nm.value && String(nm.value).trim()) ? String(nm.value).trim() : 'zona ini';
                var msg = 'Simpan perubahan pada "' + label + '"?\n\n' +
                    'Data yang dikirim: nama, status aktif/nonaktif, bentuk polygon di peta, dan daftar alat terkait (jika ada).';
                alsZoneConfirmPromise(msg).then(function (ok) {
                    if (!ok) return;
                    HTMLFormElement.prototype.submit.call(alsZoneEditFormEl);
                });
            }
            if (alsZoneEditFormEl) {
                alsZoneEditFormEl.addEventListener('submit', function (e) {
                    e.preventDefault();
                    runEditZoneConfirmAndSubmit();
                });
            }
            if (alsZoneEditSubmitBtn) {
                alsZoneEditSubmitBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    runEditZoneConfirmAndSubmit();
                });
            }

            document.querySelectorAll('button.als-zone-row-delete-btn').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var form = btn.closest('form');
                    if (!form || !form.classList.contains('als-zone-row-delete-form')) return;
                    alsZoneConfirmPromise('Hapus zona ini dari database?\n\nTindakan ini tidak dapat diurungkan.').then(function (ok) {
                        if (!ok) return;
                        HTMLFormElement.prototype.submit.call(form);
                    });
                });
            });

            var strategicSuffix = @json($strategicSuffix);
            var strategicApiBase = {
                gp: @json(url('/strategic/group-scores')),
                an: @json(url('/strategic/anomalies')),
                ut: @json(url('/strategic/utilization-daily')),
                mp: @json(url('/strategic/maintenance-plans')),
                mr: @json(url('/strategic/maintenance-records')),
            };
            var strategicStoreUrls = {
                gp: @json(route('strategic.group-scores.store').$strategicSuffix),
                an: @json(route('strategic.anomalies.store').$strategicSuffix),
                ut: @json(route('strategic.utilization-daily.store').$strategicSuffix),
                mp: @json(route('strategic.maintenance-plans.store').$strategicSuffix),
                mr: @json(route('strategic.maintenance-records.store').$strategicSuffix),
            };

            function strategicItemUrl(kind, id) {
                return strategicApiBase[kind] + '/' + encodeURIComponent(id) + strategicSuffix;
            }

            function strategicDlg(kind) {
                return document.getElementById('strategic-dialog-' + kind);
            }

            function strategicNumStr(v) {
                if (v === null || v === undefined || v === '') return '';
                return String(v);
            }

            function strategicOpenGp(mode, d) {
                var form = document.getElementById('strategic-form-gp');
                var m = document.getElementById('strategic-form-gp-method');
                var title = document.getElementById('strategic-dialog-gp-title');
                if (!form || !m) return;
                form.reset();
                if (mode === 'create') {
                    form.action = strategicStoreUrls.gp;
                    m.value = '';
                    if (title) title.textContent = 'Tambah rapor kinerja';
                } else if (d) {
                    form.action = strategicItemUrl('gp', d.id);
                    m.value = 'PUT';
                    if (title) title.textContent = 'Ubah rapor kinerja';
                    var sel = document.getElementById('strategic-gp-group');
                    if (sel) sel.value = String(d.group_id);
                    document.getElementById('strategic-gp-period').value = d.period || '';
                    document.getElementById('strategic-gp-act').value = strategicNumStr(d.activity_score);
                    document.getElementById('strategic-gp-maint').value = strategicNumStr(d.maintenance_score);
                    document.getElementById('strategic-gp-total').value = strategicNumStr(d.total_score);
                    document.getElementById('strategic-gp-grade').value = d.grade ? String(d.grade).toUpperCase() : '';
                    document.getElementById('strategic-gp-notes').value = d.notes || '';
                }
                var dlg = strategicDlg('gp');
                if (dlg && dlg.showModal) dlg.showModal();
            }

            function strategicOpenAn(mode, d) {
                var form = document.getElementById('strategic-form-an');
                var m = document.getElementById('strategic-form-an-method');
                var title = document.getElementById('strategic-dialog-an-title');
                if (!form || !m) return;
                form.reset();
                if (mode === 'create') {
                    form.action = strategicStoreUrls.an;
                    m.value = '';
                    if (title) title.textContent = 'Tambah anomali';
                } else if (d) {
                    form.action = strategicItemUrl('an', d.id);
                    m.value = 'PUT';
                    if (title) title.textContent = 'Ubah anomali';
                    document.getElementById('strategic-an-detected').value = d.detected_at || '';
                    document.getElementById('strategic-an-tractor').value = d.tractor_id || '';
                    document.getElementById('strategic-an-type').value = d.anomaly_type || '';
                    document.getElementById('strategic-an-sev').value = d.severity || 'HIGH';
                    document.getElementById('strategic-an-status').value = d.status || 'OPEN';
                    document.getElementById('strategic-an-desc').value = d.description || '';
                    document.getElementById('strategic-an-resolved').value = d.resolved_at || '';
                    document.getElementById('strategic-an-res-note').value = d.resolved_note || '';
                }
                var dlg = strategicDlg('an');
                if (dlg && dlg.showModal) dlg.showModal();
            }

            function strategicOpenUt(mode, d) {
                var form = document.getElementById('strategic-form-ut');
                var m = document.getElementById('strategic-form-ut-method');
                var title = document.getElementById('strategic-dialog-ut-title');
                if (!form || !m) return;
                form.reset();
                if (mode === 'create') {
                    form.action = strategicStoreUrls.ut;
                    m.value = '';
                    if (title) title.textContent = 'Tambah utilisasi harian';
                } else if (d) {
                    form.action = strategicItemUrl('ut', d.id);
                    m.value = 'PUT';
                    if (title) title.textContent = 'Ubah utilisasi harian';
                    document.getElementById('strategic-ut-tractor').value = d.tractor_id || '';
                    document.getElementById('strategic-ut-date').value = d.date || '';
                    document.getElementById('strategic-ut-days').value = strategicNumStr(d.active_days_rolling);
                    document.getElementById('strategic-ut-hours').value = strategicNumStr(d.estimated_hours);
                    document.getElementById('strategic-ut-pct').value = strategicNumStr(d.utilization_pct);
                    document.getElementById('strategic-ut-st').value = d.utilization_status || '';
                }
                var dlg = strategicDlg('ut');
                if (dlg && dlg.showModal) dlg.showModal();
            }

            function strategicOpenMp(mode, d) {
                var form = document.getElementById('strategic-form-mp');
                var m = document.getElementById('strategic-form-mp-method');
                var title = document.getElementById('strategic-dialog-mp-title');
                if (!form || !m) return;
                form.reset();
                if (mode === 'create') {
                    form.action = strategicStoreUrls.mp;
                    m.value = '';
                    if (title) title.textContent = 'Tambah rencana maintenance';
                } else if (d) {
                    form.action = strategicItemUrl('mp', d.id);
                    m.value = 'PUT';
                    if (title) title.textContent = 'Ubah rencana maintenance';
                    document.getElementById('strategic-mp-tractor').value = d.tractor_id || '';
                    document.getElementById('strategic-mp-task').value = d.task_type || '';
                    document.getElementById('strategic-mp-int').value = strategicNumStr(d.interval_hours);
                    document.getElementById('strategic-mp-cur').value = strategicNumStr(d.current_hours);
                    document.getElementById('strategic-mp-due').value = strategicNumStr(d.due_hours);
                    document.getElementById('strategic-mp-st').value = d.status || 'PENDING';
                }
                var dlg = strategicDlg('mp');
                if (dlg && dlg.showModal) dlg.showModal();
            }

            function strategicOpenMr(mode, d) {
                var form = document.getElementById('strategic-form-mr');
                var m = document.getElementById('strategic-form-mr-method');
                var title = document.getElementById('strategic-dialog-mr-title');
                if (!form || !m) return;
                form.reset();
                if (mode === 'create') {
                    form.action = strategicStoreUrls.mr;
                    m.value = '';
                    if (title) title.textContent = 'Tambah riwayat kesehatan alat';
                } else if (d) {
                    form.action = strategicItemUrl('mr', d.id);
                    m.value = 'PUT';
                    if (title) title.textContent = 'Ubah riwayat kesehatan alat';
                    document.getElementById('strategic-mr-tractor').value = d.tractor_id || '';
                    document.getElementById('strategic-mr-date').value = d.record_date || '';
                    document.getElementById('strategic-mr-type').value = d.record_type || '';
                    document.getElementById('strategic-mr-desc').value = d.description || '';
                    document.getElementById('strategic-mr-cost').value = strategicNumStr(d.cost);
                    document.getElementById('strategic-mr-tech').value = d.technician || '';
                    document.getElementById('strategic-mr-workshop').value = d.workshop || '';
                }
                var dlg = strategicDlg('mr');
                if (dlg && dlg.showModal) dlg.showModal();
            }

            document.querySelectorAll('.strategic-dialog-cancel').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var dlg = btn.closest('dialog');
                    if (dlg && typeof dlg.close === 'function') try { dlg.close(); } catch (e1) { /* */ }
                });
            });

            document.querySelectorAll('.strategic-form-submit').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    var form = btn.closest('form');
                    if (!form || !form.id || form.id.indexOf('strategic-form-') !== 0) return;
                    e.preventDefault();
                    if (!form.reportValidity()) return;
                    alsZoneConfirmPromise('Simpan data ini?').then(function (ok) {
                        if (!ok) return;
                        HTMLFormElement.prototype.submit.call(form);
                    });
                });
            });

            document.querySelectorAll('.strategic-crud-add').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var kind = btn.getAttribute('data-strategic-kind');
                    if (kind === 'gp') {
                        var sel = document.getElementById('strategic-gp-group');
                        if (sel && sel.options.length <= 1) {
                            window.alert('Belum ada kelompok tani di database. Tambahkan data kelompok tani terlebih dahulu.');
                            return;
                        }
                        strategicOpenGp('create');
                        return;
                    }
                    var trSel = document.getElementById('strategic-an-tractor');
                    if (!trSel || trSel.options.length === 0) {
                        window.alert('Belum ada alat (tractor) di database.');
                        return;
                    }
                    if (kind === 'an') strategicOpenAn('create');
                    else if (kind === 'ut') strategicOpenUt('create');
                    else if (kind === 'mp') strategicOpenMp('create');
                    else if (kind === 'mr') strategicOpenMr('create');
                });
            });

            document.querySelectorAll('.strategic-crud-edit').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var tr = btn.closest('tr');
                    if (!tr) return;
                    var kind = tr.getAttribute('data-strategic-kind');
                    var raw = tr.getAttribute('data-strategic-item');
                    if (!kind || !raw) return;
                    var d;
                    try {
                        d = JSON.parse(raw);
                    } catch (err) {
                        return;
                    }
                    if (kind === 'gp') strategicOpenGp('edit', d);
                    else if (kind === 'an') strategicOpenAn('edit', d);
                    else if (kind === 'ut') strategicOpenUt('edit', d);
                    else if (kind === 'mp') strategicOpenMp('edit', d);
                    else if (kind === 'mr') strategicOpenMr('edit', d);
                });
            });

            document.querySelectorAll('button.als-strategic-delete-btn').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var form = btn.closest('form');
                    if (!form || !form.classList.contains('als-strategic-delete-form')) return;
                    alsZoneConfirmPromise('Hapus data ini dari database?\n\nTindakan ini tidak dapat diurungkan.').then(function (ok) {
                        if (!ok) return;
                        HTMLFormElement.prototype.submit.call(form);
                    });
                });
            });

            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                var editM = document.getElementById('als-zone-modal-edit');
                var addM = document.getElementById('als-zone-modal-add');
                if (editM && editM.classList.contains('is-open')) {
                    deselectZone();
                    e.preventDefault();
                } else if (addM && addM.classList.contains('is-open')) {
                    closeAddModal(true);
                    e.preventDefault();
                }
            });

            document.querySelectorAll('.als-zone-pick-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    highlightZoneOnMap(Number(btn.getAttribute('data-zone-id')));
                });
            });
            document.querySelectorAll('.als-zone-table-row').forEach(function (tr) {
                tr.addEventListener('click', function () {
                    highlightZoneOnMap(Number(tr.getAttribute('data-zone-id')));
                    var mapEl = document.getElementById('strategic-fleet-map');
                    if (mapEl) mapEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                });
            });
            document.querySelectorAll('.als-zone-btn-edit').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    selectZoneForEdit(Number(btn.getAttribute('data-zone-id')));
                    var mapEl = document.getElementById('strategic-fleet-map');
                    if (mapEl) mapEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                });
            });
        })();
    </script>
    <script>
        (function () {
            function strategicSamePath(u) {
                return (
                    u.origin === window.location.origin &&
                    u.pathname.replace(/\/$/, '') === window.location.pathname.replace(/\/$/, '') &&
                    u.pathname.indexOf('strategic') !== -1
                );
            }

            function pjaxReplaceStrategicCard(card, url) {
                var cardId = card.id;
                var fetchUrl = url.split('#')[0];
                return fetch(fetchUrl, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                })
                    .then(function (r) {
                        if (!r.ok) throw new Error('fetch');
                        return r.text();
                    })
                    .then(function (html) {
                        var doc = new DOMParser().parseFromString(html, 'text/html');
                        var next = doc.getElementById(cardId);
                        if (!next) throw new Error('missing');
                        var imported = document.importNode(next, true);
                        card.replaceWith(imported);
                        if (window.history && history.pushState) {
                            history.pushState({ alsStrategicPjax: true }, '', fetchUrl);
                        }
                        bindScrollAnchors();
                    });
            }

            function bindScrollAnchors() {
                document.querySelectorAll('form[data-scroll-anchor]').forEach(function (form) {
                    if (form.dataset.alsScrollBound) return;
                    form.dataset.alsScrollBound = '1';
                    form.addEventListener('submit', function (e) {
                        if (!form.getAttribute('data-scroll-anchor')) return;
                        var card = form.closest('article.als-strat-card');
                        if (!card || !card.id) return;
                        e.preventDefault();
                        var action = form.getAttribute('action') || window.location.pathname;
                        var params = new URLSearchParams(new FormData(form));
                        var qs = params.toString();
                        var url = action + (qs ? '?' + qs : '');
                        pjaxReplaceStrategicCard(card, url).catch(function () {
                            window.location.href = url + '#' + form.getAttribute('data-scroll-anchor');
                        });
                    });
                });
            }

            /** Pagination + link Reset di dalam form filter: fetch, ganti hanya kartu tabel. */
            document.addEventListener('click', function (e) {
                var a = e.target.closest('a[href]');
                if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;

                var fromPagination = a.closest('.als-pagination-wrap');
                var formFilter = a.closest('form[data-scroll-anchor]');
                if (!fromPagination && !formFilter) return;

                var card = a.closest('article.als-strat-card');
                if (!card || !card.id) return;

                var u;
                try {
                    u = new URL(a.getAttribute('href'), window.location.href);
                } catch (err) {
                    return;
                }
                if (!strategicSamePath(u)) return;

                e.preventDefault();
                var url = u.href;
                pjaxReplaceStrategicCard(card, url).catch(function () {
                    window.location.href = url;
                });
            });

            window.addEventListener('popstate', function () {
                window.location.reload();
            });

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindScrollAnchors);
            } else {
                bindScrollAnchors();
            }
        })();
    </script>
    <script>
        (function () {
            var payload = @json($fuelFlowChart);
            if (typeof Chart === 'undefined' || !payload || !Array.isArray(payload.datasets)) {
                return;
            }
            var canvas = document.getElementById('als-fuel-flow-chart-all');
            if (!canvas || payload.datasets.length === 0) {
                return;
            }
            var ctx = canvas.getContext('2d');
            if (!ctx) {
                return;
            }
            /** Sumbu X = timestamp (ms) tanpa plugin date adapter (hindari gagal load CDN / error time scale). */
            function toPoints(rows) {
                if (!rows || !rows.length) {
                    return [];
                }
                return rows.map(function (p) {
                    var t = typeof p.x === 'string' ? Date.parse(p.x) : NaN;
                    var y = typeof p.y === 'number' ? p.y : parseFloat(p.y);
                    if (!Number.isFinite(t) || !Number.isFinite(y)) {
                        return null;
                    }
                    return { x: t, y: y };
                }).filter(function (pt) { return pt !== null; });
            }
            var datasets = payload.datasets.map(function (ds) {
                return {
                    label: ds.label,
                    data: toPoints(ds.data),
                    borderColor: ds.borderColor,
                    backgroundColor: ds.backgroundColor,
                    fill: false,
                    tension: 0.2,
                    pointRadius: 0,
                    pointHitRadius: 5,
                    borderWidth: 2,
                };
            }).filter(function (d) { return d.data.length > 0; });
            if (datasets.length === 0) {
                return;
            }
            new Chart(ctx, {
                type: 'line',
                data: { datasets: datasets },
                options: {
                    parsing: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'nearest', axis: 'x', intersect: false },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: { boxWidth: 12, font: { size: 11 }, padding: 10 },
                        },
                        tooltip: {
                            callbacks: {
                                title: function (items) {
                                    if (!items.length || items[0].parsed.x == null) {
                                        return '';
                                    }
                                    var d = new Date(items[0].parsed.x);
                                    return isFinite(d.getTime()) ? d.toLocaleString('id-ID') : '';
                                },
                                label: function (c) {
                                    var v = c.parsed.y;
                                    var s = (v != null && Number.isFinite(v)) ? Number(v).toFixed(3) : '—';
                                    return (c.dataset.label || '') + ': ' + s + ' L/jam';
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            type: 'linear',
                            title: { display: true, text: 'Waktu' },
                            ticks: {
                                maxRotation: 45,
                                autoSkip: true,
                                maxTicksLimit: 8,
                                callback: function (value) {
                                    var d = new Date(value);
                                    if (!isFinite(d.getTime())) {
                                        return '';
                                    }
                                    return d.toLocaleString('id-ID', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
                                },
                            },
                        },
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'L/jam' },
                        },
                    },
                },
            });
        })();
    </script>
</body>
</html>
