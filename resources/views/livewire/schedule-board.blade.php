<div wire:poll.10s class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><h2 class="text-2xl font-extrabold tracking-tight text-slate-950">Jadwal upload</h2><p class="mt-1 text-sm text-slate-500">Slot {{ $slotStart }}–{{ $slotEnd }} {{ $timezone }} · interval {{ app(\App\Services\SettingsService::class)->integer('publishing.slot_interval_minutes') }} menit · batas {{ $dailyLimit }} upload/hari.</p></div>
        <div class="flex flex-wrap gap-2">
            @if ($paused)
                <button wire:click="resume" wire:loading.attr="disabled" class="btn-primary">▶ Lanjutkan antrean</button>
            @else
                <button wire:click="pause" wire:loading.attr="disabled" class="btn-secondary">⏸ Jeda antrean</button>
            @endif
            <button wire:click="$toggle('showAll')" class="btn-secondary">{{ $showAll ? 'Sembunyikan riwayat' : 'Tampilkan semua status' }}</button>
        </div>
    </div>

    @if ($paused)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900"><strong>Antrean dijeda.</strong> Tidak ada upload baru yang dimulai. Job yang sudah terlanjur berjalan akan diselesaikan atau ditandai perlu pemeriksaan jika koneksi terputus.</div>
    @endif

    @if ($needsAttention > 0)
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-900"><strong>{{ $needsAttention }} publikasi perlu pemeriksaan.</strong> Jangan unggah ulang sebelum memeriksa status sesi. Gunakan tombol “Periksa status” agar tidak membuat video duplikat.</div>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="card card-pad"><p class="label">Upload hari ini</p><p class="mt-2 text-3xl font-extrabold">{{ $uploadedToday }}<span class="text-base font-semibold text-slate-400">/{{ $dailyLimit }}</span></p><p class="mt-1 text-xs text-slate-400">Berdasarkan waktu {{ $timezone }}, status uploaded.</p></div>
        <div class="card card-pad"><p class="label">Antrean</p><p class="mt-2 text-3xl font-extrabold">{{ $paused ? 'Dijeda' : 'Aktif' }}</p><p class="mt-1 text-xs text-slate-400">Scheduler memeriksa setiap menit.</p></div>
        <div class="card card-pad"><p class="label">Zona waktu</p><p class="mt-2 text-3xl font-extrabold">{{ $timezone }}</p><p class="mt-1 text-xs text-slate-400">Semua slot dan laporan harian memakai zona ini.</p></div>
    </div>

    @if ($jobs->isEmpty())
        <x-empty-state title="Belum ada jadwal" description="Setujui klip terlebih dahulu, lalu jadwalkan ke channel YouTube. Slot hanya dibuat pada waktu mendatang." icon="◷">
            <x-slot:actions><a href="{{ route('review.index') }}" class="btn-primary">Review klip</a></x-slot:actions>
        </x-empty-state>
    @else
        <div class="space-y-5">
            @foreach ($jobs->groupBy(fn ($job) => $job->scheduled_for->timezone($timezone)->toDateString()) as $date => $dayJobs)
                <div class="card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-5 py-3.5">
                        <div class="flex items-center gap-3"><h3 class="font-bold text-slate-900">{{ \Illuminate\Support\Carbon::parse($date)->locale('id')->isoFormat('dddd, D MMMM Y') }}</h3>@if ($date === $today->toDateString())<span class="badge bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200">Hari ini</span>@endif</div>
                        <span class="text-xs font-semibold text-slate-400">{{ $dayJobs->count() }} job</span>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach ($dayJobs as $job)
                            <div class="flex flex-wrap items-center gap-4 px-5 py-4">
                                <div class="w-16 shrink-0"><p class="text-sm font-extrabold text-slate-900">{{ $job->scheduled_for->timezone($timezone)->format('H:i') }}</p><p class="text-[10px] text-slate-400">{{ strtoupper($job->privacy->value) }}</p></div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-bold text-slate-800">{{ $job->clip?->title ?? '(klip dihapus)' }}</p>
                                    <p class="mt-0.5 truncate text-xs text-slate-400">{{ $job->publishingChannel?->name }} · {{ $job->made_for_kids ? 'Dibuat untuk anak' : 'Bukan untuk anak' }}</p>
                                    @if ($job->last_error)<p class="mt-1 text-xs text-amber-700">{{ \Illuminate\Support\Str::limit($job->last_error, 160) }}</p>@endif
                                    @error('cancel-'.$job->id)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    @error('reconcile-'.$job->id)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    @error('resume-'.$job->id)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    @error('retry-'.$job->id)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                </div>
                                <x-status-badge :label="$job->status->label()" :tone="match($job->status->value) { 'uploaded' => 'emerald', 'uploading' => 'sky', 'failed', 'needs_reconciliation' => 'rose', 'cancelled' => 'slate', default => 'amber' }" />
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($job->status->value === 'scheduled')
                                        <button wire:click="cancel({{ $job->id }})" wire:confirm="Batalkan jadwal ini dan kembalikan klip ke review?" class="btn-secondary !py-1.5 text-xs">Batalkan</button>
                                    @elseif ($job->status->value === 'needs_reconciliation')
                                        <button wire:click="reconcile({{ $job->id }})" wire:loading.attr="disabled" class="btn-secondary !py-1.5 text-xs">Periksa status</button>
                                        <button wire:click="resumeUpload({{ $job->id }})" wire:confirm="Lanjutkan sesi resumable yang sama? Jangan lakukan jika Anda sudah mengunggah video ini secara manual." class="btn-primary !py-1.5 text-xs">Lanjutkan upload</button>
                                    @elseif ($job->status->value === 'failed' && in_array($job->error_category, ['network', 'unknown'], true))
                                        <button wire:click="retryFailed({{ $job->id }})" wire:confirm="Coba ulang upload yang gagal? Pastikan error sudah diperbaiki." class="btn-secondary !py-1.5 text-xs">Coba lagi</button>
                                    @elseif ($job->status->value === 'uploaded' && $job->youtube_video_id)
                                        <a href="https://youtu.be/{{ $job->youtube_video_id }}" target="_blank" rel="noopener" class="text-xs font-bold text-emerald-800 hover:underline">Buka YouTube ↗</a>
                                    @endif
                                    @if ($job->clip)<a href="{{ route('review.show', $job->clip) }}" class="text-xs font-bold text-slate-500 hover:text-emerald-800">Detail</a>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="card card-pad">
        <h3 class="font-bold text-slate-900">Catatan status</h3>
        <div class="mt-3 grid gap-3 text-xs text-slate-500 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl bg-slate-50 p-3"><strong class="text-slate-800">scheduled</strong><br>Slot sudah dipesan, belum ada upload.</div>
            <div class="rounded-xl bg-slate-50 p-3"><strong class="text-slate-800">uploading</strong><br>Sesi upload sedang berjalan.</div>
            <div class="rounded-xl bg-slate-50 p-3"><strong class="text-slate-800">uploaded</strong><br>API mengembalikan ID video. Bukan jaminan publik.</div>
            <div class="rounded-xl bg-slate-50 p-3"><strong class="text-slate-800">needs_reconciliation</strong><br>Status tidak pasti; jangan unggah ulang tanpa pemeriksaan.</div>
        </div>
    </div>
</div>
