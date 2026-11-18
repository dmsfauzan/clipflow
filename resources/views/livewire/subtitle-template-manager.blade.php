@php
    $positionClass = match ($position) {
        'top' => 'items-start pt-3',
        'center' => 'items-center',
        default => 'items-end pb-3',
    };
    $previewSize = max(10, (int) round($fontSize / 4));
    $hasBox = $backgroundColor !== '' && $backgroundOpacity > 0;
    $boxColor = 'transparent';
    if ($hasBox) {
        $r = hexdec(substr($backgroundColor, 1, 2));
        $g = hexdec(substr($backgroundColor, 3, 2));
        $b = hexdec(substr($backgroundColor, 5, 2));
        $boxColor = sprintf('rgba(%d,%d,%d,%.2f)', $r, $g, $b, $backgroundOpacity / 100);
    }
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><h2 class="text-2xl font-extrabold tracking-tight text-slate-950">Template subtitle</h2><p class="mt-1 text-sm text-slate-500">Atur posisi, warna, background, dan ukuran teks yang dibakar ke video. Template default dipakai saat render.</p></div>
        <a href="{{ route('settings.index') }}" class="btn-secondary">← Pengaturan</a>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
        <form wire:submit="save" class="card card-pad space-y-5">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-slate-900">{{ $editingId ? 'Edit template' : 'Template baru' }}</h3>
                <button type="button" wire:click="newTemplate" class="btn-secondary !px-3 !py-1.5 text-xs">+ Baru</button>
            </div>

            <div>
                <p class="label">Preset cepat</p>
                <div class="flex flex-wrap gap-2">
                    @foreach (['minimal' => 'Minimal', 'bold' => 'Bold Creator', 'highlight' => 'Highlight', 'podcast' => 'Podcast'] as $presetKey => $presetLabel)
                        <button type="button" wire:click="applyPreset('{{ $presetKey }}')" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-emerald-300 hover:bg-emerald-50">{{ $presetLabel }}</button>
                    @endforeach
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="label" for="tpl-name">Nama template</label><input id="tpl-name" wire:model.live.debounce.250ms="name" class="input">@error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="tpl-font">Font</label><input id="tpl-font" wire:model.live.debounce.250ms="font" class="input" placeholder="Arial">@error('font')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="tpl-size">Ukuran (px)</label><input id="tpl-size" type="number" min="10" max="72" wire:model.live.debounce.250ms="fontSize" class="input">@error('fontSize')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="tpl-position">Posisi</label><select id="tpl-position" wire:model.live="position" class="input"><option value="bottom">Bawah (dalam video)</option><option value="center">Tengah</option><option value="top">Atas (dalam video)</option></select></div>
                <label class="flex items-end gap-2 pb-2 text-xs font-semibold text-slate-500"><input type="checkbox" wire:model.live="bold" class="rounded border-slate-300 text-emerald-900 focus:ring-emerald-500"> Teks tebal</label>
                <div><label class="label" for="tpl-text-color">Warna teks</label><div class="flex gap-2"><input id="tpl-text-color" type="color" wire:model.live="textColor" class="h-10 w-12 rounded-lg border border-slate-200"><input wire:model.live.debounce.250ms="textColor" class="input"></div>@error('textColor')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="tpl-outline-color">Warna outline</label><div class="flex gap-2"><input id="tpl-outline-color" type="color" wire:model.live="outlineColor" class="h-10 w-12 rounded-lg border border-slate-200"><input wire:model.live.debounce.250ms="outlineColor" class="input"></div>@error('outlineColor')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="tpl-outline">Tebal outline (px)</label><input id="tpl-outline" type="number" min="0" max="10" wire:model.live.debounce.250ms="outlineWidth" class="input">@error('outlineWidth')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="tpl-shadow">Shadow (px)</label><input id="tpl-shadow" type="number" min="0" max="10" wire:model.live.debounce.250ms="shadow" class="input">@error('shadow')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                <div class="sm:col-span-2"><label class="label" for="tpl-bg">Warna background (kosongkan untuk transparan)</label><div class="flex gap-2"><input id="tpl-bg" type="color" wire:model.live="backgroundColor" class="h-10 w-12 rounded-lg border border-slate-200"><input wire:model.live.debounce.250ms="backgroundColor" class="input" placeholder="#14532D atau kosong"></div>@error('backgroundColor')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="tpl-bg-opacity">Opasitas background (%)</label><input id="tpl-bg-opacity" type="number" min="0" max="100" wire:model.live.debounce.250ms="backgroundOpacity" class="input">@error('backgroundOpacity')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="tpl-margin-x">Margin samping (px)</label><input id="tpl-margin-x" type="number" min="0" max="300" wire:model.live.debounce.250ms="marginX" class="input">@error('marginX')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="tpl-margin-y">Jarak dari tepi video (px)</label><input id="tpl-margin-y" type="number" min="0" max="400" wire:model.live.debounce.250ms="marginY" class="input">@error('marginY')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="tpl-chars">Maks karakter/baris</label><input id="tpl-chars" type="number" min="10" max="120" wire:model.live.debounce.250ms="maxCharsPerLine" class="input">@error('maxCharsPerLine')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="tpl-lines">Maks baris</label><input id="tpl-lines" type="number" min="1" max="6" wire:model.live.debounce.250ms="maxLines" class="input">@error('maxLines')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
            </div>

            <label class="flex items-center gap-2 text-xs font-semibold text-slate-600"><input type="checkbox" wire:model="makeDefault" class="rounded border-slate-300 text-emerald-900 focus:ring-emerald-500"> Jadikan template default</label>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                <p class="text-[11px] text-slate-400">Perubahan berlaku untuk render berikutnya.</p>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">Simpan template</button>
            </div>
        </form>

        <div class="space-y-5">
            <div class="card card-pad">
                <h3 class="font-bold text-slate-900">Pratinjau</h3>
                <p class="mt-0.5 text-xs text-slate-400">Perkiraan posisi dan gaya teks di dalam video.</p>
                <div class="mx-auto mt-4 flex aspect-[9/16] w-44 flex-col overflow-hidden rounded-xl bg-slate-800 {{ $positionClass }}">
                    <div class="mx-auto w-[88%] text-center" style="font-family: {{ $font }}; font-size: {{ $previewSize }}px; color: {{ $textColor }}; font-weight: {{ $bold ? '700' : '400' }}; -webkit-text-stroke: {{ max(0.5, $outlineWidth / 2) }}px {{ $outlineColor }}; background: {{ $boxColor }}; padding: {{ $hasBox ? '4px 6px' : '0' }}; border-radius: 4px; line-height: 1.25;">Contoh subtitle di video</div>
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4"><h3 class="font-bold text-slate-900">Daftar template</h3><p class="mt-0.5 text-xs text-slate-400">{{ $templates->count() }} template</p></div>
                <div class="divide-y divide-slate-100">
                    @foreach ($templates as $template)
                        <div class="px-5 py-3.5">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-slate-800">{{ $template->name }} @if ($template->is_default)<span class="badge ml-1 bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200">Default</span>@endif</p>
                                    <p class="mt-0.5 text-[11px] text-slate-400">{{ $template->font }} · {{ $template->font_size }}px · {{ $template->position }} · {{ $template->text_color }}@if ($template->background_color) · box {{ $template->background_opacity }}%@endif</p>
                                </div>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button wire:click="edit({{ $template->id }})" class="text-xs font-bold text-emerald-800 hover:underline">Edit</button>
                                @if (! $template->is_default)<button wire:click="setDefault({{ $template->id }})" class="text-xs font-bold text-slate-600 hover:underline">Jadikan default</button>@endif
                                <button wire:click="duplicate({{ $template->id }})" class="text-xs font-bold text-slate-600 hover:underline">Duplikat</button>
                                <button wire:click="delete({{ $template->id }})" wire:confirm="Hapus template ini?" class="text-xs font-bold text-rose-600 hover:underline">Hapus</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
