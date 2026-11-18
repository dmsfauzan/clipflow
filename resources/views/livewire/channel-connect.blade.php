<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><h2 class="text-2xl font-extrabold tracking-tight text-slate-950">Channel YouTube</h2><p class="mt-1 text-sm text-slate-500">Satu channel per workspace, dihubungkan lewat OAuth resmi Google. Token disimpan terenkripsi di server.</p></div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card card-pad lg:col-span-2">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl bg-red-600 text-lg font-bold text-white">
                        @if ($provider['avatar'])<img src="{{ $provider['avatar'] }}" alt="" class="h-full w-full object-cover">@else YT @endif
                    </div>
                    <div>
                        <p class="text-lg font-extrabold text-slate-950">{{ $provider['name'] }}</p>
                        <p class="text-xs text-slate-400">{{ $provider['handle'] !== '' ? $provider['handle'] : ($provider['identifier'] !== '' ? $provider['identifier'] : 'Tanpa channel ID') }}</p>
                    </div>
                </div>
                <x-status-badge :label="$provider['status_label']" :tone="$provider['is_connected'] ? 'emerald' : 'amber'" />
            </div>

            @if (! $oauthConfigured)
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-relaxed text-amber-900">
                    Kredensial Google OAuth belum dikonfigurasi. Isi <code class="rounded bg-amber-100 px-1">GOOGLE_CLIENT_ID</code> dan <code class="rounded bg-amber-100 px-1">GOOGLE_CLIENT_SECRET</code> di <code class="rounded bg-amber-100 px-1">.env</code>, lalu restart server. Status ditampilkan apa adanya: <strong>belum terhubung</strong>.
                </div>
                <button type="button" disabled class="btn-primary mt-5 w-full">Hubungkan channel (belum tersedia)</button>
            @elseif ($provider['is_connected'])
                <div class="mt-6 grid gap-3 text-xs sm:grid-cols-2">
                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-bold uppercase text-slate-400">Channel ID</p><p class="mt-1 font-mono text-slate-700">{{ $provider['identifier'] }}</p></div>
                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-bold uppercase text-slate-400">Token kedaluwarsa</p><p class="mt-1 text-slate-700">{{ $provider['token_expires_at'] ?? 'Tidak diketahui' }}</p></div>
                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-bold uppercase text-slate-400">Terhubung sejak</p><p class="mt-1 text-slate-700">{{ $provider['connected_at'] ?? 'Tidak diketahui' }}</p></div>
                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-bold uppercase text-slate-400">Terakhir refresh token</p><p class="mt-1 text-slate-700">{{ $provider['last_refreshed_at'] ?? 'Belum ada' }}</p></div>
                </div>
                <div class="mt-5">
                    <p class="label">Scope yang diminta</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($scopes as $scope)
                            <span class="badge bg-slate-100 text-slate-600 ring-1 ring-slate-200">{{ str_replace('https://www.googleapis.com/auth/', '', $scope) }}</span>
                        @endforeach
                    </div>
                </div>
                <form method="POST" action="{{ route('channels.youtube.disconnect', $provider['id']) }}" class="mt-6" onsubmit="return confirm('Putuskan koneksi channel ini? Token akan dihapus dari server.');">
                    @csrf
                    <button type="submit" class="btn-danger">Putuskan koneksi</button>
                </form>
            @else
                <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600">
                    Klik tombol di bawah untuk masuk ke halaman OAuth Google. Aplikasi memakai scope minimum untuk upload dan membaca identitas channel. Aplikasi tidak meminta password Google, dan token tidak ditampilkan ke frontend.
                </div>
                <a href="{{ route('channels.youtube.redirect') }}" class="btn-primary mt-5 w-full">Hubungkan channel YouTube</a>
            @endif
        </div>

        <div class="space-y-5">
            <div class="card card-pad">
                <h3 class="font-bold text-slate-900">Keamanan koneksi</h3>
                <ul class="mt-3 space-y-2.5 text-xs leading-relaxed text-slate-600">
                    <li class="flex gap-2"><span class="text-emerald-700">✓</span> State OAuth divalidasi pada callback.</li>
                    <li class="flex gap-2"><span class="text-emerald-700">✓</span> Token disimpan terenkripsi dan disembunyikan dari respons/log.</li>
                    <li class="flex gap-2"><span class="text-emerald-700">✓</span> Refresh token digunakan otomatis sebelum upload.</li>
                    <li class="flex gap-2"><span class="text-emerald-700">✓</span> Putuskan koneksi menghapus token di server.</li>
                </ul>
            </div>
            <div class="card card-pad">
                <h3 class="font-bold text-slate-900">Hanya YouTube pada fase ini</h3>
                <p class="mt-2 text-xs leading-relaxed text-slate-500">Tidak ada tombol palsu untuk TikTok atau Instagram. Adapter lain hanya akan ada jika memang diimplementasikan.</p>
            </div>
        </div>
    </div>
</div>
