<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola perangkat — {{ config('app.name', 'Laravel') }}</title>
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
</head>
<body class="min-h-full bg-slate-50 text-slate-900 antialiased">
    @include('partials.alsintan-nav', ['active' => 'tractors'])

    <div class="als-page-content mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <header class="mb-8">
            <p class="text-sm font-medium text-emerald-700">Perangkat</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Kelola perangkat</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-600">
                Daftar alat yang terhubung. Ubah nama tampilan jika perlu; kode alat dari perangkat tidak berubah di sini.
            </p>
        </header>

        @if (session('status'))
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($tractors->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-12 text-center shadow-sm">
                <p class="text-lg font-semibold text-slate-800">Belum ada perangkat</p>
                <p class="mt-2 text-sm text-slate-600">Saat alat mulai mengirim data, entri akan muncul otomatis di daftar ini.</p>
                <a href="{{ route('dashboard') }}" class="mt-6 inline-flex rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Ke monitoring</a>
            </div>
        @else
            <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm ring-1 ring-slate-100/80">
                <div class="border-b border-slate-100 px-5 py-4 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Daftar perangkat</h2>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $tractors->count() }} perangkat terdaftar</p>
                    </div>
                    <a href="{{ route('dashboard') }}" class="mt-3 inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-800 shadow-sm hover:bg-slate-50 sm:mt-0">
                        Buka monitoring
                    </a>
                </div>

                <ul class="divide-y divide-slate-100" role="list">
                    @foreach ($tractors as $t)
                        @php
                            $lastAny = $t->last_telemetry_at ? \Carbon\Carbon::parse($t->last_telemetry_at) : null;
                        @endphp
                        <li class="px-5 py-5 sm:px-6">
                            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                        <span class="font-mono text-base font-semibold text-slate-900">{{ $t->id }}</span>
                                        @if ($t->name)
                                            <span class="text-sm text-slate-500">· {{ $t->name }}</span>
                                        @endif
                                    </div>
                                    <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-xs text-slate-600">
                                        <div>
                                            <dt class="font-medium text-slate-500">Data telemetri</dt>
                                            <dd class="tabular-nums text-slate-800">{{ number_format($t->telemetry_logs_count) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="font-medium text-slate-500">Telemetri terakhir</dt>
                                            <dd>{{ $lastAny ? $lastAny->timezone(config('app.timezone'))->diffForHumans() : '—' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="font-medium text-slate-500">Posisi terbaru</dt>
                                            <dd class="font-mono text-xs text-slate-700">
                                                @if ($t->latestPosition)
                                                    {{ number_format((float) $t->latestPosition->lat, 5) }}, {{ number_format((float) $t->latestPosition->lng, 5) }}
                                                @else
                                                    —
                                                @endif
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                                <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-end lg:w-auto lg:min-w-[320px]">
                                    <form method="post" action="{{ route('tractors.update', $t) }}" class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-end">
                                        @csrf
                                        @method('PATCH')
                                        <div class="min-w-0 flex-1">
                                            <label for="name-{{ $t->id }}" class="mb-1 block text-xs font-medium text-slate-500">Nama tampilan</label>
                                            <input type="text" id="name-{{ $t->id }}" name="name" value="{{ old('name', $t->name) }}"
                                                placeholder="Contoh: Traktor Ladang A"
                                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <label for="plate-{{ $t->id }}" class="mb-1 block text-xs font-medium text-slate-500">Plat</label>
                                            <input type="text" id="plate-{{ $t->id }}" name="plate_number" value="{{ old('plate_number', $t->plate_number) }}"
                                                placeholder="Contoh: AB-1001-TK"
                                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                                        </div>
                                        <button type="submit" class="shrink-0 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                                            Simpan
                                        </button>
                                    </form>
                                    <a href="{{ route('dashboard', ['tractor' => $t->id]) }}"
                                        class="inline-flex shrink-0 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-900 hover:bg-emerald-100">
                                        Monitor
                                    </a>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</body>
</html>
