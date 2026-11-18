<div wire:poll.8s class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('sources.index') }}" class="text-xs font-bold text-emerald-800 hover:underline">← Semua sumber</a>
            <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-950">{{ $source->original_name }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $source->processing_stage ?? 'Sumber siap diproses' }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-status-badge :label="$source->statusLabel()" :tone="$source->status->value === 'failed' ? 'rose' : ($source->status->isProcessing() ? 'amber' : 'emerald')" />
            @if ($source->status->isProcessing())
                <span class="flex items-center gap-1 text-xs text-slate-400">↻ memperbarui otomatis</span>
            @endif
        </div>
    </div>

    @if ($source->isImport() && $source->isDownloading())
        <div class="card card-pad">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="font-bold text-slate-900">Mengunduh dari link</h3>
                    <p class="mt-1 text-xs text-slate-400">Sumber eksternal: <span class="font-semibold text-slate-600">{{ $source->sourceHost() ?? 'tidak diketahui' }}</span></p>
                </div>
                <span class="badge bg-sky-50 text-sky-800 ring-1 ring-sky-200">{{ $source->import_progress !== null ? $source->import_progress.'%' : 'menunggu' }}</span>
            </div>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-emerald-600 transition-all" style="width: {{ $source->import_progress ?? 4 }}%"></div>
            </div>
            <p class="mt-2 text-xs text-slate-400">{{ $source->processing_stage ?? 'Menunggu worker mengambil antrean unduhan.' }}</p>
        </div>
    @elseif ($source->isImport())
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-4">
            <p class="text-sm text-slate-600">Sumber diimpor dari link eksternal: <span class="font-semibold text-slate-800">{{ $source->sourceHost() ?? 'tidak diketahui' }}</span></p>
            @if ($source->import_completed_at)
                <span class="text-xs text-slate-400">Unduhan selesai {{ $source->import_completed_at->diffForHumans() }}</span>
            @endif
        </div>
    @endif

    @if ($demoMissingFile)
        <div class="flex items-start gap-3 rounded-2xl border border-violet-200 bg-violet-50 px-5 py-4">
            <span class="badge bg-violet-100 text-violet-800 ring-1 ring-violet-200">Mode Demo</span>
            <p class="text-sm text-violet-900">Data demo tidak menyertakan file media. Upload, transkripsi, render, dan upload sungguhan hanya tersedia pada data nyata.</p>
        </div>
    @endif

    @if ($source->failure_message)
        <div class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4">
            <span class="text-lg">!</span>
            <div class="flex-1"><p class="text-sm font-bold text-rose-800">Pemrosesan gagal</p><p class="mt-1 text-sm leading-relaxed text-rose-700">{{ $source->failure_message }}</p></div>
            <button wire:click="retry({{ $source->id }})" wire:loading.attr="disabled" class="btn-secondary !py-2 text-xs">Coba lagi</button>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card card-pad"><p class="label">Status</p><p class="mt-2 text-lg font-extrabold">{{ $source->statusLabel() }}</p><p class="mt-1 text-xs text-slate-400">{{ $source->created_at->diffForHumans() }}</p></div>
        <div class="card card-pad"><p class="label">Durasi</p><p class="mt-2 text-lg font-extrabold">{{ $source->duration_seconds ? number_format((float) $source->duration_seconds / 60, 1).' menit' : 'Belum diketahui' }}</p><p class="mt-1 text-xs text-slate-400">{{ $source->width ? $source->width.'×'.$source->height : 'Menunggu FFprobe' }}</p></div>
        <div class="card card-pad"><p class="label">Format & ukuran</p><p class="mt-2 text-lg font-extrabold">{{ strtoupper($source->extension) }}</p><p class="mt-1 text-xs text-slate-400">{{ \Illuminate\Support\Number::fileSize($source->size_bytes) }}</p></div>
        <div class="card card-pad"><p class="label">Hak penggunaan</p><p class="mt-2 text-lg font-extrabold">✓ Dikonfirmasi</p><p class="mt-1 text-xs text-slate-400">{{ $source->rights_confirmed_at?->format('d M Y H:i') }}</p></div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card card-pad xl:col-span-2">
            <div class="flex items-center justify-between">
                <div><h3 class="font-bold text-slate-900">Klip dari sumber ini</h3><p class="mt-0.5 text-xs text-slate-400">{{ $source->clips->count() }} klip · {{ $reviewCount }} menunggu review</p></div>
                <span class="badge bg-slate-100 text-slate-600 ring-1 ring-slate-200">Skor AI bersifat heuristik</span>
            </div>

            @if ($source->clips->isEmpty())
                <div class="rounded-xl bg-slate-50 px-4 py-10 text-center text-sm text-slate-400">Belum ada klip. Klip dibuat otomatis setelah transkripsi dan seleksi AI selesai.</div>
            @else
                <div class="mt-5 overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-400"><tr><th class="pb-2 pr-3">Klip</th><th class="pb-2 pr-3">Waktu sumber</th><th class="pb-2 pr-3">Durasi</th><th class="pb-2 pr-3">Status</th><th class="pb-2 text-right">Aksi</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($source->clips as $clip)
                                <tr class="hover:bg-slate-50">
                                    <td class="py-3 pr-3"><a href="{{ route('review.show', $clip) }}" class="font-semibold text-slate-800 hover:text-emerald-800">{{ $clip->title }}</a>@if ($clip->reason)<p class="mt-0.5 max-w-sm truncate text-xs text-slate-400">{{ $clip->reason }}</p>@endif</td>
                                    <td class="py-3 pr-3 font-mono text-xs text-slate-500">{{ number_format((float) $clip->start_seconds, 1) }}s → {{ number_format((float) $clip->end_seconds, 1) }}s</td>
                                    <td class="py-3 pr-3">{{ number_format((float) $clip->duration_seconds) }} dtk</td>
                                    <td class="py-3 pr-3"><x-status-badge :label="$clip->statusLabel()" :tone="match($clip->status->value) { 'review' => 'amber', 'approved', 'scheduled', 'uploaded' => 'emerald', 'rejected', 'failed' => 'rose', default => 'sky' }" /></td>
                                    <td class="py-3 text-right"><a href="{{ route('review.show', $clip) }}" class="text-xs font-bold text-emerald-800 hover:underline">Review</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="card card-pad">
            <div class="flex items-center justify-between"><h3 class="font-bold text-slate-900">Template subtitle</h3><a href="{{ route('subtitle-templates.index') }}" class="text-xs font-bold text-emerald-800 hover:underline">Kelola →</a></div>
            <p class="mt-1 text-xs text-slate-400">Berlaku untuk klip sumber ini yang tidak punya template khusus.</p>
            <div class="mt-3 flex gap-2">
                <select wire:model="subtitleTemplateId" class="input">
                    <option value="">Ikuti template default</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}{{ $template->is_default ? ' (default)' : '' }}</option>
                    @endforeach
                </select>
                <button wire:click="saveTemplate" wire:loading.attr="disabled" class="btn-secondary shrink-0">Simpan</button>
            </div>
            @error('subtitleTemplateId')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            <p class="mt-2 text-[11px] text-slate-400">Klip lama tidak otomatis berubah; render ulang dari halaman review.</p>
        </div>

        <div class="card card-pad">
            <div class="flex items-center justify-between"><h3 class="font-bold text-slate-900">Transkripsi</h3>@if ($source->transcript)<span class="text-xs text-slate-400">{{ $segmentCount }} segmen</span>@endif</div>
            @if (! $source->transcript)
                <div class="mt-4 rounded-xl bg-slate-50 px-4 py-8 text-center text-xs text-slate-400">Transkripsi belum tersedia.</div>
            @else
                <div class="mt-4 flex items-center gap-2 text-xs text-slate-500"><span class="badge bg-sky-50 text-sky-800 ring-1 ring-sky-200">Bahasa: {{ $source->transcript->language }}</span><span class="badge bg-slate-100 text-slate-600 ring-1 ring-slate-200">{{ $source->transcript->model }}</span></div>
                <div class="mt-4 max-h-72 space-y-3 overflow-y-auto pr-2">
                    @forelse ($recentSegments as $segment)
                        <div class="rounded-lg bg-slate-50 p-3"><p class="font-mono text-[10px] text-slate-400">{{ number_format($segment['start'], 1) }}s → {{ number_format($segment['end'], 1) }}s</p><p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $segment['text'] }}</p></div>
                    @empty
                        <p class="text-xs text-slate-400">Belum ada segmen.</p>
                    @endforelse
                </div>
                <details class="mt-4 text-xs text-slate-500"><summary class="cursor-pointer font-bold text-emerald-800">Lihat seluruh teks</summary><p class="mt-3 leading-relaxed">{{ $source->transcript->full_text }}</p></details>
            @endif
        </div>
    </div>
</div>
