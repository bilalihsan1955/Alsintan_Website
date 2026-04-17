@props([
    'preserverExcept' => [],
    'resetKeys' => [],
    'resetFragment' => null,
])
@php
    /** `*_per_page` tidak ada di form ini — ikut sebagai hidden lewat preserver agar filter tanggal/tabel tetap menjaga ukuran halaman. */
    $except = array_merge(['from', 'to', 'per_page'], $preserverExcept);
    $fromDefault = request('from', isset($from) ? $from->toDateString() : now()->subDays(30)->toDateString());
    $toDefault = request('to', isset($to) ? $to->toDateString() : now()->toDateString());
    $resetQuery = request()->except($resetKeys);
    $resetUrl = route('strategic', $resetQuery);
    if ($resetFragment) {
        $resetUrl .= '#'.$resetFragment;
    }
    $dateCtrl = 'min-h-[38px] w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20';
@endphp
<form method="get" action="{{ route('strategic') }}" class="min-w-0 w-full" @if($resetFragment) data-scroll-anchor="{{ $resetFragment }}" @endif>
    <x-strategic.preserver :except="$except" />
    <div class="flex flex-nowrap items-end gap-2 overflow-x-auto pb-0.5 [scrollbar-width:thin]">
        <label class="flex w-[9.5rem] flex-shrink-0 flex-col gap-0.5 text-xs font-medium text-slate-600">
            <span>Dari tanggal</span>
            <input type="date" name="from" value="{{ $fromDefault }}" class="{{ $dateCtrl }}">
        </label>
        <label class="flex w-[9.5rem] flex-shrink-0 flex-col gap-0.5 text-xs font-medium text-slate-600">
            <span>Sampai tanggal</span>
            <input type="date" name="to" value="{{ $toDefault }}" class="{{ $dateCtrl }}">
        </label>
        <div class="flex min-w-0 flex-1 flex-nowrap items-end gap-2">
            {{ $slot }}
        </div>
        <div class="flex shrink-0 gap-2">
            <button type="submit" class="inline-flex min-h-[38px] items-center justify-center whitespace-nowrap rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">Terapkan</button>
            <a href="{{ $resetUrl }}" class="inline-flex min-h-[38px] items-center justify-center whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
        </div>
    </div>
</form>
