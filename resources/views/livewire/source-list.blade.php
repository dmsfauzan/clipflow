<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><h2 class="text-2xl font-extrabold tracking-tight text-slate-950">Sumber video</h2><p class="mt-1 text-sm text-slate-500">Video privat yang masuk pipeline transkripsi, seleksi AI, dan render.</p></div>
        <a href="{{ route('sources.create') }}" class="btn-primary">+ Tambah sumber</a>
    </div>

    <div class="card card-pad">
        <div class="grid gap-3 md:grid-cols-[1fr_220px_auto]">
            <div><label class="label" for="source-search">Cari sumber</label><input id="source-search" wire:model.live.debounce.350ms="search" class="input" placeholder="Nama file..."></div>
            <div><label class="label" for="source-status">Status</label><select id="source-status" wire:model.live="status" class="input"><option value="">Semua status</option>@foreach ($statuses as $sourceStatus)<option value="{{ $sourceStatus->value }}">{{ $sourceStatus->label() }}</option>@endforeach</select></div>
            <label class="flex items-end gap-2 pb-2 text-xs font-semibold text-slate-500"><input type="checkbox" wire:model.live="includeDemo" class="rounded border-slate-300 text-emerald-900 focus:ring-emerald-500"> Tampilkan demo</label>
        </div>
    </div>

    @if ($sources->isEmpty())
        <x-empty-state title="Belum ada sumber video" description="Unggah MP4, MOV, atau WebM secara privat untuk memulai pipeline AI." icon="▣">
            <x-slot:actions><a href="{{ route('sources.create') }}" class="btn-primary">Unggah video pertama</a></x-slot:actions>
        </x-empty-state>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($sources as $source)
                <div class="card overflow-hidden transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex h-36 items-center justify-center bg-slate-950 text-white">
                        @if ($source->is_demo)<span class="badge bg-violet-500/20 text-violet-200 ring-1 ring-violet-400/30">Mode Demo · file tidak disertakan</span>@else<span class="text-4xl opacity-40">▣</span>@endif
                    </div>
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><a href="{{ route('sources.show', $source) }}" class="block truncate font-bold text-slate-900 hover:text-emerald-800">{{ $source->original_name }}</a><p class="mt-1 text-xs text-slate-400">{{ \Illuminate\Support\Number::fileSize($source->size_bytes) }} · {{ $source->extension }}</p></div><x-status-badge :label="$source->statusLabel()" :tone="$source->status->value === 'failed' ? 'rose' : ($source->status->isProcessing() ? 'amber' : 'emerald')" /></div>
                        <div class="mt-4 space-y-2"><div class="flex justify-between text-xs text-slate-500"><span>Progress</span><span>{{ $source->clips_count }} klip</span></div><div class="h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-600 transition-all {{ $source->status->value === 'failed' ? 'bg-rose-500' : ($source->status->value === 'ready' ? 'w-full' : 'w-1/2') }}"></div></div></div>
                        @if ($source->processing_stage)<p class="mt-3 text-xs text-slate-500">{{ $source->processing_stage }}</p>@endif
                        @if ($source->failure_message)<p class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ Str::limit($source->failure_message, 160) }}</p>@endif
                        <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4"><span class="text-xs text-slate-400">{{ $source->created_at->diffForHumans() }}</span>@if ($source->status->value === 'failed')<button wire:click="retry({{ $source->id }})" wire:loading.attr="disabled" class="text-xs font-bold text-emerald-800 hover:underline">Coba lagi</button>@else<a href="{{ route('sources.show', $source) }}" class="text-xs font-bold text-emerald-800 hover:underline">Detail →</a>@endif</div>
                    </div>
                </div>
            @endforeach
        </div>
        <div>{{ $sources->links() }}</div>
    @endif
</div>
