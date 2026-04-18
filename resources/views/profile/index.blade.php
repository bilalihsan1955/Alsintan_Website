<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profil — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #f8fafc; }
        .card { background: #fff; border: 1px solid rgb(226 232 240); border-radius: 1rem; }
        .label { font-size: .75rem; font-weight: 500; color: rgb(71 85 105); }
        .ctrl { margin-top: .25rem; width: 100%; border-radius: .5rem; border: 1px solid rgb(226 232 240); background: #fff; padding: .5rem .75rem; font-size: .875rem; color: rgb(15 23 42); box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04); }
        .ctrl:focus { outline: none; border-color: rgb(16 185 129); box-shadow: 0 0 0 3px rgb(16 185 129 / 0.18); }
        .btn-primary { display:inline-flex; align-items:center; justify-content:center; border-radius: .5rem; background: rgb(16 185 129); padding: .5rem 1rem; font-size: .875rem; font-weight: 600; color: #fff; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); }
        .btn-primary:hover { background: rgb(5 150 105); }
        .btn-ghost { display:inline-flex; align-items:center; justify-content:center; border-radius: .5rem; border:1px solid rgb(226 232 240); background:#fff; padding: .5rem .75rem; font-size: .875rem; font-weight: 600; color: rgb(51 65 85); }
        .btn-ghost:hover { background: rgb(248 250 252); }
        .badge-role { display:inline-flex; align-items:center; gap:.25rem; font-size:.7rem; font-weight:600; letter-spacing:.02em; padding:.15rem .5rem; border-radius:999px; }
        .role-admin { background: rgb(219 234 254); color: rgb(30 64 175); }
        .role-operator { background: rgb(220 252 231); color: rgb(22 101 52); }
        .seg { display:flex; gap:.25rem; background: rgb(241 245 249); padding:.25rem; border-radius:.6rem; }
        .seg label { flex:1; text-align:center; cursor:pointer; }
        .seg label input { display:none; }
        .seg label span { display:block; padding:.45rem .5rem; border-radius:.45rem; font-size:.8rem; font-weight:600; color:rgb(71 85 105); transition:all .15s ease; }
        .seg label input:checked + span { background:#fff; color: rgb(6 95 70); box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.07); }
        .avatar-ring { background: linear-gradient(135deg, rgb(16 185 129), rgb(56 189 248)); padding: 3px; border-radius: 9999px; }
    </style>
</head>
<body class="min-h-screen antialiased">
    @include('partials.alsintan-nav', ['active' => 'profile'])

    <main class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
        @if (session('ok'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">{{ session('ok') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert">{{ $errors->first() }}</div>
        @endif

        {{-- Identitas & avatar --}}
        <section class="card p-5 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="avatar-ring">
                        <div class="h-16 w-16 overflow-hidden rounded-full bg-white ring-1 ring-slate-200">
                            @if ($user->avatar_url)
                                <img src="{{ $user->avatar_url }}" alt="Foto profil" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-xl font-bold text-slate-500">
                                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name ?? '?', 0, 1)) }}
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="min-w-0">
                        <h1 class="truncate text-lg font-bold text-slate-900">{{ $user->name }}</h1>
                        <p class="truncate text-sm text-slate-500">{{ $user->email }}</p>
                        <span class="badge-role {{ $user->role === 'admin' ? 'role-admin' : 'role-operator' }} mt-1.5">
                            {{ $user->role === 'admin' ? 'Admin' : 'Operator' }}
                        </span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <form method="post" action="{{ route('profile.avatar.upload') }}" enctype="multipart/form-data" id="form-avatar" class="inline">
                        @csrf
                        <label for="avatar" class="btn-ghost cursor-pointer">Ganti foto</label>
                        <input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="document.getElementById('form-avatar').submit()">
                    </form>
                    @if ($user->avatar_url)
                        <form method="post" action="{{ route('profile.avatar.delete') }}" class="inline" onsubmit="return confirm('Hapus foto profil?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-ghost text-rose-700">Hapus</button>
                        </form>
                    @endif
                </div>
            </div>
        </section>

        {{-- Info akun --}}
        <section class="card mt-5 p-5 sm:p-6">
            <h2 class="text-sm font-semibold text-slate-900">Informasi akun</h2>
            <p class="mt-0.5 text-xs text-slate-500">Ubah nama tampilan dan nomor telepon Anda.</p>
            <form method="post" action="{{ route('profile.update') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                @csrf
                @method('PATCH')
                <label class="block sm:col-span-1">
                    <span class="label">Nama</span>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" class="ctrl">
                </label>
                <label class="block sm:col-span-1">
                    <span class="label">Nomor telepon</span>
                    <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="32" placeholder="08xxxxxxxxxx" class="ctrl">
                </label>
                <label class="block sm:col-span-2">
                    <span class="label">Email</span>
                    <input type="email" value="{{ $user->email }}" disabled class="ctrl bg-slate-50 text-slate-500">
                </label>
                <div class="sm:col-span-2 flex justify-end">
                    <button type="submit" class="btn-primary">Simpan perubahan</button>
                </div>
            </form>
        </section>

        {{-- Preferensi --}}
        <section class="card mt-5 p-5 sm:p-6">
            <h2 class="text-sm font-semibold text-slate-900">Preferensi tampilan</h2>
            <p class="mt-0.5 text-xs text-slate-500">Pilihan tema &amp; bahasa akan tersinkron ke aplikasi mobile.</p>
            <form method="post" action="{{ route('profile.preferences') }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <span class="label">Mode tema</span>
                    <div class="seg mt-1">
                        @foreach (['system' => 'Ikuti sistem', 'light' => 'Terang', 'dark' => 'Gelap'] as $v => $lab)
                            <label>
                                <input type="radio" name="theme_mode" value="{{ $v }}" {{ ($preferences['theme_mode'] ?? 'system') === $v ? 'checked' : '' }}>
                                <span>{{ $lab }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <span class="label">Bahasa</span>
                    <div class="seg mt-1">
                        @foreach (['id' => 'Indonesia', 'en' => 'English'] as $v => $lab)
                            <label>
                                <input type="radio" name="language" value="{{ $v }}" {{ ($preferences['language'] ?? 'id') === $v ? 'checked' : '' }}>
                                <span>{{ $lab }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary">Simpan preferensi</button>
                </div>
            </form>
        </section>

        {{-- Keamanan --}}
        <section class="card mt-5 p-5 sm:p-6">
            <h2 class="text-sm font-semibold text-slate-900">Keamanan</h2>
            <p class="mt-0.5 text-xs text-slate-500">Gunakan password minimal 8 karakter yang hanya Anda tahu.</p>
            <form method="post" action="{{ route('profile.password') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                @csrf
                @method('PATCH')
                <label class="block sm:col-span-2">
                    <span class="label">Password lama</span>
                    <input type="password" name="current_password" required class="ctrl">
                </label>
                <label class="block sm:col-span-1">
                    <span class="label">Password baru</span>
                    <input type="password" name="password" required minlength="8" class="ctrl">
                </label>
                <label class="block sm:col-span-1">
                    <span class="label">Konfirmasi password baru</span>
                    <input type="password" name="password_confirmation" required minlength="8" class="ctrl">
                </label>
                <div class="sm:col-span-2 flex justify-end">
                    <button type="submit" class="btn-primary">Ubah password</button>
                </div>
            </form>
        </section>

        {{-- Logout --}}
        <section class="card mt-5 p-5 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Keluar dari akun</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Perangkat ini akan dikeluarkan. Anda perlu masuk kembali.</p>
                </div>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-ghost text-rose-700">Keluar</button>
                </form>
            </div>
        </section>
    </main>

    {{-- Terapkan theme_mode langsung di halaman profil agar preview sesuai. --}}
    <script>
        (function applyTheme(mode) {
            var root = document.documentElement;
            var shouldDark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            root.classList.toggle('dark', shouldDark);
        })({!! json_encode($preferences['theme_mode'] ?? 'system') !!});
    </script>
</body>
</html>
