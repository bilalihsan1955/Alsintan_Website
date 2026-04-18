@php
    $active = $active ?? 'dashboard';
    $me = auth()->user();
    $isAdmin = $me && ($me->role ?? 'operator') === 'admin';
    $navCount = $isAdmin ? 4 : 3;
    $navActive = match (true) {
        $active === 'tractors' => 'tractors',
        $active === 'strategic' => 'strategic',
        $active === 'users' => 'users',
        default => 'dashboard',
    };
@endphp
<style>
    .als-nav-track {
        position: relative;
        display: flex;
        width: clamp(19rem, 72vw, 40rem);
        max-width: 100%;
        gap: 0.25rem;
        padding: 0.25rem;
        border-radius: 0.75rem;
        background: rgb(241 245 249 / 0.92);
        backdrop-filter: blur(6px);
        overflow: hidden;
    }
    .als-nav-track[data-count="3"] { width: clamp(19rem, 60vw, 30rem); }
    .als-nav-pill {
        position: absolute;
        top: 0.25rem;
        bottom: 0.25rem;
        left: 0.25rem;
        border-radius: 0.5rem;
        background: #fff;
        box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.07);
        z-index: 0;
        pointer-events: none;
        transition: transform 0.34s cubic-bezier(0.22, 1, 0.36, 1);
    }
    /* --- 3 segmen (operator) --- */
    .als-nav-track[data-count="3"] .als-nav-pill {
        width: calc(33.333% - 0.3rem);
    }
    .als-nav-track[data-count="3"][data-active="tractors"] .als-nav-pill { transform: translateX(calc(100% + 0.15rem)); }
    .als-nav-track[data-count="3"][data-active="strategic"] .als-nav-pill { transform: translateX(calc(200% + 0.3rem)); }
    /* --- 4 segmen (admin): Monitoring | Perangkat | Strategic | Pengguna --- */
    .als-nav-track[data-count="4"] .als-nav-pill {
        width: calc(25% - 0.22rem);
    }
    .als-nav-track[data-count="4"][data-active="tractors"] .als-nav-pill { transform: translateX(calc(100% + 0.15rem)); }
    .als-nav-track[data-count="4"][data-active="strategic"] .als-nav-pill { transform: translateX(calc(200% + 0.3rem)); }
    .als-nav-track[data-count="4"][data-active="users"] .als-nav-pill { transform: translateX(calc(300% + 0.45rem)); }

    .als-nav-link {
        position: relative;
        z-index: 1;
        flex: 1;
        min-width: 0;
        text-align: center;
        border-radius: 0.5rem;
        padding: 0.5rem 0.35rem;
        font-size: 0.8125rem;
        font-weight: 500;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: color 0.2s ease;
    }
    @media (min-width: 640px) {
        .als-nav-link { padding: 0.5rem 0.75rem; font-size: 0.875rem; }
    }
    .als-nav-link.is-active { color: rgb(6 95 70); }
    .als-nav-avatar {
        display:inline-flex; align-items:center; gap:.5rem;
        border-radius: 999px; padding: .25rem .75rem .25rem .25rem;
        border:1px solid rgb(226 232 240); background:#fff;
    }
    .als-nav-avatar:hover { background: rgb(248 250 252); }
    .als-nav-avatar .bubble {
        width: 1.75rem; height: 1.75rem; border-radius:999px; background: rgb(16 185 129); color:#fff;
        display:inline-flex; align-items:center; justify-content:center; font-size:.8rem; font-weight:700;
        overflow:hidden;
    }
    .als-nav-avatar .bubble img { width:100%; height:100%; object-fit:cover; }
    .als-nav-role { font-size:.65rem; font-weight:700; letter-spacing:.03em; padding:.05rem .35rem; border-radius:999px; }
    .role-admin { background: rgb(219 234 254); color: rgb(30 64 175); }
    .role-operator { background: rgb(220 252 231); color: rgb(22 101 52); }
</style>
<nav class="fixed left-0 right-0 top-0 z-50 border-b border-slate-200/80 bg-white/95 shadow-sm backdrop-blur-md" aria-label="Navigasi utama">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
        <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-2 text-lg font-bold tracking-tight text-emerald-900">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-600 text-sm text-white">A</span>
            Alsintan
        </a>
        <div class="als-nav-track min-w-0 flex-1 basis-[min(100%,22rem)] justify-center sm:basis-auto sm:flex-initial" data-count="{{ $navCount }}" data-active="{{ $navActive }}">
            <span class="als-nav-pill" aria-hidden="true"></span>
            <a href="{{ route('dashboard') }}" class="als-nav-link {{ $active === 'dashboard' ? 'is-active' : 'text-slate-600' }}">Monitoring</a>
            <a href="{{ route('tractors.manage') }}" class="als-nav-link {{ $active === 'tractors' ? 'is-active' : 'text-slate-600' }}" title="Kelola perangkat">Perangkat</a>
            <a href="{{ route('strategic') }}" class="als-nav-link {{ $active === 'strategic' ? 'is-active' : 'text-slate-600' }}">Strategic</a>
            @if ($isAdmin)
                <a href="{{ route('admin.users.index') }}" class="als-nav-link {{ $active === 'users' ? 'is-active' : 'text-slate-600' }}" title="Kelola pengguna">Pengguna</a>
            @endif
        </div>

        @if ($me)
            <a href="{{ route('profile') }}" class="als-nav-avatar shrink-0" title="Profil">
                <span class="bubble">
                    @if ($me->avatar_url)
                        <img src="{{ $me->avatar_url }}" alt="">
                    @else
                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($me->name ?? '?', 0, 1)) }}
                    @endif
                </span>
                <span class="hidden text-sm font-semibold text-slate-700 sm:inline">{{ \Illuminate\Support\Str::limit($me->name, 14) }}</span>
                <span class="als-nav-role {{ $isAdmin ? 'role-admin' : 'role-operator' }}">{{ $isAdmin ? 'Admin' : 'Operator' }}</span>
            </a>
        @endif
    </div>
</nav>
{{-- Mengisi ruang agar konten tidak tertutup nav fixed --}}
<div class="pointer-events-none h-16 w-full shrink-0" aria-hidden="true"></div>
