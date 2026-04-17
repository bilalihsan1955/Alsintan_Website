@props([
    'name',
    'options' => [],
    'selected' => null,
    'id' => null,
    'submitOnChange' => false,
    'size' => 'md',
    'variant' => 'default',
])

@php
    $selectedStr = $selected === null ? '' : (string) $selected;
    $labelText = '—';
    foreach ($options as $k => $lab) {
        if ((string) $k === $selectedStr) {
            $labelText = $lab;
            break;
        }
    }
    if ($labelText === '—' && ! empty($options)) {
        $firstKey = array_key_first($options);
        $labelText = $options[$firstKey];
        $selectedStr = (string) $firstKey;
    }
    if ($variant === 'hero') {
        $triggerClasses = 'min-h-[42px] w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-800 shadow-sm flex items-center justify-between gap-1 text-left focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20';
    } else {
        $triggerClasses = $size === 'sm'
            ? 'min-h-8 w-full rounded-md border border-slate-200 bg-white px-1.5 py-0.5 text-xs font-normal text-slate-800 shadow-sm flex items-center justify-between gap-1 text-left focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20'
            : 'min-h-[38px] w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm font-normal text-slate-800 shadow-sm flex items-center justify-between gap-1 text-left focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20';
    }
    $panelClasses = 'absolute left-0 z-[100] mt-1 max-h-60 min-w-full overflow-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg ring-1 ring-slate-900/5';
    $optBase = $size === 'sm'
        ? 'cursor-pointer px-2 py-1.5 text-xs text-slate-800 hover:bg-emerald-50'
        : 'cursor-pointer px-3 py-2 text-sm text-slate-800 hover:bg-emerald-50';
@endphp
<div {{ $attributes->merge(['class' => 'relative inline-block max-w-full align-bottom']) }} data-als-custom-select data-submit-on-change="{{ $submitOnChange ? '1' : '0' }}">
    <input type="hidden" name="{{ $name }}" value="{{ $selectedStr }}" data-als-cs-input @if($id) id="{{ $id }}" @endif>
    <button type="button" class="{{ $triggerClasses }}" data-als-cs-trigger aria-expanded="false" aria-haspopup="listbox" @if($id) aria-labelledby="{{ $id }}-label" @endif>
        <span class="min-w-0 flex-1 truncate" data-als-cs-label @if($id) id="{{ $id }}-label" @endif>{{ $labelText }}</span>
        <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <ul class="{{ $panelClasses }} hidden" data-als-cs-panel role="listbox" tabindex="-1">
        @foreach ($options as $val => $lab)
            @php $isSel = (string) $val === $selectedStr; @endphp
            <li role="option" aria-selected="{{ $isSel ? 'true' : 'false' }}" class="{{ $optBase }} {{ $isSel ? 'bg-emerald-50 font-medium text-emerald-900' : '' }}" data-als-cs-option data-value="{{ $val }}">{{ $lab }}</li>
        @endforeach
    </ul>
</div>
