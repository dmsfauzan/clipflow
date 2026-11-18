<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — clipflow. AI Clip Studio</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/clipflow-mark.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 p-6 text-slate-900">
    <div class="w-full max-w-5xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm lg:grid lg:grid-cols-2">
        <div class="hidden bg-emerald-950 p-10 text-white lg:block">
            <div class="mb-8 flex items-center gap-3">
                <img src="{{ asset('images/clipflow-mark-light.svg') }}" alt="clipflow" class="h-10 w-10">
                <div><p class="text-xl font-extrabold tracking-tight">clipflow<span class="text-lime-300">.</span></p><p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-emerald-300">AI Clip Studio</p></div>
            </div>
            <h1 class="text-3xl font-extrabold leading-tight">Siapkan klip, subtitle, judul, dan caption otomatis. Anda cukup review dan approve.</h1>
            <p class="mt-4 text-sm leading-relaxed text-emerald-100/80">Hanya klip yang Anda setujui yang boleh diunggah otomatis. Akun pemilik dibuat melalui command lokal, tidak ada registrasi publik.</p>
            <ul class="mt-8 space-y-3 text-sm text-emerald-100/90">
                <li class="flex gap-2"><span>✓</span> Transkripsi local dengan faster-whisper</li>
                <li class="flex gap-2"><span>✓</span> Seleksi cuplikan oleh AI (Ollama atau 9router), divalidasi ketat</li>
                <li class="flex gap-2"><span>✓</span> Render vertikal 9:16 privat</li>
                <li class="flex gap-2"><span>✓</span> Upload YouTube resmi lewat OAuth</li>
            </ul>
        </div>
        <div class="p-8 sm:p-12">
            <div class="mb-7 flex items-center gap-3 lg:hidden">
                <img src="{{ asset('images/clipflow-mark.svg') }}" alt="clipflow" class="h-9 w-9">
                <p class="text-xl font-extrabold tracking-tight text-slate-950">clipflow<span class="text-lime-600">.</span></p>
            </div>
            <h2 class="text-2xl font-extrabold tracking-tight">Masuk sebagai pemilik</h2>
            <p class="mt-1 text-sm text-slate-500">Gunakan akun yang dibuat dengan <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">php artisan clipflow:create-owner</code>.</p>
            @error('email')
                <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ $message }}</div>
            @enderror
            <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5">
                @csrf
                <div>
                    <label for="email" class="label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="input" placeholder="owner@clipflow.test">
                </div>
                <div>
                    <label for="password" class="label">Password</label>
                    <input id="password" name="password" type="password" required class="input" placeholder="••••••••">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-emerald-900 focus:ring-emerald-500"> Ingat saya</label>
                <button type="submit" class="btn-primary w-full">Masuk</button>
            </form>
        </div>
    </div>
    @livewireScripts
</body>
</html>
