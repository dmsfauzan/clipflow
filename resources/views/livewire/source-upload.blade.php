<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <div class="flex border-b border-slate-100">
                <button type="button" wire:click="$set('sourceMode', 'upload')" class="flex-1 px-5 py-4 text-sm font-bold transition {{ $sourceMode === 'upload' ? 'border-b-2 border-emerald-900 text-emerald-900' : 'text-slate-400 hover:text-slate-700' }}">⬆ Unggah file</button>
                <button type="button" wire:click="$set('sourceMode', 'url')" class="flex-1 px-5 py-4 text-sm font-bold transition {{ $sourceMode === 'url' ? 'border-b-2 border-emerald-900 text-emerald-900' : 'text-slate-400 hover:text-slate-700' }}">🔗 Impor dari link</button>
            </div>

            <form wire:submit="save" class="card-pad space-y-6"
                  x-data="{ progress: null }"
                  x-on:livewire-upload-start="progress = 0"
                  x-on:livewire-upload-finish="progress = 100"
                  x-on:livewire-upload-error="progress = null"
                  x-on:livewire-upload-progress="progress = $event.detail.progress">

                @if ($sourceMode === 'upload')
                    <div class="rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 p-6 transition hover:border-emerald-400">
                        <div class="flex flex-col items-center gap-4 text-center">
                            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 text-2xl text-emerald-900">▣</div>
                            <div>
                                <p class="text-sm font-bold text-slate-800">Pilih file video</p>
                                <p class="mt-1 text-xs text-slate-500">MP4, MOV, atau WebM · maksimal {{ number_format($maxBytes / 1073741824, 1, ',', '.') }} GB · maksimal {{ round($maxDurationSeconds / 3600, 1) }} jam</p>
                            </div>
                            <input type="file" accept=".mp4,.mov,.webm,video/mp4,video/quicktime,video/webm" wire:model="video" class="block w-full text-sm text-slate-500 file:mr-4 file:rounded-xl file:border-0 file:bg-emerald-900 file:px-5 file:py-3 file:text-sm file:font-bold file:text-white hover:file:bg-emerald-800">
                            @if ($video)
                                <div class="flex w-full items-center gap-3 rounded-xl bg-white px-4 py-3 text-left shadow-sm">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-emerald-900">▶</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-bold text-slate-800">{{ $video->getClientOriginalName() }}</p>
                                        <p class="text-xs text-slate-400">{{ number_format($video->getSize() / 1048576, 1, ',', '.') }} MB</p>
                                    </div>
                                    <button type="button" wire:click="$set('video', null)" class="text-slate-400 hover:text-rose-500" aria-label="Hapus file">✕</button>
                                </div>
                            @endif
                        </div>

                        <div class="mt-4" x-show="progress !== null" x-cloak>
                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-200">
                                <div class="h-full rounded-full bg-emerald-600 transition-all" :style="`width: ${progress ?? 0}%`"></div>
                            </div>
                            <p class="mt-2 text-xs font-semibold text-slate-500">Mengunggah... <span x-text="progress ?? 0"></span>%</p>
                        </div>
                        <div wire:loading wire:target="video" class="mt-4 text-xs font-semibold text-slate-500">Menyiapkan file di browser...</div>
                    </div>

                    @error('video')
                        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</div>
                    @enderror
                @else
                    <div class="space-y-4">
                        <div>
                            <label class="label" for="source-url">Link video</label>
                            <input id="source-url" type="url" wire:model="sourceUrl" placeholder="https://www.youtube.com/watch?v=..." class="input" autocomplete="off">
                            <p class="mt-1 text-xs text-slate-400">Mendukung YouTube dan situs lain yang didukung yt-dlp. Maksimal {{ number_format($maxBytes / 1073741824, 1, ',', '.') }} GB · {{ round($maxDurationSeconds / 3600, 1) }} jam · resolusi maksimal {{ config('clipflow.download.max_height') }}p.</p>
                            @error('sourceUrl')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        @if (! $ytdlpAvailable)
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-relaxed text-amber-900">
                                <strong>yt-dlp belum terdeteksi.</strong> Link tetap bisa dimasukkan, tetapi unduhan akan gagal sampai yt-dlp terpasang dan <code class="rounded bg-amber-100 px-1">CLIPFLOW_YTDLP_BINARY</code> benar. Lihat README bagian instalasi.
                            </div>
                        @endif

                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600">
                            Unduhan berjalan di queue <strong>pipeline</strong>. Pastikan queue worker aktif. Anda akan diarahkan ke halaman detail sumber untuk melihat progres.
                        </div>
                    </div>
                @endif

                <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4">
                    <input type="checkbox" wire:model="rightsConfirmed" class="mt-0.5 rounded border-slate-300 text-emerald-900 focus:ring-emerald-500">
                    <span class="text-sm text-slate-600">Saya mengonfirmasi bahwa saya memiliki hak penggunaan video ini, atau video ini adalah konten saya sendiri. Saya memahami mengunduh video dari platform pihak ketiga dapat melanggar ketentuan layanan mereka.</span>
                </label>
                @error('rightsConfirmed')
                    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</div>
                @enderror

                <div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 pt-5">
                    <p class="text-xs text-slate-400">
                        @if ($sourceMode === 'upload')
                            File disimpan privat. FFprobe akan memeriksa stream video dan audio.
                        @else
                            Link disimpan untuk audit. Query rahasia di-redact dari log.
                        @endif
                    </p>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save,video">
                        <span wire:loading wire:target="save">Memproses...</span>
                        <span wire:loading.remove wire:target="save">{{ $sourceMode === 'upload' ? 'Mulai proses' : 'Mulai unduh' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="space-y-4">
        <div class="card card-pad">
            <h3 class="font-bold text-slate-900">Apa yang terjadi setelah masuk</h3>
            <ol class="mt-4 space-y-3 text-sm text-slate-600">
                <li class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-900 text-[10px] font-bold text-white">1</span> File diunggah, atau video diunduh dari link ke storage privat.</li>
                <li class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-900 text-[10px] font-bold text-white">2</span> FFprobe memeriksa durasi, stream video, dan audio.</li>
                <li class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-900 text-[10px] font-bold text-white">3</span> Audio diekstrak, lalu faster-whisper membuat transkripsi bertimestamp.</li>
                <li class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-900 text-[10px] font-bold text-white">4</span> Ollama memilih kandidat klip 15–60 detik (maksimal 30 per sumber).</li>
                <li class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-900 text-[10px] font-bold text-white">5</span> Setiap klip dirender 9:16 dengan subtitle burn-in, lalu masuk antrean review.</li>
            </ol>
            <p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-xs text-amber-800">Queue worker harus berjalan agar pipeline diproses. Lihat README untuk perintahnya.</p>
        </div>
        <div class="card card-pad">
            <h3 class="font-bold text-slate-900">Hak & privasi</h3>
            <p class="mt-2 text-xs leading-relaxed text-slate-500">Gunakan impor link terutama untuk konten milik Anda sendiri. Semua file media tetap di storage privat dan hanya dapat diakses pemilik yang login.</p>
        </div>
    </div>
</div>
