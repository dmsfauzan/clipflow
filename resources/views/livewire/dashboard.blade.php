<div wire:poll.10s class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-950">Clipflow — AI Clip Studio</h2>
            <p class="mt-1 text-sm text-slate-500">Pantau pipeline, review klip, dan jadwal upload dalam satu tempat.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($paused)
                <span class="badge bg-amber-50 text-amber-800 ring-1 ring-amber-200">Antrean dijeda</span>
            @else
                <span class="badge bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200">Antrean aktif</span>
            @endif
            <span class="badge bg-slate-100 text-slate-600 ring-1 ring-slate-200">{{ $timezone ?? 'Asia/Jakarta' }}</span>
        </div>
    </div>

    @if ($demoSourceCount > 0)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-violet-200 bg-violet-50 px-5 py-4">
            <div class="flex items-start gap-3">
                <span class="badge bg-violet-100 text-violet-800 ring-1 ring-violet-200">Mode Demo</span>
                <p class="text-sm text-violet-900">{{ $demoSourceCount }} sumber demo dan {{ $demoReviewCount }} klip demo tersedia. Data demo <strong>tidak dihitung</strong> pada statistik di bawah.</p>
            </div>
            <a href="{{ route('sources.index') }}" class="text-xs font-bold text-violet-800 underline">Lihat sumber demo →</a>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Sumber video" :value="$sourceCount" icon="▣" hint="Data nyata, tanpa demo">
            <a href="{{ route('sources.index') }}" class="mt-4 inline-flex text-xs font-bold text-emerald-800 hover:underline">Kelola sumber →</a>
        </x-stat-card>
        <x-stat-card label="Menunggu review" :value="$reviewCount" icon="✓" hint="Klip hasil render yang belum diputuskan">
            <a href="{{ route('review.index') }}" class="mt-4 inline-flex text-xs font-bold text-emerald-800 hover:underline">Buka review →</a>
        </x-stat-card>
        <x-stat-card label="Klip terjadwal" :value="$scheduledCount" icon="◷" hint="Sudah approved dan punya slot upload">
            <a href="{{ route('schedule.index') }}" class="mt-4 inline-flex text-xs font-bold text-emerald-800 hover:underline">Lihat jadwal →</a>
        </x-stat-card>
        <x-stat-card label="Upload hari ini" :value="$uploadsToday" icon="↑" :hint="'API YouTube menerima video, dihitung berdasarkan tanggal Asia/Jakarta. Batas harian: '.$dailyLimit">
            <p class="mt-4 text-xs font-semibold text-slate-400">{{ $pendingApprovalCount }} klip approved menunggu dijadwalkan</p>
        </x-stat-card>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
                <div><h3 class="font-bold text-slate-900">Klip yang perlu direview</h3><p class="mt-0.5 text-xs text-slate-400">Approval adalah syarat wajib upload.</p></div>
                <a href="{{ route('review.index') }}" class="btn-secondary !px-3 !py-2 text-xs">Semua</a>
            </div>
            @if ($reviewClips->isEmpty())
                <div class="px-6 py-12 text-center">
                    <p class="text-sm font-semibold text-slate-500">Belum ada klip yang menunggu review.</p>
                    <p class="mt-1 text-xs text-slate-400">Unggah video sumber, lalu pipeline akan menyiapkan klip secara otomatis.</p>
                    <a href="{{ route('sources.create') }}" class="btn-secondary mt-4 text-xs">Tambah sumber video</a>
                </div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($reviewClips as $clip)
                        <a href="{{ route('review.show', $clip) }}" class="flex items-center gap-4 px-5 py-4 transition hover:bg-slate-50 sm:px-6">
                            <div class="flex h-14 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-900 text-[10px] font-bold text-white">9:16</div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold text-slate-800">{{ $clip->title }}</p>
                                <p class="mt-0.5 truncate text-xs text-slate-400">{{ $clip->videoSource->original_name }} · {{ number_format((float) $clip->duration_seconds, 0) }} detik</p>
                            </div>
                            @if ($clip->ai_score !== null)
                                <span class="hidden text-right text-xs text-slate-400 sm:block" title="Heuristik AI, bukan prediksi pasti viral">Skor AI<br><span class="font-bold text-slate-700">{{ number_format((float) $clip->ai_score, 0) }}</span></span>
                            @endif
                            <span class="text-slate-300">→</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="font-bold text-slate-900">Jadwal upload berikutnya</h3>
                <p class="mt-0.5 text-xs text-slate-400">{{ $timezone ?? 'Asia/Jakarta' }}</p>
            </div>
            @if ($upcomingJobs->isEmpty())
                <div class="px-5 py-10 text-center text-sm text-slate-400">Belum ada jadwal upload.</div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($upcomingJobs as $job)
                        <div class="px-5 py-3.5">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-bold text-slate-800">{{ $job->scheduled_for->timezone($timezone ?? 'Asia/Jakarta')->isoFormat('D MMM, HH:mm') }}</p>
                                <x-status-badge :label="$job->status->label()" :tone="$job->status->value === 'uploading' ? 'sky' : 'emerald'" />
                            </div>
                            <p class="mt-1 truncate text-xs text-slate-400">{{ $job->clip?->title }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
            <div class="border-t border-slate-100 px-5 py-3"><a href="{{ route('schedule.index') }}" class="text-xs font-bold text-emerald-800 hover:underline">Kelola jadwal →</a></div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card card-pad lg:col-span-2">
            <div class="flex items-start justify-between gap-4">
                <div><h3 class="font-bold text-slate-900">Status worker & pipeline</h3><p class="mt-0.5 text-xs text-slate-400">Dihitung dari job queue, status sumber, dan heartbeat scheduler/worker. Status ini bukan klaim bahwa worker selalu hidup.</p></div>
                <div class="flex flex-col items-end gap-2">
                    <span class="badge {{ $heartbeatHealthy ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : 'bg-amber-50 text-amber-800 ring-1 ring-amber-200' }}">Scheduler {{ $heartbeatHealthy ? 'aktif' : 'belum terbaru' }}</span>
                    <span class="badge {{ ($workerHealthy || $workerBusy || ! $workerInactive) ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : 'bg-rose-50 text-rose-700 ring-1 ring-rose-200' }}">Worker {{ $workerBusy ? 'aktif · memproses job' : ($workerInactive ? 'tidak aktif' : ($workerHealthy ? 'aktif' : 'idle')) }}</span>
                </div>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-bold uppercase text-slate-400">Queue menunggu</p><p class="mt-1 text-xl font-extrabold">{{ $queuePending }}</p></div>
                <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-bold uppercase text-slate-400">Sedang di-reserve</p><p class="mt-1 text-xl font-extrabold">{{ $queueReserved }}</p></div>
                <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-bold uppercase text-slate-400">Job gagal</p><p class="mt-1 text-xl font-extrabold {{ $queueFailed > 0 ? 'text-rose-600' : '' }}">{{ $queueFailed }}</p></div>
                <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-bold uppercase text-slate-400">Sumber diproses</p><p class="mt-1 text-xl font-extrabold">{{ $processingSources }}</p></div>
                <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-bold uppercase text-slate-400">Klip dirender</p><p class="mt-1 text-xl font-extrabold">{{ $renderingClips }}</p></div>
                <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-bold uppercase text-slate-400">Klip gagal</p><p class="mt-1 text-xl font-extrabold {{ $failedClips > 0 ? 'text-rose-600' : '' }}">{{ $failedClips }}</p></div>
            </div>
            <p class="mt-4 text-xs text-slate-400">
                @if ($heartbeatAt)
                    Heartbeat scheduler terakhir: {{ $heartbeatAt->diffForHumans() }}.
                @else
                    Heartbeat scheduler belum tersedia. Jalankan <code class="rounded bg-slate-100 px-1 py-0.5">php artisan schedule:work</code>.
                @endif
                @if ($workerBusy)
                    Worker pipeline sedang memproses {{ $reservedJobs }} job{{ $workerHeartbeatAt ? ' (heartbeat '.$workerHeartbeatAt->diffForHumans().')' : '' }}.
                @elseif ($workerInactive)
                    <span class="font-semibold text-rose-600">Worker pipeline tidak aktif</span> padahal ada {{ $queueBacklog }} job menunggu. Jalankan
                    <code class="rounded bg-slate-100 px-1 py-0.5">powershell -File .\scripts\restart-clipflow.ps1</code>.
                @elseif ($workerHealthy)
                    Worker pipeline aktif{{ $workerHeartbeatAt ? ' ('.$workerHeartbeatAt->diffForHumans().')' : '' }}.
                @else
                    Worker pipeline idle — tidak ada job menunggu.
                @endif
            </p>
        </div>

        <div class="card card-pad">
            <h3 class="font-bold text-slate-900">Channel & publikasi</h3>
            @if ($channels->isEmpty())
                <p class="mt-2 text-sm text-slate-500">Belum ada channel. Hubungkan channel resmi lewat OAuth.</p>
                <a href="{{ route('channels.youtube') }}" class="btn-secondary mt-4 w-full text-xs">Hubungkan YouTube</a>
            @else
                <div class="mt-4 space-y-3">
                    @foreach ($channels as $channel)
                        <div class="flex items-center gap-3 rounded-xl border border-slate-100 p-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white">YT</div>
                            <div class="min-w-0 flex-1"><p class="truncate text-sm font-bold">{{ $channel->channel_title ?: $channel->name }}</p><p class="text-xs text-slate-400">{{ $channel->isConnected() ? 'Terhubung' : 'Belum terhubung' }}</p></div>
                            <span class="h-2 w-2 rounded-full {{ $channel->isConnected() ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 rounded-xl bg-slate-50 p-3 text-xs text-slate-500">
                    Auto-schedule: <strong class="text-slate-800">{{ $autoSchedule ? 'aktif' : 'nonaktif' }}</strong><br>
                    Publish otomatis tetap tunduk pada approval, kuota, dan status channel.
                </div>
            @endif
        </div>
    </div>
</div>
