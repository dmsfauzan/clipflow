<?php

namespace App\Livewire;

use App\Enums\ClipStatus;
use App\Models\SubtitleTemplate;
use App\Models\VideoSource;
use App\Services\ActivityLogger;
use App\Services\SourceRetryService;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SourceDetail extends AuthenticatedComponent
{
    public VideoSource $source;

    public ?int $subtitleTemplateId = null;

    public function mount(VideoSource $source): void
    {
        abort_unless(auth()->user()?->id === $source->user_id, 403);

        $this->source = $source;
        $this->subtitleTemplateId = $source->subtitle_template_id;
    }

    public function saveTemplate(ActivityLogger $activity): void
    {
        $this->validate([
            'subtitleTemplateId' => ['nullable', 'integer', 'exists:subtitle_templates,id'],
        ], [], ['subtitleTemplateId' => 'Template subtitle']);

        $this->source->update(['subtitle_template_id' => $this->subtitleTemplateId]);
        $activity->log('source.subtitle_template_updated', 'Template subtitle sumber diperbarui.', $this->source);
        $this->dispatch('toast', message: 'Template subtitle sumber disimpan. Render klip berikutnya memakai template ini.');
    }

    public function retry(SourceRetryService $retries): void
    {
        try {
            $retries->retry($this->source);
            $this->dispatch('toast', message: 'Sumber dikirim ulang ke antrean pemrosesan.');
        } catch (\Throwable $exception) {
            $this->addError('retry', $exception->getMessage());
        }
    }

    public function render(): View
    {
        $source = $this->source->fresh()->load([
            'transcript',
            'clips' => fn ($query) => $query->orderBy('start_seconds'),
        ]);

        $segments = $source->transcript?->segments ?? [];
        $recentSegments = array_slice($segments, -8);

        return view('livewire.source-detail', [
            'source' => $source,
            'recentSegments' => $recentSegments,
            'segmentCount' => count($segments),
            'demoMissingFile' => $source->is_demo && ! is_file(Storage::disk($source->disk)->path($source->path)),
            'reviewCount' => $source->clips->where('status', ClipStatus::Review)->count(),
            'templates' => SubtitleTemplate::query()->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }
}
