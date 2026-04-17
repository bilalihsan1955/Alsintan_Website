@props(['variant' => 'neutral'])

@php
    $classes = match ($variant) {
        'enter' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        'exit' => 'bg-rose-50 text-rose-800 border-rose-200',
        'grade-a' => 'bg-emerald-50 text-emerald-900 border-emerald-200',
        'grade-b' => 'bg-sky-50 text-sky-900 border-sky-200',
        'grade-c' => 'bg-amber-50 text-amber-900 border-amber-200',
        'grade-d' => 'bg-rose-50 text-rose-900 border-rose-200',
        'sev-high' => 'bg-red-50 text-red-800 border-red-200',
        'sev-medium' => 'bg-amber-50 text-amber-900 border-amber-200',
        'sev-low' => 'bg-slate-100 text-slate-700 border-slate-200',
        'st-open' => 'bg-orange-50 text-orange-900 border-orange-200',
        'st-resolved' => 'bg-emerald-50 text-emerald-900 border-emerald-200',
        'ut-high' => 'bg-violet-50 text-violet-900 border-violet-200',
        'ut-mid' => 'bg-sky-50 text-sky-900 border-sky-200',
        'ut-low' => 'bg-slate-100 text-slate-700 border-slate-200',
        'mp-done' => 'bg-emerald-50 text-emerald-900 border-emerald-200',
        'mp-pending' => 'bg-amber-50 text-amber-900 border-amber-200',
        'mp-overdue' => 'bg-red-50 text-red-900 border-red-200',
        'health' => 'bg-cyan-50 text-cyan-900 border-cyan-200',
        'neutral' => 'bg-slate-100 text-slate-700 border-slate-200',
        default => 'bg-slate-100 text-slate-700 border-slate-200',
    };
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide '.$classes]) }}>
    {{ $slot }}
</span>
