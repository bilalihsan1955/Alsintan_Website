@php
    $active = $active ?? 'dashboard';
@endphp
<style>
    .als-nav-track {
        position: relative;
        display: flex;
        width: clamp(19rem, 60vw, 30rem);
        max-width: 100%;
        gap: 0.25rem;
        padding: 0.25rem;
        border-radius: 0.75rem;
        background: rgb(241 245 249 / 0.92);
        backdrop-filter: blur(6px);
        overflow: hidden;
    }
    .als-nav-pill {
        position: absolute;
        top: 0.25rem;
        bottom: 0.25rem;
        left: 0.25rem;
        width: calc(33.333% - 0.3rem);
        border-radius: 0.5rem;
        background: #fff;
        box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.07);
        z-index: 0;
        pointer-events: none;
        transition: transform 0.34s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .als-nav-track[data-active="tractors"] .als-nav-pill { transform: translateX(calc(100% + 0.15rem)); }
    .als-nav-track[data-active="strategic"] .als-nav-pill { transform: translateX(calc(200% + 0.3rem)); }
    .als-nav-link {
        position: relative;
        z-index: 1;
        flex: 1;
        min-width: 0;
        text-align: center;
        border-radius: 0.5rem;
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        font-weight: 500;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: color 0.2s ease;
    }
    .als-nav-link.is-active { color: rgb(6 95 70); }
</style>
<nav class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-sm" aria-label="Navigasi utama">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-lg font-bold tracking-tight text-emerald-900">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-600 text-sm text-white">A</span>
            Alsintan
        </a>
        <div class="als-nav-track" data-active="{{ $active === 'tractors' ? 'tractors' : ($active === 'strategic' ? 'strategic' : 'dashboard') }}">
            <span class="als-nav-pill" aria-hidden="true"></span>
            <a href="{{ route('dashboard') }}" class="als-nav-link {{ $active === 'dashboard' ? 'is-active' : 'text-slate-600' }}">Monitoring</a>
            <a href="{{ route('tractors.manage') }}" class="als-nav-link {{ $active === 'tractors' ? 'is-active' : 'text-slate-600' }}">Kelola perangkat</a>
            <a href="{{ route('strategic') }}" class="als-nav-link {{ $active === 'strategic' ? 'is-active' : 'text-slate-600' }}">Strategic</a>
        </div>
    </div>
</nav>
