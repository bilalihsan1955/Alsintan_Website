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
    <style>
        #strategic-fleet-map { min-height: 320px; position: relative; z-index: 0; isolation: isolate; }
        @media (min-width: 768px) { #strategic-fleet-map { min-height: 420px; } }
        .als-strat-map-card { overflow: visible; }
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
    </style>
</head>
<body class="h-full bg-slate-50 pb-28 text-slate-900 antialiased md:pb-8">
    @include('partials.alsintan-nav', ['active' => 'strategic'])

    <div class="als-page-content mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        @if (session('ok'))
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">{{ session('ok') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
                <p class="font-semibold">Tidak dapat menyimpan zona:</p>
                <ul class="mt-2 list-inside list-disc text-xs">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
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

        {{-- Grafik cakupan & BBM: tidak ditampilkan (data KPI tetap memakai rentang Dari/Sampai di filter tiap tabel) --}}

        {{-- 6) Geofencing --}}
        <section class="mb-10 space-y-6">
            <h2 class="als-section-title flex items-center text-lg font-bold">Smart Geofencing — Anti Pencurian</h2>
            @php
                $zoneStoreUrl = route('strategic.zones.store');
                $zoneQs = request()->getQueryString();
                if ($zoneQs !== null && $zoneQs !== '') {
                    $zoneStoreUrl .= '?'.$zoneQs;
                }
                $zoneFormQuerySuffix = ($zoneQs !== null && $zoneQs !== '') ? '?'.$zoneQs : '';
                $zoneApiBase = url('/strategic/zones');
            @endphp
            <div class="als-strat-map-card rounded-2xl border border-slate-100 bg-white shadow-sm ring-1 ring-slate-100/80">
                <div class="flex flex-col gap-2 border-b border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Peta armada &amp; area kerja</h3>
                        <p class="mt-0.5 text-xs text-slate-600">Satu jenis zona (area kerja) untuk geofence. Jalur tipis = riwayat GPS per alat; antara log EXIT dan ENTER berikutnya alat dapat dilacak di telemetri.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-[11px]">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 font-medium text-emerald-800"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500" aria-hidden="true"></span>Area kerja</span>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2 py-0.5 text-slate-600"><span class="h-2 w-2 rounded-full bg-sky-500" aria-hidden="true"></span>Posisi alat</span>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/90 px-4 py-2.5 sm:px-5">
                    <button type="button" id="als-zone-draw-work" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1">+ Gambar area kerja</button>
                    <span id="als-zone-draw-hint" class="hidden min-w-[10rem] flex-1 text-xs leading-snug text-slate-600"></span>
                    <button type="button" id="als-zone-draw-finish" class="hidden rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50">Selesai gambar</button>
                    <button type="button" id="als-zone-draw-undo" class="hidden rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50">Urung titik</button>
                    <button type="button" id="als-zone-draw-cancel" class="hidden text-xs font-semibold text-rose-600 hover:text-rose-800">Batal</button>
                </div>
                <div class="relative p-3 sm:p-4">
                    <div id="strategic-fleet-map" class="w-full rounded-xl border border-slate-100 bg-slate-100/40 shadow-inner"></div>

                    @if ($geofenceZonesAll->isNotEmpty())
                        <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Pilih zona di peta</p>
                            <div class="mt-1.5 flex max-h-28 flex-wrap gap-1.5 overflow-y-auto">
                                @foreach ($geofenceZonesAll as $zPick)
                                    <button type="button" class="als-zone-pick-btn rounded-md border border-slate-200 bg-white px-2 py-1 text-left text-[11px] font-medium text-slate-800 shadow-sm hover:border-amber-400 hover:bg-amber-50" data-zone-id="{{ $zPick->id }}">{{ $zPick->name }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm ring-1 ring-slate-100/80">
                <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
                    <h3 class="text-sm font-semibold text-slate-900">Daftar zona geofence</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Polygon dari kolom <span class="font-mono text-[11px]">polygon_json</span>; kosong atau tidak valid = tidak digambar di peta.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-4 py-3">Nama</th>
                                <th class="px-4 py-3">Aktif</th>
                                <th class="px-4 py-3">Titik</th>
                                <th class="min-w-[12rem] px-4 py-3">Titik pertama (urutan di DB)</th>
                                <th class="whitespace-nowrap px-4 py-3">Peta</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($geofenceZonesAll as $zone)
                                @php $zm = $zonePolygonMeta($zone); @endphp
                                <tr class="hover:bg-slate-50/80">
                                    <td class="px-4 py-2.5 font-medium text-slate-900">{{ $zone->name }}</td>
                                    <td class="px-4 py-2.5">{{ $zone->is_active ? 'Ya' : 'Tidak' }}</td>
                                    <td class="px-4 py-2.5 tabular-nums">{{ $zm['pts'] }}</td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-slate-600">{{ $zm['preview'] }}</td>
                                    <td class="px-4 py-2.5">
                                        @if ($zm['pts'] >= 3)
                                            <button type="button" class="als-zone-table-focus rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-900 hover:bg-amber-100" data-zone-id="{{ $zone->id }}">Sorot &amp; edit</button>
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
                                        @endif
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
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($groupScores as $g)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-3 py-3 tabular-nums text-slate-600">{{ ($groupScores->currentPage() - 1) * $groupScores->perPage() + $loop->iteration }}</td>
                                <td class="px-3 py-3 font-medium">{{ optional($g->group)->name ?? '—' }}</td>
                                <td class="px-3 py-3">{{ optional($g->group)->village ?? '—' }}</td>
                                <td class="px-3 py-3 font-mono text-xs">{{ $g->period }}</td>
                                <td class="px-3 py-3 tabular-nums">{{ number_format((float) $g->activity_score, 2) }}</td>
                                <td class="px-3 py-3 tabular-nums">{{ number_format((float) $g->maintenance_score, 2) }}</td>
                                <td class="px-3 py-3 tabular-nums font-semibold">{{ number_format((float) $g->total_score, 2) }}</td>
                                <td class="px-3 py-3"><x-strategic.badge :variant="$gradeVariant($g->grade)">{{ $g->grade }}</x-strategic.badge></td>
                                <td class="max-w-xs truncate px-3 py-3 text-slate-600" title="{{ $g->notes }}">{{ $g->notes ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-10 text-center text-slate-500">Belum ada data rapor untuk periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-strategic.table-shell>

            <x-strategic.table-shell title="Anomaly Detection System" subtitle="Insiden &amp; status penanganan" icon="alert" fragment="strategic-an-section" per-page-key="an_per_page" :paginator="$anomalies">
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
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($anomalies as $a)
                            <tr class="hover:bg-slate-50/80">
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ optional($a->detected_at)->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 font-mono text-sm">{{ $a->tractor_id }}</td>
                                <td class="px-4 py-3">{{ $a->anomaly_type }}</td>
                                <td class="px-4 py-3"><x-strategic.badge :variant="$sevVariant($a->severity)">{{ strtoupper($a->severity) }}</x-strategic.badge></td>
                                <td class="max-w-md px-4 py-3 text-slate-700">{{ \Illuminate\Support\Str::limit($a->description, 120) }}</td>
                                <td class="px-4 py-3">
                                    <x-strategic.badge :variant="strtoupper((string) $a->status) === 'OPEN' ? 'st-open' : 'st-resolved'">{{ strtoupper($a->status) }}</x-strategic.badge>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada data anomali.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-strategic.table-shell>
        </section>

        {{-- 8) Utilization --}}
        <section class="mb-10">
            <h2 class="als-section-title mb-4 text-lg font-bold">Utilization Rate</h2>
            <x-strategic.table-shell title="Utilization Rate" subtitle="Estimasi jam &amp; tingkat utilisasi" icon="table" fragment="strategic-ut-section" per-page-key="ut_per_page" :paginator="$utilizationRows">
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
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($utilizationRows as $u)
                            @php $pct = min(100, max(0, (float) $u->utilization_pct)); @endphp
                            <tr class="hover:bg-slate-50/80">
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
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">Belum ada data utilisasi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-strategic.table-shell>
        </section>

        {{-- 9) Maintenance --}}
        <section class="mb-10 space-y-8">
            <h2 class="als-section-title text-lg font-bold">Maintenance &amp; Health Record</h2>

            <x-strategic.table-shell title="Predictive Maintenance Alert" subtitle="Interval &amp; jam kerja mesin" icon="alert" fragment="strategic-mp-section" per-page-key="mp_per_page" :paginator="$maintenancePlans">
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
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($maintenancePlans as $m)
                            @php
                                $due = (float) ($m->due_hours ?? 0);
                                $cur = (float) ($m->current_hours ?? 0);
                                $prog = $due > 0 ? min(100, ($cur / $due) * 100) : 0;
                            @endphp
                            <tr class="hover:bg-slate-50/80">
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
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">Belum ada rencana maintenance.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-strategic.table-shell>

            <x-strategic.table-shell title="Digital Health Record (Kartu Riwayat)" subtitle="Biaya &amp; teknisi" icon="table" fragment="strategic-mr-section" per-page-key="mr_per_page" :paginator="$maintenanceRecords">
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
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($maintenanceRecords as $r)
                            <tr class="hover:bg-slate-50/80">
                                <td class="whitespace-nowrap px-4 py-3">{{ optional($r->record_date)->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 font-mono text-sm">{{ $r->tractor_id }}</td>
                                <td class="px-4 py-3"><x-strategic.badge variant="health">{{ $healthTypeLabel($r->record_type) }}</x-strategic.badge></td>
                                <td class="max-w-md px-4 py-3 text-slate-700">{{ \Illuminate\Support\Str::limit($r->description, 100) }}</td>
                                <td class="px-4 py-3 tabular-nums font-medium">Rp {{ number_format((float) $r->cost, 0, ',', '.') }}</td>
                                <td class="px-4 py-3">{{ $r->technician ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada riwayat kesehatan alat.</td></tr>
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

    {{-- Modal zona: tambah (setelah gambar polygon) --}}
    <div id="als-zone-modal-add" class="fixed inset-0 z-[10000] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="als-zone-modal-add-title" aria-hidden="true" style="display: none;">
        <button type="button" id="als-zone-modal-add-backdrop" class="absolute inset-0 bg-slate-900/50" aria-label="Tutup"></button>
        <div class="relative z-10 flex max-h-[min(90vh,36rem)] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200">
            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-100 bg-emerald-50/40 px-5 py-4">
                <h4 id="als-zone-modal-add-title" class="text-base font-semibold text-slate-900">Simpan zona baru</h4>
                <button type="button" id="als-zone-modal-add-close" class="rounded-lg p-1.5 text-slate-500 transition hover:bg-white hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                <p class="text-xs text-slate-600">Nama akan tampil di peta dan tabel. Polygon mengikuti titik yang Anda klik di peta (urutan menggambar).</p>
                <form method="post" class="mt-4 space-y-3" action="{{ $zoneStoreUrl }}" id="als-zone-add-form">
                    @csrf
                    <input type="hidden" name="is_active" value="1">
                    <div>
                        <label for="als-zone-name" class="text-xs font-medium text-slate-700">Nama zona</label>
                        <input type="text" name="name" id="als-zone-name" value="{{ old('name') }}" required maxlength="120" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" placeholder="Contoh: Area kerja Blok A">
                    </div>
                    <textarea name="polygon_json" id="als-zone-polygon-json" required class="sr-only" tabindex="-1"></textarea>
                    @if ($tractors->isNotEmpty() && ($zoneTractorFeatureReady ?? false))
                        <details class="text-xs">
                            <summary class="cursor-pointer font-medium text-slate-600">Kaitkan alat (opsional)</summary>
                            <div class="mt-2 max-h-36 space-y-1.5 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50/80 p-2">
                                @foreach ($tractors as $tr)
                                    <label class="flex cursor-pointer items-center gap-2 rounded px-1 py-0.5 hover:bg-white">
                                        <input type="checkbox" name="tractor_ids[]" value="{{ $tr->id }}" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                        <span class="font-mono text-[11px] text-slate-800">{{ $tr->id }}</span>
                                        @if ($tr->name)
                                            <span class="text-slate-500">{{ $tr->name }}</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </details>
                    @endif
                    <div class="flex flex-wrap gap-2 pt-1">
                        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">Simpan ke database</button>
                        <button type="button" id="als-zone-form-redraw" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Gambar ulang</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal zona: edit (saat zona dipilih di peta) --}}
    <div id="als-zone-modal-edit" class="fixed inset-0 z-[10000] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="als-zone-modal-edit-title" aria-hidden="true" style="display: none;">
        <button type="button" id="als-zone-modal-edit-backdrop" class="absolute inset-0 bg-slate-900/50" aria-label="Tutup"></button>
        <div class="relative z-10 flex max-h-[min(90vh,36rem)] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-amber-200">
            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-amber-100 bg-amber-50/50 px-5 py-4">
                <div>
                    <h4 id="als-zone-modal-edit-title" class="text-base font-semibold text-amber-950">Edit zona</h4>
                    <p class="mt-0.5 text-[11px] text-amber-900/85">Tarik titik oranye di peta untuk mengubah bentuk. Zona terpilih disorot.</p>
                </div>
                <button type="button" id="als-zone-edit-close" class="shrink-0 rounded-lg p-1.5 text-amber-900/70 transition hover:bg-amber-100 hover:text-amber-950 focus:outline-none focus:ring-2 focus:ring-amber-500" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                <form id="als-zone-edit-form" method="post" class="space-y-3" action="#">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="polygon_json" id="als-zone-edit-polygon" value="">
                    <div>
                        <label for="als-zone-edit-name" class="text-xs font-medium text-slate-700">Nama</label>
                        <input type="text" name="name" id="als-zone-edit-name" required maxlength="120" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/25">
                    </div>
                    <label class="flex cursor-pointer items-center gap-2 text-xs text-slate-700">
                        <input type="checkbox" name="is_active" id="als-zone-edit-active" value="1" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                        Zona aktif (dipakai evaluasi geofence)
                    </label>
                    @if ($tractors->isNotEmpty() && ($zoneTractorFeatureReady ?? false))
                        <details class="text-xs" open>
                            <summary class="cursor-pointer font-medium text-slate-600">Alat terkait</summary>
                            <div class="mt-2 max-h-32 space-y-1 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50/80 p-2">
                                @foreach ($tractors as $tr)
                                    <label class="flex cursor-pointer items-center gap-2 rounded px-1 py-0.5 hover:bg-white">
                                        <input type="checkbox" name="tractor_ids[]" value="{{ $tr->id }}" class="als-edit-tractor rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                                        <span class="font-mono text-[11px]">{{ $tr->id }}</span>
                                        @if ($tr->name)<span class="text-slate-500">{{ $tr->name }}</span>@endif
                                    </label>
                                @endforeach
                            </div>
                        </details>
                    @endif
                    <div class="flex flex-wrap gap-2 pt-1">
                        <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">Simpan perubahan</button>
                    </div>
                </form>
                <form id="als-zone-delete-form" method="post" action="#" class="mt-4 border-t border-slate-100 pt-4">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-800 hover:bg-rose-100" onclick="return confirm('Hapus zona ini dari database?');">Hapus zona</button>
                </form>
            </div>
        </div>
    </div>

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
            const map = L.map('strategic-fleet-map', { scrollWheelZoom: true }).setView([-2.5, 118], 5);
            L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', { maxZoom: 22, attribution: '&copy; Google' }).addTo(map);
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
                    m.style.display = 'flex';
                    m.setAttribute('aria-hidden', 'false');
                }
                setTimeout(function () {
                    var n = document.getElementById('als-zone-name');
                    if (n) n.focus();
                }, 80);
            }

            function closeAddModal(clearForm) {
                var m = document.getElementById('als-zone-modal-add');
                if (m) {
                    m.style.display = 'none';
                    m.setAttribute('aria-hidden', 'true');
                }
                if (clearForm) resetAddZoneForm();
            }

            function openEditModal() {
                var m = document.getElementById('als-zone-modal-edit');
                if (m) {
                    m.style.display = 'flex';
                    m.setAttribute('aria-hidden', 'false');
                }
            }

            function closeEditModal() {
                var m = document.getElementById('als-zone-modal-edit');
                if (m) {
                    m.style.display = 'none';
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
                var dim = selectedZoneId != null;
                Object.keys(zoneRegistry).forEach(function (kid) {
                    var r = zoneRegistry[kid];
                    var sid = Number(kid);
                    r.polygon.setStyle(zoneStyleForEntry(r, sid === selectedZoneId, dim));
                });
            }

            function latlngsToPolygonJson(latlngs) {
                return JSON.stringify(latlngs.map(function (ll) {
                    return { lat: Math.round(ll.lat * 1e6) / 1e6, lng: Math.round(ll.lng * 1e6) / 1e6 };
                }));
            }

            function syncEditPolygonField() {
                var el = document.getElementById('als-zone-edit-polygon');
                if (!el || selectedZoneId == null) return;
                var r = zoneRegistry[selectedZoneId];
                if (!r) return;
                el.value = latlngsToPolygonJson(r.latlngs);
            }

            function clearVertexMarkers() {
                vertexLayer.clearLayers();
            }

            var vertexIcon = L.divIcon({
                className: 'als-vertex-root',
                html: '<div class="als-vertex-dot"></div>',
                iconSize: [22, 22],
                iconAnchor: [11, 11]
            });

            function buildVertexMarkers() {
                clearVertexMarkers();
                if (selectedZoneId == null) return;
                var r = zoneRegistry[selectedZoneId];
                if (!r) return;
                r.latlngs.forEach(function (latlng, idx) {
                    var m = L.marker(latlng, { draggable: true, icon: vertexIcon, zIndexOffset: 800, pane: 'alsVertexPane' });
                    m.addTo(vertexLayer);
                    m.on('drag', function () {
                        r.latlngs[idx] = m.getLatLng();
                        r.polygon.setLatLngs(r.latlngs);
                        syncEditPolygonField();
                    });
                    m.on('dragend', syncEditPolygonField);
                });
            }

            function hideEditPanel() {
                closeEditModal();
            }

            function showEditPanel(r) {
                var f = document.getElementById('als-zone-edit-form');
                var df = document.getElementById('als-zone-delete-form');
                if (!f || !df) return;
                var suf = zoneFormQuerySuffix || '';
                f.setAttribute('action', zoneUrlBase + '/' + r.meta.id + suf);
                df.setAttribute('action', zoneUrlBase + '/' + r.meta.id + suf);
                document.getElementById('als-zone-edit-name').value = r.meta.name || '';
                document.getElementById('als-zone-edit-active').checked = !!r.meta.is_active;
                syncEditPolygonField();
                document.querySelectorAll('.als-edit-tractor').forEach(function (cb) {
                    cb.checked = (r.meta.tractor_ids || []).indexOf(cb.value) !== -1;
                });
                openEditModal();
            }

            function deselectZone() {
                selectedZoneId = null;
                clearVertexMarkers();
                applyZonePolygonStyles();
                hideEditPanel();
                document.querySelectorAll('.als-zone-pick-btn').forEach(function (b) {
                    b.classList.remove('ring-2', 'ring-amber-500', 'bg-amber-100');
                });
                document.querySelectorAll('.als-zone-table-focus').forEach(function (b) {
                    b.classList.remove('ring-2', 'ring-amber-500');
                });
            }

            function selectZone(id) {
                var sid = Number(id);
                if (!zoneRegistry[sid]) return;
                exitDrawFully();
                closeAddModal(true);
                selectedZoneId = sid;
                applyZonePolygonStyles();
                zoneRegistry[sid].polygon.bringToFront();
                buildVertexMarkers();
                showEditPanel(zoneRegistry[sid]);
                document.querySelectorAll('.als-zone-pick-btn').forEach(function (b) {
                    b.classList.toggle('ring-2', Number(b.getAttribute('data-zone-id')) === sid);
                    b.classList.toggle('ring-amber-500', Number(b.getAttribute('data-zone-id')) === sid);
                    b.classList.toggle('bg-amber-100', Number(b.getAttribute('data-zone-id')) === sid);
                });
                document.querySelectorAll('.als-zone-table-focus').forEach(function (b) {
                    var match = Number(b.getAttribute('data-zone-id')) === sid;
                    b.classList.toggle('ring-2', match);
                    b.classList.toggle('ring-amber-500', match);
                });
                try {
                    map.fitBounds(zoneRegistry[sid].polygon.getBounds().pad(0.06), { maxZoom: 17 });
                } catch (e) { /* */ }
            }

            window.__alsStrategicSelectZone = selectZone;

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
                    selectZone(z.id);
                });
                zoneRegistry[z.id] = r;
            });

            positions.forEach(function (p) {
                if (!Number.isFinite(Number(p.lat)) || !Number.isFinite(Number(p.lng))) return;
                bounds.extend([p.lat, p.lng]);
                L.circleMarker([p.lat, p.lng], { radius: 7, color: '#0f172a', weight: 1, fillColor: statusColor(p.status), fillOpacity: 0.95 })
                    .addTo(map).bindPopup(p.tractor_id + '<br>Engine: ' + (p.engine_on ? 'ON' : 'OFF'));
                const rt = routes[p.tractor_id] || [];
                if (rt.length > 1) {
                    L.polyline(rt.map(function (x) { return [x.lat, x.lng]; }), { color: statusColor(p.status), weight: 3, opacity: 0.85 }).addTo(map);
                    rt.forEach(function (x) {
                        if (Number.isFinite(x.lat) && Number.isFinite(x.lng)) bounds.extend([x.lat, x.lng]);
                    });
                }
            });
            if (bounds.isValid()) {
                map.fitBounds(bounds.pad(0.08), { maxZoom: 16 });
            }

            /* —— Gambar zona baru (area kerja) —— */
            var drawLayer = L.layerGroup().addTo(map);
            var draftPoints = [];
            var onMapClickDraw = null;

            function drawUiHide() {
                document.getElementById('als-zone-draw-hint').classList.add('hidden');
                document.getElementById('als-zone-draw-finish').classList.add('hidden');
                document.getElementById('als-zone-draw-undo').classList.add('hidden');
                document.getElementById('als-zone-draw-cancel').classList.add('hidden');
            }

            function drawUiShow() {
                document.getElementById('als-zone-draw-hint').classList.remove('hidden');
                document.getElementById('als-zone-draw-finish').classList.remove('hidden');
                document.getElementById('als-zone-draw-undo').classList.remove('hidden');
                document.getElementById('als-zone-draw-cancel').classList.remove('hidden');
            }

            function stopDrawListen() {
                if (onMapClickDraw) {
                    map.off('click', onMapClickDraw);
                    onMapClickDraw = null;
                }
                map.doubleClickZoom.enable();
            }

            function clearDraft() {
                drawLayer.clearLayers();
                draftPoints = [];
            }

            function exitDrawFully() {
                stopDrawListen();
                clearDraft();
                drawUiHide();
            }

            function redrawDraftPreview() {
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
                deselectZone();
                exitDrawFully();
                closeAddModal(true);
                map.doubleClickZoom.disable();
                document.getElementById('als-zone-draw-hint').textContent = 'Klik peta untuk setiap sudut area kerja (minimal 3). Lalu klik "Selesai gambar".';
                drawUiShow();
                onMapClickDraw = function (e) {
                    draftPoints.push(e.latlng);
                    redrawDraftPreview();
                };
                map.on('click', onMapClickDraw);
            }

            document.getElementById('als-zone-draw-work').addEventListener('click', function () { startDraw(); });
            document.getElementById('als-zone-draw-cancel').addEventListener('click', function () {
                exitDrawFully();
                closeAddModal(true);
            });
            document.getElementById('als-zone-draw-undo').addEventListener('click', function () {
                if (draftPoints.length) draftPoints.pop();
                redrawDraftPreview();
            });
            document.getElementById('als-zone-draw-finish').addEventListener('click', function () {
                if (draftPoints.length < 3) {
                    window.alert('Minimal 3 titik untuk membentuk polygon.');
                    return;
                }
                var jsonArr = draftPoints.map(function (ll) {
                    return { lat: Math.round(ll.lat * 1e6) / 1e6, lng: Math.round(ll.lng * 1e6) / 1e6 };
                });
                document.getElementById('als-zone-polygon-json').value = JSON.stringify(jsonArr);
                document.getElementById('als-zone-name').value = '';
                stopDrawListen();
                drawUiHide();
                openAddModal();
            });
            document.getElementById('als-zone-form-redraw').addEventListener('click', function () {
                closeAddModal(false);
                clearDraft();
                startDraw();
            });

            var closeEditBtn = document.getElementById('als-zone-edit-close');
            if (closeEditBtn) closeEditBtn.addEventListener('click', deselectZone);

            var addBackdrop = document.getElementById('als-zone-modal-add-backdrop');
            var addCloseX = document.getElementById('als-zone-modal-add-close');
            if (addBackdrop) addBackdrop.addEventListener('click', function () { closeAddModal(true); });
            if (addCloseX) addCloseX.addEventListener('click', function () { closeAddModal(true); });

            var editBackdrop = document.getElementById('als-zone-modal-edit-backdrop');
            if (editBackdrop) editBackdrop.addEventListener('click', deselectZone);

            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                var editM = document.getElementById('als-zone-modal-edit');
                var addM = document.getElementById('als-zone-modal-add');
                if (editM && editM.style.display === 'flex') {
                    deselectZone();
                    e.preventDefault();
                } else if (addM && addM.style.display === 'flex') {
                    closeAddModal(true);
                    e.preventDefault();
                }
            });

            document.querySelectorAll('.als-zone-pick-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    selectZone(Number(btn.getAttribute('data-zone-id')));
                });
            });
            document.querySelectorAll('.als-zone-table-focus').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    selectZone(Number(btn.getAttribute('data-zone-id')));
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
</body>
</html>
