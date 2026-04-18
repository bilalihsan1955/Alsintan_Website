@props([
    'preserverExcept' => [],
    'resetKeys' => [],
    'resetFragment' => null,
    /** Tampilkan input Dari/Sampai (filter global strategic). Matikan untuk kartu read-only seperti log Geofence Alerts. */
    'showStrategicDateRange' => true,
    /** Fallback tanggal jika tidak ada di query (biasanya dari controller). */
    'strategicFrom' => null,
    'strategicTo' => null,
])
@php
    /** `*_per_page` tidak ada di form ini — ikut sebagai hidden lewat preserver agar filter tanggal/tabel tetap menjaga ukuran halaman. */
    $except = array_merge(['from', 'to', 'per_page'], $preserverExcept);
    $fromDefault = request('from', $strategicFrom ?? now()->subDays(30)->toDateString());
    $toDefault = request('to', $strategicTo ?? now()->toDateString());
    $resetQuery = request()->except($resetKeys);
    $resetUrl = route('strategic', $resetQuery);
    if ($resetFragment) {
        $resetUrl .= '#'.$resetFragment;
    }
    $dateCtrl = 'min-h-[38px] w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20';
@endphp
<form method="get" action="{{ route('strategic') }}" class="min-w-0 w-full" @if($resetFragment) data-scroll-anchor="{{ $resetFragment }}" @endif>
    <x-strategic.preserver :except="$except" />
    {{--
        Responsif tanpa scroll horizontal di tablet/medium:
        - Mobile / md: `flex-wrap` (baris terbagi, tanggal 2 kolom 50/50).
        - lg ke atas: tetap satu baris (`lg:flex-nowrap`).
    --}}
    <div class="flex flex-wrap items-end gap-2 lg:flex-nowrap lg:overflow-x-auto lg:pb-0.5 lg:[scrollbar-width:thin]">
        @if ($showStrategicDateRange)
            <label class="flex w-[calc(50%-0.25rem)] flex-col gap-0.5 text-xs font-medium text-slate-600 sm:w-[9.5rem] sm:flex-shrink-0">
                <span>Dari tanggal</span>
                <input type="date" name="from" value="{{ $fromDefault }}" class="{{ $dateCtrl }}">
            </label>
            <label class="flex w-[calc(50%-0.25rem)] flex-col gap-0.5 text-xs font-medium text-slate-600 sm:w-[9.5rem] sm:flex-shrink-0">
                <span>Sampai tanggal</span>
                <input type="date" name="to" value="{{ $toDefault }}" class="{{ $dateCtrl }}">
            </label>
        @else
            <input type="hidden" name="from" value="{{ $fromDefault }}">
            <input type="hidden" name="to" value="{{ $toDefault }}">
        @endif
        {{-- Slot filter per tabel: boleh wrap di mobile/md, satu baris di lg. --}}
        <div class="flex w-full min-w-0 flex-wrap items-end gap-2 lg:w-auto lg:flex-1 lg:flex-nowrap">
            {{ $slot }}
        </div>
        <div class="ml-auto flex shrink-0 gap-2">
            <button type="submit" class="inline-flex min-h-[38px] items-center justify-center whitespace-nowrap rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">Terapkan</button>
            <a href="{{ $resetUrl }}" class="inline-flex min-h-[38px] items-center justify-center whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
        </div>
    </div>
</form>
