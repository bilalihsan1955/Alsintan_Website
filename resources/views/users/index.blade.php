<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengguna — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['DM Sans', 'ui-sans-serif', 'system-ui'] } } } };</script>
</head>
<body class="min-h-full bg-slate-50 font-sans text-slate-900 antialiased">
    @include('partials.alsintan-nav', ['active' => 'users'])

    <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
        <header class="mb-6">
            <p class="text-sm font-medium text-emerald-700">Admin</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Kelola pengguna</h1>
            <p class="mt-2 text-sm text-slate-600">Tambah akun operator atau admin lain. Email dipakai untuk login web dan aplikasi mobile.</p>
        </header>

        @if (session('status'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-sm font-semibold text-slate-900">Tambah pengguna</h2>
            <form method="post" action="{{ route('admin.users.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                @csrf
                <label class="block sm:col-span-1">
                    <span class="text-xs font-medium text-slate-600">Nama</span>
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="120" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                </label>
                <label class="block sm:col-span-1">
                    <span class="text-xs font-medium text-slate-600">Email</span>
                    <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                </label>
                <label class="block sm:col-span-1">
                    <span class="text-xs font-medium text-slate-600">Telepon (opsional)</span>
                    <input type="text" name="phone" value="{{ old('phone') }}" maxlength="32" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                </label>
                <label class="block sm:col-span-1">
                    <span class="text-xs font-medium text-slate-600">Password awal</span>
                    <input type="password" name="password" required minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                </label>
                <label class="block sm:col-span-2">
                    <span class="text-xs font-medium text-slate-600">Peran</span>
                    <select name="role" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 sm:max-w-xs">
                        <option value="operator" @selected(old('role') === 'operator')>Operator</option>
                        <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                    </select>
                </label>
                <div class="sm:col-span-2 flex justify-end">
                    <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Simpan pengguna</button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
                <h2 class="text-sm font-semibold text-slate-900">Daftar pengguna</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50/80 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Peran</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($users as $u)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $u->name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $u->email }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $u->role === 'admin' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800' }}">
                                        {{ $u->role === 'admin' ? 'Admin' : 'Operator' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.users.edit', $u) }}" class="font-semibold text-emerald-700 hover:text-emerald-900">Edit</a>
                                    @if ((int) $u->id !== (int) auth()->id())
                                        <form method="post" action="{{ route('admin.users.destroy', $u) }}" class="inline" onsubmit="return confirm('Hapus pengguna {{ $u->email }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ml-3 font-semibold text-rose-600 hover:text-rose-800">Hapus</button>
                                        </form>
                                    @else
                                        <span class="ml-3 text-xs text-slate-400">(Anda)</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">Belum ada pengguna.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($users->hasPages())
                <div class="border-t border-slate-100 px-4 py-3">{{ $users->links() }}</div>
            @endif
        </section>
    </div>
</body>
</html>
