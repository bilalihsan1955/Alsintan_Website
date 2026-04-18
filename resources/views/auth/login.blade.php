<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html, body { -webkit-tap-highlight-color: transparent; }
        .als-bg {
            background:
                radial-gradient(1200px 600px at 100% -10%, rgba(16,185,129,0.12), transparent 60%),
                radial-gradient(900px 500px at -10% 110%, rgba(56,189,248,0.10), transparent 60%),
                #f8fafc;
        }
        @media (prefers-color-scheme: dark) {
            body.theme-dark-supported .als-bg {
                background:
                    radial-gradient(1200px 600px at 100% -10%, rgba(16,185,129,0.18), transparent 60%),
                    radial-gradient(900px 500px at -10% 110%, rgba(56,189,248,0.14), transparent 60%),
                    #0f172a;
            }
        }
    </style>
</head>
<body class="als-bg h-full min-h-screen antialiased">
    <main class="flex min-h-screen items-center justify-center p-4 sm:p-6">
        <div class="w-full max-w-md">
            <div class="mb-6 flex flex-col items-center text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-600 text-lg font-bold text-white shadow-lg shadow-emerald-600/20">A</div>
                <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">Masuk ke Alsintan</h1>
                <p class="mt-1 text-sm text-slate-600">Pantau alat &amp; operasional kelompok tani Anda.</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <form method="POST" action="{{ route('login.submit') }}" class="space-y-4" novalidate>
                    @csrf

                    @if ($errors->any())
                        <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <label class="block">
                        <span class="text-xs font-medium text-slate-600">Email</span>
                        <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                            class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                    </label>

                    <label class="block">
                        <span class="text-xs font-medium text-slate-600">Password</span>
                        <div class="relative mt-1">
                            <input id="password" type="password" name="password" required autocomplete="current-password"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 pr-10 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                            <button type="button" aria-label="Tampilkan password" id="toggle-pw"
                                class="absolute inset-y-0 right-2 inline-flex w-8 items-center justify-center rounded text-slate-400 hover:text-slate-700">
                                <svg id="pw-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.644C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </button>
                        </div>
                    </label>

                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span>Ingat saya di perangkat ini</span>
                    </label>

                    <button type="submit"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                        Masuk
                    </button>
                </form>
            </div>

            <p class="mt-4 text-center text-xs text-slate-500">
                Akun hanya dibuat oleh admin. Butuh akses? Hubungi pengelola.
            </p>
        </div>
    </main>

    <script>
        (function () {
            var btn = document.getElementById('toggle-pw');
            var inp = document.getElementById('password');
            if (!btn || !inp) return;
            btn.addEventListener('click', function () {
                inp.type = inp.type === 'password' ? 'text' : 'password';
            });
        })();
    </script>
</body>
</html>
