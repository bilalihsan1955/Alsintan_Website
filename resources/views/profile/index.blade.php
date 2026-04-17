<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profil — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 p-6">
    <div class="mx-auto max-w-lg rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <h1 class="text-xl font-bold text-slate-900">Profil</h1>
        <p class="mt-2 text-sm text-slate-600">Halaman profil placeholder. Sesuaikan dengan akun pengguna Anda.</p>
        <a href="{{ route('dashboard') }}" class="mt-6 inline-flex rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Kembali ke Home</a>
    </div>
</body>
</html>
