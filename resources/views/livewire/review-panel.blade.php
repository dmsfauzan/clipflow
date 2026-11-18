<div wire:poll.12s x-data="{ compact: localStorage.getItem('clipflow-review-compact') !== 'false' }" class="space-y-5" :class="compact ? 'review-compact' : ''">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div><a href="{{ route('review.index') }}" class="text-xs font-bold text-emerald-800 hover:underline">← Semua review</a><h2 title="{{ $clip->title }}" class="review-title mt-1 max-w-3xl text-2xl font-extrabold tracking-tight text-slate-950 line-clamp-2">{{ $clip->title }}</h2><p class="review-meta mt-1 text-sm text-slate-500">{{ $clip->videoSource->original_name }} · {{ number_format((float) $clip->duration_seconds, 1) }} detik · sumber {{ number_format((float) $clip->start_seconds, 1) }}s–{{ number_format((float) $clip->end_seconds, 1) }}s</p></div>
        <div class="flex items-center gap-2">
            <button type="button" @click="compact = !compact; localStorage.setItem('clipflow-review-compact', compact ? 'true' : 'false')" class="btn-secondary !px-3 !py-1.5 text-[11px]" title="Ubah kepadatan tampilan">
                <span x-text="compact ? 'Tampilan: Padat' : 'Tampilan: Nyaman'"></span>
            </button>
            <x-status-badge :label="$clip->statusLabel()" :tone="match($clip->status->value) { 'review' => 'amber', 'approved', 'scheduled', 'uploaded' => 'emerald', 'rejected', 'failed' => 'rose', default => 'sky' }" />
        </div>
    </div>

    @if ($clip->videoSource->is_demo && ! $clip->rendered_path)
        <div class="rounded-2xl border border-violet-200 bg-violet-50 px-5 py-4 text-sm text-violet-900"><strong>Mode Demo.</strong> Data demo tidak memiliki MP4 hasil render, jadi preview/download tidak tersedia.</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(340px,0.8fr)]">
        <div class="space-y-5">
            <div class="card overflow-hidden">
                <div class="aspect-video bg-slate-950">
                    @if ($renderedUrl)
                        <video controls preload="metadata" class="h-full w-full object-contain" poster="{{ $thumbnailUrl }}"><source src="{{ $renderedUrl }}" type="video/mp4">Browser tidak mendukung preview video.</video>
                    @else
                        <div class="flex h-full flex-col items-center justify-center gap-3 text-center text-white/60"><span class="text-5xl text-white/20">▶</span><p class="text-sm">Preview tersedia setelah render berhasil.</p><p class="text-xs text-white/40">Status saat ini: {{ $clip->statusLabel() }}</p></div>
                    @endif
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4">
                    <div class="flex gap-2 text-xs text-slate-500"><span class="rounded-lg bg-slate-100 px-2.5 py-1.5 font-semibold">9:16</span><span class="rounded-lg bg-slate-100 px-2.5 py-1.5 font-semibold">{{ $clip->rendered_width ?: 720 }}×{{ $clip->rendered_height ?: 1280 }}</span><span class="rounded-lg bg-slate-100 px-2.5 py-1.5 font-semibold">H.264 + AAC</span></div>
                    @if ($downloadUrl)<a href="{{ $downloadUrl }}" class="btn-secondary !py-2 text-xs">↓ Download MP4</a>@endif
                </div>
            </div>

            <div class="card card-pad">
                <div class="mb-4 flex items-center justify-between"><div><h3 class="review-section font-bold text-slate-900">Konten publikasi</h3><p class="mt-0.5 text-xs text-slate-400">Perubahan setelah approval akan membatalkan approval terkait.</p></div>@if ($clip->ai_score !== null)<span class="badge bg-violet-50 text-violet-800 ring-1 ring-violet-200">Heuristik AI: {{ number_format((float) $clip->ai_score, 0) }}/100</span>@endif</div>
                <div class="space-y-4">
                    <div><label class="label" for="clip-title">Judul</label><input id="clip-title" wire:model="title" class="input"><div class="mt-1 flex justify-between text-[10px] text-slate-400"><span>Gunakan judul yang jelas, tidak menyesatkan.</span><span>{{ mb_strlen($title) }}/200</span></div>@error('title')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                    <div><label class="label" for="clip-caption">Caption</label><textarea id="clip-caption" wire:model="caption" rows="5" class="input resize-y" placeholder="Tulis caption untuk YouTube..." ></textarea><div class="mt-1 text-right text-[10px] text-slate-400">{{ mb_strlen($caption) }}/3000</div>@error('caption')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                    <button wire:click="saveContent" wire:loading.attr="disabled" class="btn-secondary w-full">Simpan perubahan</button>
                </div>
            </div>

            <div class="card card-pad">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="review-section font-bold text-slate-900">Template subtitle</h3>
                        <p class="mt-0.5 text-xs text-slate-400">Template efektif: <strong class="text-slate-700">{{ $effectiveTemplateName }}</strong></p>
                    </div>
                    <a href="{{ route('subtitle-templates.index') }}" class="text-xs font-bold text-emerald-800 hover:underline">Kelola →</a>
                </div>
                <div class="mt-4 flex gap-2">
                    <select wire:model="subtitleTemplateId" class="input">
                        <option value="">Ikuti sumber / default</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }}{{ $template->is_default ? ' (default)' : '' }}</option>
                        @endforeach
                    </select>
                    @if (in_array($clip->status->value, ['review', 'failed'], true))
                        <button wire:click="applyTemplate" wire:loading.attr="disabled" class="btn-secondary shrink-0">Terapkan</button>
                    @endif
                </div>
                @error('subtitleTemplate')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                <p class="mt-2 text-[11px] text-slate-400">“Terapkan” menyimpan pilihan dan menjadwalkan render ulang. Hanya untuk klip review/gagal.</p>
            </div>
        </div>

        <div class="space-y-5">
            <div class="card card-pad">
                <h3 class="review-section font-bold text-slate-900">Keputusan review</h3>
                <p class="mt-1 text-xs leading-relaxed text-slate-400">Pilih channel, privasi, dan deklarasi konten anak sebelum menyetujui. Upload otomatis tetap tidak berjalan tanpa jadwal.</p>
                <div class="mt-5 space-y-4">
                    <div><label class="label" for="channel">Channel tujuan</label><select id="channel" wire:model="channelId" class="input"><option value="">Pilih channel</option>@foreach ($channels as $channel)<option value="{{ $channel->id }}">{{ $channel->channel_title ?: $channel->name }}{{ $channel->isConnected() ? '' : ' · belum terhubung' }}</option>@endforeach</select>@error('channelId')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                    <div><label class="label" for="privacy">Privasi YouTube</label><select id="privacy" wire:model="privacy" class="input"><option value="private">Private (direkomendasikan untuk tes)</option><option value="unlisted">Unlisted</option><option value="public">Public</option></select></div>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3"><input type="checkbox" wire:model="madeForKids" class="mt-0.5 rounded border-slate-300 text-emerald-900 focus:ring-emerald-500"><span class="text-xs leading-relaxed text-slate-600">Saya sudah menentukan deklarasi “dibuat untuk anak” sesuai isi video.</span></label>
                    @error('approve')<p class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ $message }}</p>@enderror
                    @error('rerender')<p class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ $message }}</p>@enderror
                    @if ($clip->status->value === 'review')
                        <button wire:click="approve" wire:loading.attr="disabled" class="btn-primary w-full">✓ Approve klip</button>
                        <button x-data @click="const reason = prompt('Alasan penolakan (opsional):'); $wire.reject(reason)" class="btn-danger w-full">Tolak klip</button>
                        <button wire:click="rerender" wire:loading.attr="disabled" class="btn-secondary w-full">Render ulang subtitle</button>
                    @elseif ($clip->status->value === 'failed')
                        <button wire:click="rerender" wire:loading.attr="disabled" class="btn-secondary w-full">Render ulang klip</button>
                    @elseif ($clip->status->value === 'approved')
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs leading-relaxed text-emerald-800">Klip approved. {{ $autoSchedule ? 'Auto-schedule aktif; slot akan dicari otomatis.' : 'Auto-schedule nonaktif; Anda perlu menjadwalkannya.' }}</div>
                        @if (! $job)<button wire:click="scheduleNow" class="btn-primary w-full">Jadwalkan slot berikutnya</button>@endif
                    @elseif ($clip->status->value === 'scheduled')
                        <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-xs leading-relaxed text-sky-800">Terjadwal pada <strong>{{ $job?->scheduled_for?->timezone(config('clipflow.timezone'))->format('d M Y, H:i') }} WIB</strong>.</div>
                        <button wire:click="cancelSchedule" class="btn-danger w-full">Batalkan jadwal → review</button>
                    @elseif ($clip->status->value === 'rejected')
                        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs leading-relaxed text-rose-800">Klip ditolak. Alasan: {{ $clip->reject_reason ?: 'Tidak ada alasan ditulis.' }}</div>
                        <button wire:click="restore" class="btn-secondary w-full">Kembalikan ke review</button>
                    @elseif ($clip->status->value === 'uploaded')
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs leading-relaxed text-emerald-800">API YouTube sudah mengembalikan ID <strong>{{ $job?->youtube_video_id }}</strong>. Ini bukan klaim video sudah publik atau selesai diproses YouTube.</div>
                    @endif
                </div>
            </div>

            <div class="card card-pad">
                <h3 class="review-section font-bold text-slate-900">Alasan pemilihan AI</h3>
                <p class="review-body mt-3 text-sm leading-relaxed text-slate-600">{{ $clip->reason ?: 'Tidak ada alasan yang dicatat.' }}</p>
                <p class="mt-4 text-[11px] leading-relaxed text-slate-400">Skor dan alasan AI adalah heuristik untuk membantu review, bukan prediksi pasti viral. Isi transkrip diperlakukan sebagai data, bukan instruksi.</p>
            </div>

            @if ($approval)
                <div class="card card-pad">
                    <h3 class="review-section font-bold text-slate-900">Jejak approval</h3>
                    <dl class="review-approval mt-4 space-y-3 text-xs"><div class="flex justify-between gap-4"><dt class="text-slate-400">Disetujui oleh</dt><dd class="text-right font-semibold text-slate-700">{{ $approval->user?->name }}</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-400">Waktu</dt><dd class="text-right font-semibold text-slate-700">{{ $approval->approved_at?->format('d M Y H:i') }}</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-400">Versi</dt><dd class="font-mono text-right text-slate-700">v{{ $approval->approval_version }} · {{ substr($approval->content_hash, 0, 12) }}…</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-400">Privasi</dt><dd class="font-semibold text-slate-700">{{ $approval->privacy->label() }}</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-400">Channel</dt><dd class="text-right font-semibold text-slate-700">{{ $approval->publishingChannel?->name }}</dd></div></dl>
                </div>
            @endif
        </div>
    </div>
</div>
