<?php

namespace App\Livewire;

use App\Models\SubtitleTemplate;
use App\Services\SubtitleTemplateService;
use Illuminate\View\View;

class SubtitleTemplateManager extends AuthenticatedComponent
{
    public ?int $editingId = null;

    public string $name = 'Template Baru';

    public string $font = 'Arial';

    public int $fontSize = 30;

    public string $textColor = '#FFFFFF';

    public string $outlineColor = '#000000';

    public int $outlineWidth = 2;

    public int $shadow = 0;

    public string $backgroundColor = '';

    public int $backgroundOpacity = 0;

    public string $position = 'bottom';

    public int $marginX = 30;

    public int $marginY = 40;

    public int $maxCharsPerLine = 42;

    public int $maxLines = 2;

    public bool $bold = false;

    public bool $makeDefault = true;

    public function mount(): void
    {
        $this->fillFrom(app(SubtitleTemplateService::class)->active());
    }

    public function newTemplate(): void
    {
        $this->editingId = null;
        $this->fillFrom(new SubtitleTemplate(SubtitleTemplate::defaults()));
        $this->name = 'Template Baru';
        $this->makeDefault = false;
    }

    public function edit(int $id): void
    {
        $template = SubtitleTemplate::query()->findOrFail($id);

        $this->editingId = $template->id;
        $this->fillFrom($template);
        $this->makeDefault = $template->is_default;
    }

    public function applyPreset(string $preset): void
    {
        $presets = [
            'minimal' => [
                'name' => 'Minimal',
                'font_size' => 28,
                'bold' => false,
                'outline_width' => 2,
                'shadow' => 0,
                'background_color' => null,
                'background_opacity' => 0,
                'position' => 'bottom',
                'margin_y' => 40,
                'max_chars_per_line' => 44,
                'max_lines' => 2,
            ],
            'bold' => [
                'name' => 'Bold Creator',
                'font_size' => 38,
                'bold' => true,
                'outline_width' => 3,
                'shadow' => 1,
                'background_color' => null,
                'background_opacity' => 0,
                'position' => 'bottom',
                'margin_y' => 40,
                'max_chars_per_line' => 34,
                'max_lines' => 2,
            ],
            'highlight' => [
                'name' => 'Highlight',
                'font_size' => 32,
                'bold' => true,
                'outline_width' => 6,
                'shadow' => 0,
                'background_color' => '#14532D',
                'background_opacity' => 85,
                'position' => 'bottom',
                'margin_y' => 30,
                'max_chars_per_line' => 34,
                'max_lines' => 2,
            ],
            'podcast' => [
                'name' => 'Podcast',
                'font_size' => 26,
                'bold' => false,
                'outline_width' => 4,
                'shadow' => 0,
                'background_color' => '#000000',
                'background_opacity' => 60,
                'position' => 'bottom',
                'margin_y' => 30,
                'max_chars_per_line' => 52,
                'max_lines' => 2,
            ],
        ];

        if (! isset($presets[$preset])) {
            return;
        }

        $this->editingId = null;
        $this->fillFrom(new SubtitleTemplate(array_merge(SubtitleTemplate::defaults(), $presets[$preset])));
        $this->makeDefault = false;
        $this->dispatch('toast', message: 'Preset diterapkan. Klik Simpan template untuk menyimpan.');
    }

    public function save(SubtitleTemplateService $service): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'font' => ['required', 'string', 'max:100'],
            'fontSize' => ['required', 'integer', 'min:10', 'max:72'],
            'textColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'outlineColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'outlineWidth' => ['required', 'integer', 'min:0', 'max:10'],
            'shadow' => ['required', 'integer', 'min:0', 'max:10'],
            'backgroundColor' => ['nullable', 'regex:/^(#[0-9A-Fa-f]{6})?$/'],
            'backgroundOpacity' => ['required', 'integer', 'min:0', 'max:100'],
            'position' => ['required', 'in:bottom,center,top'],
            'marginX' => ['required', 'integer', 'min:0', 'max:300'],
            'marginY' => ['required', 'integer', 'min:0', 'max:400'],
            'maxCharsPerLine' => ['required', 'integer', 'min:10', 'max:120'],
            'maxLines' => ['required', 'integer', 'min:1', 'max:6'],
        ], [], [
            'name' => 'Nama template',
            'font' => 'Font',
            'fontSize' => 'Ukuran font',
            'textColor' => 'Warna teks',
            'outlineColor' => 'Warna outline',
            'outlineWidth' => 'Tebal outline',
            'backgroundColor' => 'Warna background',
            'backgroundOpacity' => 'Opasitas background',
            'position' => 'Posisi',
            'marginX' => 'Margin samping',
            'marginY' => 'Margin vertikal',
            'maxCharsPerLine' => 'Maksimal karakter per baris',
            'maxLines' => 'Maksimal baris',
        ]);

        unset($validated['makeDefault']);

        $template = $this->editingId !== null
            ? SubtitleTemplate::query()->findOrFail($this->editingId)
            : new SubtitleTemplate;

        $template->fill($validated);
        $template->background_color = $this->backgroundColor !== '' ? $this->backgroundColor : null;
        $template->save();

        if ($this->makeDefault) {
            $service->makeDefault($template);
        }

        $this->editingId = $template->id;
        $this->dispatch('toast', message: 'Template subtitle disimpan.');
    }

    public function setDefault(int $id, SubtitleTemplateService $service): void
    {
        $service->makeDefault(SubtitleTemplate::query()->findOrFail($id));
        $this->dispatch('toast', message: 'Template default diperbarui.');
    }

    public function duplicate(int $id): void
    {
        $source = SubtitleTemplate::query()->findOrFail($id);
        $copy = $source->replicate();
        $copy->name = $source->name.' (copy)';
        $copy->is_default = false;
        $copy->save();

        $this->edit($copy->id);
        $this->dispatch('toast', message: 'Template diduplikasi.');
    }

    public function delete(int $id, SubtitleTemplateService $service): void
    {
        $template = SubtitleTemplate::query()->findOrFail($id);
        $wasDefault = $template->is_default;
        $template->delete();

        if (SubtitleTemplate::query()->count() === 0) {
            $service->resetToDefault();
        } elseif ($wasDefault) {
            $service->makeDefault(SubtitleTemplate::query()->orderBy('id')->firstOrFail());
        }

        if ($this->editingId === $id) {
            $this->newTemplate();
        }

        $this->dispatch('toast', message: 'Template dihapus.');
    }

    public function render(): View
    {
        return view('livewire.subtitle-template-manager', [
            'templates' => SubtitleTemplate::query()->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    private function fillFrom(SubtitleTemplate $template): void
    {
        $this->name = (string) $template->name;
        $this->font = (string) $template->font;
        $this->fontSize = (int) $template->font_size;
        $this->textColor = (string) $template->text_color;
        $this->outlineColor = (string) $template->outline_color;
        $this->outlineWidth = (int) $template->outline_width;
        $this->shadow = (int) $template->shadow;
        $this->backgroundColor = (string) ($template->background_color ?? '');
        $this->backgroundOpacity = (int) $template->background_opacity;
        $this->position = (string) $template->position;
        $this->marginX = (int) $template->margin_x;
        $this->marginY = (int) $template->margin_y;
        $this->maxCharsPerLine = (int) $template->max_chars_per_line;
        $this->maxLines = (int) $template->max_lines;
        $this->bold = (bool) $template->bold;
    }
}
