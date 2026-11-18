<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><h2 class="text-2xl font-extrabold tracking-tight text-slate-950">Pengaturan pipeline & publikasi</h2><p class="mt-1 text-sm text-slate-500">Perubahan disimpan di database. Nilai default berasal dari <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">config/clipflow.php</code> dan <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">.env</code>.</p></div>
        <button wire:click="save" wire:loading.attr="disabled" class="btn-primary">Simpan pengaturan</button>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="card card-pad">
                <h3 class="font-bold text-slate-900">Publikasi & slot upload</h3>
                <p class="mt-0.5 text-xs text-slate-400">Zona waktu {{ config('clipflow.timezone') }}. Default slot 30 menit dari 08.00 sampai 22.30 WIB.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><label class="label" for="daily-limit">Batas upload harian (1–30)</label><input id="daily-limit" type="number" min="1" max="30" wire:model="dailyLimit" class="input">@error('dailyLimit')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror<p class="mt-1 text-[11px] text-slate-400">Batas maksimum, bukan target yang dijamin tercapai.</p></div>
                    <div><label class="label" for="slot-interval">Interval slot (menit)</label><input id="slot-interval" type="number" min="5" max="240" wire:model="slotInterval" class="input">@error('slotInterval')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                    <div><label class="label" for="slot-start">Jam mulai</label><input id="slot-start" type="time" wire:model="slotStart" class="input">@error('slotStart')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                    <div><label class="label" for="slot-end">Jam akhir</label><input id="slot-end" type="time" wire:model="slotEnd" class="input">@error('slotEnd')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                    <div><label class="label" for="default-privacy">Privasi default</label><select id="default-privacy" wire:model="defaultPrivacy" class="input"><option value="private">Private (default tes)</option><option value="unlisted">Unlisted</option><option value="public">Public</option></select></div>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3"><input type="checkbox" wire:model="autoSchedule" class="mt-0.5 rounded border-slate-300 text-emerald-900 focus:ring-emerald-500"><span class="text-xs leading-relaxed text-slate-600"><strong>Auto-schedule saat approve.</strong> Approval tetap wajib. Jika nonaktif, klip approved harus dijadwalkan manual.</span></label>
                </div>
                <div class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-500">Publikasi otomatis sekarang: <strong class="text-slate-800">{{ $paused ? 'dijeda' : 'aktif' }}</strong>. Jeda/lanjutkan dilakukan dari halaman Jadwal Upload.</div>
            </div>

            <div class="card card-pad">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-bold text-slate-900">Kuota YouTube Data API</h3>
                        <p class="mt-0.5 text-xs text-slate-400">Kuota default 10.000 unit/hari; satu upload memakai 1.600 unit. Batas harian efektif mengikuti nilai terkecil antara batas di atas dan kapasitas kuota.</p>
                    </div>
                    <span class="badge bg-amber-50 text-amber-800 ring-1 ring-amber-200">±{{ $quota['capacity'] }} upload/hari</span>
                </div>
                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    <div><label class="label" for="quota-daily">Kuota harian (unit)</label><input id="quota-daily" type="number" min="0" wire:model="quotaDailyUnits" class="input">@error('quotaDailyUnits')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                    <div><label class="label" for="quota-cost">Biaya per upload (unit)</label><input id="quota-cost" type="number" min="1" wire:model="quotaUploadCost" class="input">@error('quotaUploadCost')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                    <div><label class="label" for="quota-reserve">Reserve (unit)</label><input id="quota-reserve" type="number" min="0" wire:model="quotaReserve" class="input">@error('quotaReserve')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-bold uppercase text-slate-400">Estimasi terpakai hari ini</p><p class="mt-1 text-lg font-extrabold text-slate-900">{{ number_format($quota['used_today'], 0, ',', '.') }} unit</p></div>
                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-bold uppercase text-slate-400">Sisa kuota</p><p class="mt-1 text-lg font-extrabold text-slate-900">{{ number_format($quota['remaining'], 0, ',', '.') }} unit</p></div>
                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-bold uppercase text-slate-400">Kapasitas</p><p class="mt-1 text-lg font-extrabold text-slate-900">{{ $quota['capacity'] }} upload</p></div>
                </div>
                <p class="mt-3 text-[11px] leading-relaxed text-slate-400">Untuk target 30 upload/hari, ajukan peningkatan kuota YouTube Data API melalui Google Cloud Console. Tanpa itu, kapasitas nyata ±6 upload/hari.</p>
            </div>

            <div class="card card-pad">
                <h3 class="font-bold text-slate-900">Transkripsi & seleksi AI</h3>
                <p class="mt-0.5 text-xs text-slate-400">Seleksi klip memakai driver AI yang dipilih. Model dan base URL dapat diganti tanpa mengubah kode.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><label class="label" for="whisper-model">Model Whisper</label><input id="whisper-model" wire:model="whisperModel" class="input" placeholder="small"><p class="mt-1 text-[11px] text-slate-400">tiny, base, small, medium, large-v3. Model besar butuh RAM/VRAM lebih.</p>@error('whisperModel')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                    <div><label class="label" for="ai-driver">Driver AI</label><select id="ai-driver" wire:model.live="aiDriver" class="input"><option value="9router">9router / OpenAI-compatible</option><option value="ollama">Ollama (lokal)</option></select><p class="mt-1 text-[11px] text-slate-400">Pilih 9router untuk gateway; Ollama untuk pemakaian offline.</p>@error('aiDriver')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                    <div class="sm:col-span-2"><label class="label" for="ai-url">Base URL AI</label><div class="flex gap-2"><input id="ai-url" wire:model="aiBaseUrl" class="input" placeholder="http://localhost:20128/v1"><button type="button" wire:click="testAiConnection" wire:loading.attr="disabled" class="btn-secondary shrink-0">Tes koneksi</button></div>@error('aiBaseUrl')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror<p class="mt-1 text-[11px] text-slate-400">Ollama: <code class="rounded bg-slate-100 px-1">http://127.0.0.1:11434</code> · 9router: <code class="rounded bg-slate-100 px-1">http://localhost:20128/v1</code> (sertakan /v1).</p></div>
                    <div><label class="label" for="ai-model">Model AI</label><input id="ai-model" wire:model="aiModel" class="input" placeholder="assist"><p class="mt-1 text-[11px] text-slate-400">Contoh 9router: <code class="rounded bg-slate-100 px-1">assist</code>. Ollama: <code class="rounded bg-slate-100 px-1">llama3.1:8b</code>.</p>@error('aiModel')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                    <div class="rounded-xl border p-3 {{ $ai['driver'] === 'ollama' || $ai['key_configured'] ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}">
                        <p class="text-[10px] font-bold uppercase tracking-wide {{ $ai['driver'] === 'ollama' || $ai['key_configured'] ? 'text-emerald-700' : 'text-amber-700' }}">API key</p>
                        <p class="mt-1 text-xs leading-relaxed {{ $ai['driver'] === 'ollama' || $ai['key_configured'] ? 'text-emerald-900' : 'text-amber-900' }}">
                            @if ($ai['driver'] === 'ollama')
                                Ollama lokal tidak memerlukan API key.
                            @elseif ($ai['key_configured'])
                                Terisi dari <code class="rounded bg-white/70 px-1">CLIPFLOW_AI_API_KEY</code>.
                            @else
                                Belum diisi. Tambahkan <code class="rounded bg-white/70 px-1">CLIPFLOW_AI_API_KEY</code> di <code class="rounded bg-white/70 px-1">.env</code> lalu jalankan <code class="rounded bg-white/70 px-1">php artisan config:clear</code>.
                            @endif
                        </p>
                    </div>
                    <div><label class="label" for="min-clip">Durasi minimal klip (detik)</label><input id="min-clip" type="number" step="1" min="5" max="120" wire:model="minClipSeconds" class="input">@error('minClipSeconds')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                    <div><label class="label" for="max-clip">Durasi maksimal klip (detik)</label><input id="max-clip" type="number" step="1" min="10" max="240" wire:model="maxClipSeconds" class="input">@error('maxClipSeconds')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                    <div><label class="label" for="max-candidates">Maksimal kandidat per sumber</label><input id="max-candidates" type="number" min="1" max="30" wire:model="maxCandidates" class="input">@error('maxCandidates')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                    <div class="grid grid-cols-2 gap-3"><div><label class="label" for="render-w">Lebar render</label><input id="render-w" type="number" min="360" max="1920" wire:model="renderWidth" class="input">@error('renderWidth')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div><div><label class="label" for="render-h">Tinggi render</label><input id="render-h" type="number" min="640" max="1920" wire:model="renderHeight" class="input">@error('renderHeight')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div></div>
                </div>
                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-5">
                    <div>
                        <h4 class="font-bold text-slate-900">Template subtitle</h4>
                        <p class="mt-0.5 text-xs text-slate-400">Aktif: <strong class="text-slate-700">{{ $subtitleTemplate->name }}</strong> · {{ $subtitleTemplate->font_size }}px · posisi {{ $subtitleTemplate->position }} · {{ $subtitleTemplate->text_color }}</p>
                    </div>
                    <a href="{{ route('subtitle-templates.index') }}" class="btn-secondary !py-2 text-xs">Kelola template →</a>
                </div>
            </div>

            <div class="card card-pad">
                <h3 class="font-bold text-slate-900">Retensi file</h3>
                <p class="mt-0.5 text-xs text-slate-400">0 berarti file disimpan selamanya. File yang masih dibutuhkan antrean tidak akan dihapus.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><label class="label" for="retention-source">Retensi video sumber (hari)</label><input id="retention-source" type="number" min="0" max="3650" wire:model="retentionSourceDays" class="input">@error('retentionSourceDays')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                    <div><label class="label" for="retention-clip">Retensi hasil render (hari)</label><input id="retention-clip" type="number" min="0" max="3650" wire:model="retentionClipDays" class="input">@error('retentionClipDays')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card card-pad">
                <h3 class="font-bold text-slate-900">Status tool server</h3>
                <p class="mt-0.5 text-xs text-slate-400">Diperiksa ulang maksimal sekali per menit.</p>
                <ul class="mt-4 space-y-2.5 text-sm">
                    @php
                        $toolRows = [
                            ['FFmpeg', $tools['ffmpeg'], $ffmpegBinary],
                            ['FFprobe', $tools['ffprobe'], $ffprobeBinary],
                            ['Python', $tools['python'], $pythonBinary],
                            ['yt-dlp', $tools['ytdlp'], config('clipflow.download.ytdlp_binary')],
                            ['Script transkripsi', $tools['whisper_script'], 'scripts/transcribe.py'],
                            ['AI: '.$ai['driver'], $ai['reachable'], $ai['endpoint']],
                        ];
                    @endphp
                    @foreach ($toolRows as [$label, $ok, $detail])
                        <li class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full {{ $ok ? 'bg-emerald-500' : 'bg-rose-500' }}"></span><span class="font-semibold text-slate-700">{{ $label }}</span></span>
                            <span class="truncate text-[10px] text-slate-400" title="{{ $detail }}">{{ $ok ? 'siap' : 'tidak ditemukan' }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-4 rounded-xl bg-slate-50 px-3 py-3 text-[11px] leading-relaxed text-slate-500">Jalankan <code class="rounded bg-slate-200/70 px-1">php artisan clipflow:check</code> untuk pemeriksaan lengkap dari terminal.</div>
            </div>

            <div class="card card-pad">
                <h3 class="font-bold text-slate-900">Server & kapasitas</h3>
                <dl class="mt-4 space-y-3 text-xs">
                    <div class="flex justify-between"><dt class="text-slate-400">Penyimpanan bebas</dt><dd class="font-semibold text-slate-700">{{ $storageFree !== null ? \Illuminate\Support\Number::fileSize($storageFree) : 'Tidak diketahui' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Total penyimpanan</dt><dd class="font-semibold text-slate-700">{{ $storageTotal !== null ? \Illuminate\Support\Number::fileSize($storageTotal) : 'Tidak diketahui' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Scheduler heartbeat</dt><dd class="font-semibold text-slate-700">{{ $schedulerHeartbeat ? \Illuminate\Support\Carbon::parse($schedulerHeartbeat)->diffForHumans() : 'Belum ada' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Worker</dt><dd class="text-right font-semibold {{ $reservedJobs > 0 ? 'text-sky-700' : (($pendingJobs > 0 && (! $workerHeartbeat || \Illuminate\Support\Carbon::parse($workerHeartbeat)->lt(now()->subMinutes(5)))) ? 'text-rose-600' : 'text-emerald-700') }}">{{ $reservedJobs > 0 ? 'memproses '.$reservedJobs.' job' : ($pendingJobs > 0 ? $pendingJobs.' job menunggu' : 'idle') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Worker heartbeat</dt><dd class="text-right font-semibold text-slate-700">{{ $workerHeartbeat ? \Illuminate\Support\Carbon::parse($workerHeartbeat)->diffForHumans().($workerHost ? ' · '.$workerHost : '') : 'Belum ada' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">OAuth YouTube</dt><dd class="font-semibold {{ $oauthConfigured ? 'text-emerald-700' : 'text-amber-700' }}">{{ $oauthConfigured ? 'Terkonfigurasi' : 'Belum dikonfigurasi' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Driver AI</dt><dd class="text-right font-semibold text-slate-700">{{ $ai['driver'] }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Model AI</dt><dd class="text-right font-semibold text-slate-700">{{ $ai['model'] }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Endpoint AI</dt><dd class="max-w-[220px] truncate text-right font-semibold text-slate-700" title="{{ $ai['endpoint'] }}">{{ $ai['endpoint'] }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Model tersedia</dt><dd class="text-right font-semibold text-slate-700">{{ count($ai['models']) > 0 ? implode(', ', array_slice($ai['models'], 0, 3)).(count($ai['models']) > 3 ? ' …' : '') : 'Tidak terdeteksi' }}</dd></div>
                </dl>
            </div>

            <div class="card card-pad">
                <h3 class="font-bold text-slate-900">Notifikasi Telegram</h3>
                <p class="mt-0.5 text-xs text-slate-400">Kirim notifikasi saat klip siap review, pipeline gagal, atau upload perlu pemeriksaan.</p>
                <div class="mt-3 flex items-center justify-between gap-3 rounded-xl {{ $telegramConfigured ? 'bg-emerald-50' : 'bg-amber-50' }} px-3 py-2.5">
                    <span class="text-xs font-semibold {{ $telegramConfigured ? 'text-emerald-800' : 'text-amber-800' }}">{{ $telegramConfigured ? 'Terkonfigurasi' : 'Belum dikonfigurasi' }}</span>
                    <button type="button" wire:click="testTelegram" wire:loading.attr="disabled" class="btn-secondary !px-3 !py-1.5 text-xs" @disabled(! $telegramConfigured)>Tes kirim</button>
                </div>
                @error('telegram')<p class="mt-2 text-xs text-rose-600">{{ $message }}</p>@enderror
                <p class="mt-3 text-[11px] leading-relaxed text-slate-500">Konfigurasi via <code class="rounded bg-slate-100 px-1">CLIPFLOW_TELEGRAM_ENABLED</code>, <code class="rounded bg-slate-100 px-1">TELEGRAM_BOT_TOKEN</code>, dan <code class="rounded bg-slate-100 px-1">TELEGRAM_CHAT_ID</code>.</p>
            </div>

            <div class="card card-pad">
                <h3 class="font-bold text-slate-900">Catatan penting</h3>
                <ul class="mt-3 space-y-2 text-[11px] leading-relaxed text-slate-500">
                    <li>• Broadcaster PC lokal harus tetap menyala agar queue worker dan scheduler berjalan.</li>
                    <li>• Target 30 upload/hari dibatasi pasokan klip yang disetujui, waktu render, server, dan kuota YouTube.</li>
                    <li>• Kredensial rahasia hanya di <code class="rounded bg-slate-100 px-1">.env</code> atau penyimpanan terenkripsi.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
