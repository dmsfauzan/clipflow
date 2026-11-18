<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'clipflow. — AI Clip Studio')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/clipflow-mark.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <div x-data="{ open: false }" class="min-h-screen lg:flex">
        <div x-show="open" x-transition.opacity class="fixed inset-0 z-30 bg-slate-950/40 lg:hidden" @click="open = false"></div>

        <aside :class="open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'" class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:static">
            <div class="flex h-20 items-center gap-3 border-b border-slate-100 px-6">
                <img src="{{ asset('images/clipflow-mark.svg') }}" alt="clipflow" class="h-9 w-9">
                <div>
                    <p class="text-xl font-extrabold tracking-tight text-slate-950">clipflow<span class="text-lime-600">.</span></p>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-emerald-700">AI Clip Studio</p>
                </div>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-6">
                <p class="mb-3 px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Workspace</p>
                @php
                    $navItems = [
                        ['route' => 'dashboard', 'label' => 'Overview', 'icon' => '⌂'],
                        ['route' => 'sources.index', 'label' => 'Sumber Video', 'icon' => '▣'],
                        ['route' => 'review.index', 'label' => 'Review Klip', 'icon' => '✓'],
                        ['route' => 'schedule.index', 'label' => 'Jadwal Upload', 'icon' => '◷'],
                    ];
                @endphp
                @foreach ($navItems as $item)
                    <a href="{{ route($item['route']) }}" @click="open = false" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ request()->routeIs($item['route'].'*') || ($item['route'] === 'dashboard' && request()->routeIs('dashboard')) ? 'bg-emerald-50 text-emerald-900' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' }}">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg {{ request()->routeIs($item['route'].'*') || ($item['route'] === 'dashboard' && request()->routeIs('dashboard')) ? 'bg-emerald-900 text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-slate-200' }}">{{ $item['icon'] }}</span>
                        {{ $item['label'] }}
                    </a>
                @endforeach

                <p class="mb-3 mt-8 px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Pengaturan</p>
                @php
                    $secondaryItems = [
                        ['route' => 'channels.youtube', 'label' => 'Channel YouTube', 'icon' => '▶'],
                        ['route' => 'activity.index', 'label' => 'Aktivitas', 'icon' => '≡'],
                        ['route' => 'failed-jobs.index', 'label' => 'Job Gagal', 'icon' => '!'],
                        ['route' => 'subtitle-templates.index', 'label' => 'Template Subtitle', 'icon' => 'T'],
                        ['route' => 'settings.index', 'label' => 'Pengaturan', 'icon' => '⚙'],
                    ];
                @endphp
                @foreach ($secondaryItems as $item)
                    <a href="{{ route($item['route']) }}" @click="open = false" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ request()->routeIs($item['route'].'*') ? 'bg-emerald-50 text-emerald-900' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' }}">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500 group-hover:bg-slate-200">{{ $item['icon'] }}</span>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="border-t border-slate-100 p-4">
                <div class="mb-3 rounded-2xl bg-emerald-950 p-4 text-white">
                    <div class="mb-2 flex items-center justify-between"><span class="text-xs font-semibold text-emerald-200">Batas upload harian</span><span class="rounded-full bg-emerald-800 px-2 py-0.5 text-[10px] font-bold">30</span></div>
                    <p class="text-xs leading-relaxed text-emerald-100/75">Target adalah batas maksimum konfigurasi, bukan jaminan volume upload.</p>
                </div>
                <div class="flex items-center gap-3 rounded-xl p-2">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-900">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</div>
                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</p><p class="truncate text-xs text-slate-400">{{ auth()->user()->email }}</p></div>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button title="Keluar" class="text-slate-400 hover:text-rose-600">↪</button></form>
                </div>
            </div>
        </aside>

        <main class="min-w-0 flex-1">
            <header class="sticky top-0 z-20 flex h-20 items-center justify-between border-b border-slate-200/80 bg-slate-50/90 px-4 backdrop-blur sm:px-8 lg:px-10">
                <div class="flex items-center gap-3"><button @click="open = true" class="rounded-xl border border-slate-200 bg-white p-2.5 text-slate-600 lg:hidden">☰</button><div><p class="text-sm font-medium text-slate-400">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p><h1 class="text-xl font-bold tracking-tight text-slate-950">@yield('heading', 'Overview')</h1></div></div>
                <div class="flex items-center gap-3">@if (session('success'))<div class="hidden rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-800 sm:block">{{ session('success') }}</div>@endif<a href="{{ route('sources.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-emerald-900 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-800"><span class="text-lg leading-none">+</span><span class="hidden sm:inline">Tambah sumber</span></a></div>
            </header>
            @if (session('error'))<div class="mx-4 mt-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 sm:mx-8 lg:mx-10">{{ session('error') }}</div>@endif
            <div class="p-4 sm:p-8 lg:p-10">@yield('content')</div>
        </main>
    </div>
    <div id="toast" class="pointer-events-none fixed bottom-5 right-5 z-50 hidden max-w-sm rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white shadow-xl"></div>
    @livewireScripts
    <script>
        window.addEventListener('toast', (event) => { const toast = document.getElementById('toast'); toast.textContent = event.detail?.message ?? ''; toast.classList.remove('hidden'); setTimeout(() => toast.classList.add('hidden'), 4000); });
    </script>
</body>
</html>
