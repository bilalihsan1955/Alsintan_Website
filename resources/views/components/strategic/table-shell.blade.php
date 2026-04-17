@props([
    'title',
    'subtitle' => null,
    'icon' => 'table',
    'fragment' => null,
    'paginator' => null,
    'newTabAnchor' => '',
    'showNewTab' => true,
    'perPageKey' => null,
])
@php
    $perPagePageKey = $perPageKey ? str_replace('_per_page', '_page', $perPageKey) : null;
@endphp

<article {{ $attributes->merge(['class' => 'als-strat-card rounded-2xl border border-slate-200/80 bg-white shadow-sm']) }} @if($fragment) id="{{ $fragment }}" @endif>
    <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex min-w-0 gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                    @if($icon === 'map')
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    @elseif($icon === 'chart')
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>
                    @elseif($icon === 'alert')
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    @else
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    @endif
                </span>
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-slate-900">{{ $title }}</h3>
                    @if($subtitle)
                        <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
                    @endif
                </div>
            </div>
            @if($showNewTab)
                <a href="{{ url()->full() }}{{ $newTabAnchor }}" target="_blank" rel="noopener noreferrer"
                    class="inline-flex h-8 shrink-0 items-center justify-center self-start rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-50 sm:self-center">
                    Buka di halaman baru
                </a>
            @endif
        </div>
        @if($toolbar ?? null)
            <div class="mt-2 min-w-0">
                {{ $toolbar }}
            </div>
        @endif
    </div>
    <div class="overflow-x-auto">
        {{ $slot }}
    </div>
    @if($paginator)
        @php
            $perPageVal = $perPageKey ? (string) request($perPageKey, request('per_page', '10')) : '';
        @endphp
        <div class="flex flex-nowrap items-center justify-between gap-2 overflow-x-auto border-t border-slate-100 bg-slate-50/50 px-4 py-2 [scrollbar-width:thin] sm:px-5">
            @if($perPageKey)
                <form method="get" action="{{ route('strategic') }}" class="flex shrink-0 items-center gap-2" @if($fragment) data-scroll-anchor="{{ $fragment }}" @endif>
                    <x-strategic.preserver :except="array_values(array_filter([$perPageKey, $perPagePageKey]))" />
                    <label class="inline-flex items-center gap-1.5 text-[11px] font-medium text-slate-600">
                        <span class="whitespace-nowrap">Data/hal.</span>
                        <x-ui.custom-select name="{{ $perPageKey }}" :options="['10' => '10', '50' => '50']" :selected="$perPageVal" submit-on-change size="sm" class="w-[4.25rem]" />
                    </label>
                </form>
            @endif
            <div class="flex min-w-0 flex-1 items-center justify-end gap-3">
                <p class="shrink-0 text-xs tabular-nums text-slate-600">
                    <span class="font-medium text-slate-800">{{ $paginator->firstItem() ?? 0 }}</span>
                    –
                    <span class="font-medium text-slate-800">{{ $paginator->lastItem() ?? 0 }}</span>
                    <span class="text-slate-400"> / </span>
                    <span class="font-medium text-slate-800">{{ $paginator->total() }}</span>
                </p>
                <div class="als-pagination-wrap flex shrink-0">{{ $paginator->links('pagination.strategic-simple') }}</div>
            </div>
        </div>
    @endif
</article>
