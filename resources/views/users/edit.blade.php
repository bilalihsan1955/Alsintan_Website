<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit pengguna — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['DM Sans', 'ui-sans-serif', 'system-ui'] } } } };</script>
</head>
<body class="min-h-full bg-slate-50 font-sans text-slate-900 antialiased">
    @include('partials.alsintan-nav', ['active' => 'users'])

    <div class="mx-auto max-w-lg px-4 py-6 sm:px-6 lg:px-8">
        <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-900">← Kembali ke daftar</a>

        <header class="mt-4 mb-6">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Edit pengguna</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $editUser->email }}</p>
        </header>

        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <form method="post" action="{{ route('admin.users.update', $editUser) }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <label class="block">
                    <span class="text-xs font-medium text-slate-600">Nama</span>
                    <input type="text" name="name" value="{{ old('name', $editUser->name) }}" required maxlength="120" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600">Email</span>
                    <input type="email" name="email" value="{{ old('email', $editUser->email) }}" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600">Telepon (opsional)</span>
                    <input type="text" name="phone" value="{{ old('phone', $editUser->phone) }}" maxlength="32" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600">Password baru</span>
                    <input type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Kosongkan jika tidak diubah" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600">Peran</span>
                    <select name="role" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                        <option value="operator" @selected(old('role', $editUser->role) === 'operator')>Operator</option>
                        <option value="admin" @selected(old('role', $editUser->role) === 'admin')>Admin</option>
                    </select>
                </label>

                <div class="flex justify-end gap-2 pt-2">
                    <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Batal</a>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Simpan</button>
                </div>
            </form>
        </section>
    </div>
</body>
</html>
