<div wire:poll.15s class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><h2 class="text-2xl font-extrabold tracking-tight text-slate-950">Review klip</h2><p class="mt-1 text-sm text-slate-500">Tonton hasil render, edit judul/caption, lalu setujui atau tolak. Hanya klip yang disetujui yang boleh diunggah.</p></div>
        <a href="{{ route('schedule.index') }}" class="btn-secondary">Lihat jadwal →</a>
    </div>

    <div class="card card-pad">
        <div class="grid gap-3 md:grid-cols-[1fr_200px_auto]">
            <div><label class="label" for="review-search">Cari klip</label><input id="review-search" wire:model.live.debounce.350ms="search" class="input" placeholder="Judul atau caption..."></div>
            <div><label class="label" for="review-status">Status</label><select id="review-status" wire:model.live="status" class="input"><option value="all">Semua status</option>@foreach ($statuses as $clipStatus)<option value="{{ $clipStatus->value }}">{{ $clipStatus->label() }}</option>@endforeach</select></div>
            <label class="flex items-end gap-2 pb-2 text-xs font-semibold text-slate-500"><input type="checkbox" wire:model.live="includeDemo" class="rounded border-slate-300 text-emerald-900 focus:ring-emerald-500"> Tampilkan demo</label>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/70 px-5 py-4 sm:px-6">
            <div>
                <h3 class="font-bold text-slate-900">Approval batch</h3>
                <p class="mt-0.5 text-xs text-slate-400">{{ count($selectedIds) }} klip dipilih. Hanya klip terpilih yang diproses.</p>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <div><label class="label" for="batch-channel">Channel</label><select id="batch-channel" wire:model="channelId" class="input !w-48 !py-2"><option value="">Pilih channel</option>@foreach ($channels as $channel)<option value="{{ $channel->id }}">{{ $channel->channel_title ?: $channel->name }}{{ $channel->isConnected() ? '' : ' (belum terhubung)' }}</option>@endforeach</select></div>
                <div><label class="label" for="batch-privacy">Privasi</label><select id="batch-privacy" wire:model="privacy" class="input !w-32 !py-2"><option value="private">Private</option><option value="unlisted">Unlisted</option><option value="public">Public</option></select></div>
                <label class="flex items-center gap-2 pb-2 text-xs font-semibold text-slate-500"><input type="checkbox" wire:model="madeForKids" class="rounded border-slate-300 text-emerald-900 focus:ring-emerald-500"> Untuk anak</label>
                <button wire:click="approveSelected" wire:loading.attr="disabled" class="btn-primary !py-2 text-xs" @disabled(count($selectedIds) === 0)>Setujui terpilih</button>
                <button wire:click="clearSelection" class="btn-secondary !py-2 text-xs" @disabled(count($selectedIds) === 0)>Bersihkan</button>
            </div>
        </div>
        @if ($channels->isEmpty())
            <div class="border-b border-amber-200 bg-amber-50 px-5 py-3 text-xs text-amber-800">Approval memerlukan channel YouTube yang terhubung. <a href="{{ route('channels.youtube') }}" class="font-bold underline">Hubungkan channel dulu →</a></div>
        @endif
        <div class="border-b border-slate-100 bg-white px-5 py-2 text-xs text-slate-400 sm:px-6">
            @if ($autoSchedule)
                Auto-schedule aktif: klip yang disetujui langsung mencari slot upload berikutnya.
            @else
                Auto-schedule nonaktif: approval tidak langsung mengunggah. Jadwalkan dari halaman detail klip.
            @endif
        </div>
        @error('selectedIds')<div class="border-b border-rose-200 bg-rose-50 px-5 py-3 text-xs text-rose-700">{{ $message }}</div>@enderror
        @error('channelId')<div class="border-b border-rose-200 bg-rose-50 px-5 py-3 text-xs text-rose-700">{{ $message }}</div>@enderror
    </div>

    @if ($clips->isEmpty())
        <x-empty-state title="Tidak ada klip pada filter ini" description="Ubah status atau kata kunci pencarian, atau unggah sumber baru untuk membuat klip." icon="✓">
            <x-slot:actions><a href="{{ route('sources.create') }}" class="btn-secondary">Tambah sumber video</a></x-slot:actions>
        </x-empty-state>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($clips as $clip)
                <div class="card overflow-hidden">
                    <div class="relative flex aspect-video items-center justify-center bg-slate-950">
                        @if ($clip->thumbnail_path)
                            <img src="{{ route('media.clip.thumbnail', $clip) }}" alt="" class="h-full w-full object-cover opacity-90">
                        @elseif ($clip->videoSource->is_demo)
                            <span class="badge bg-violet-500/20 text-violet-200 ring-1 ring-violet-400/30">Demo · tanpa file</span>
                        @else
                            <span class="text-4xl text-white/20">▶</span>
                        @endif
                        <div class="absolute left-3 top-3 flex items-center gap-2">
                            <input type="checkbox" wire:model="selectedIds" value="{{ $clip->id }}" class="h-4 w-4 rounded border-white/50 bg-white/80 text-emerald-900 focus:ring-emerald-500" aria-label="Pilih {{ $clip->title }}">
                            <x-status-badge :label="$clip->statusLabel()" :tone="match($clip->status->value) { 'review' => 'amber', 'approved', 'scheduled', 'uploaded' => 'emerald', 'rejected', 'failed' => 'rose', default => 'sky' }" />
                        </div>
                        @if ($clip->duration_seconds)
                            <span class="absolute bottom-3 right-3 rounded bg-black/70 px-2 py-1 text-[10px] font-bold text-white">{{ number_format((float) $clip->duration_seconds) }} dtk</span>
                        @endif
                    </div>
                    <div class="p-5">
                        <a href="{{ route('review.show', $clip) }}" class="line-clamp-2 font-bold text-slate-900 hover:text-emerald-800">{{ $clip->title }}</a>
                        <p class="mt-1 truncate text-xs text-slate-400">{{ $clip->videoSource->original_name }}</p>
                        <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
                            <span class="font-mono">{{ number_format((float) $clip->start_seconds, 0) }}s → {{ number_format((float) $clip->end_seconds, 0) }}s</span>
                            @if ($clip->ai_score !== null)<span title="Skor heuristik AI, bukan prediksi pasti viral">Skor AI <strong class="text-slate-700">{{ number_format((float) $clip->ai_score, 0) }}</strong></span>@endif
                        </div>
                        @if ($clip->failure_message)<p class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ \Illuminate\Support\Str::limit($clip->failure_message, 120) }}</p>@endif
                        <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4">
                            <span class="text-xs text-slate-400">{{ $clip->created_at->diffForHumans() }}</span>
                            <a href="{{ route('review.show', $clip) }}" class="text-xs font-bold text-emerald-800 hover:underline">Buka review →</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div>{{ $clips->links() }}</div>
    @endif
</div>
