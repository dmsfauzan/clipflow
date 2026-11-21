<?php

namespace App\Livewire;

use App\Enums\ClipStatus;
use App\Enums\VideoSourceStatus;
use App\Jobs\RenderClip;
use App\Models\Clip;
use App\Models\SubtitleTemplate;
use App\Models\VideoSource;
use App\Services\ActivityLogger;
use App\Services\SourceRetryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SourceDetail extends AuthenticatedComponent
{
    public VideoSource $source;

    public ?int $subtitleTemplateId = null;

    public $manualStart = null;

    public $manualEnd = null;

    public string $manualTitle = '';

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

    public function createManualClip(ActivityLogger $activity): void
    {
        $source = $this->source->fresh();

        abort_unless($source !== null && auth()->user()?->id === $source->user_id, 403);

        if ($source->is_demo) {
            $this->addError('manualStart', 'Data demo tidak memiliki file media; klip manual tidak dapat dirender.');

            return;
        }

        if ($source->probed_at === null || (float) $source->duration_seconds <= 0) {
            $this->addError('manualStart', 'Durasi sumber belum diketahui. Tunggu FFprobe selesai.');

            return;
        }

        $max = (float) $source->duration_seconds;
        $minClip = max(1.0, (float) config('clipflow.ai.min_clip_seconds'));
        $maxClip = max($minClip, (float) config('clipflow.ai.max_clip_seconds'));

        $this->validate([
            'manualStart' => ['required', 'numeric', 'min:0', 'max:'.$max],
            'manualEnd' => ['required', 'numeric', 'gt:manualStart', 'max:'.$max],
            'manualTitle' => ['nullable', 'string', 'max:200'],
        ], [
            'manualStart.required' => 'Isi detik mulai.',
            'manualEnd.required' => 'Isi detik selesai.',
            'manualEnd.gt' => 'Detik selesai harus lebih besar dari detik mulai.',
            'manualEnd.max' => 'Detik selesai melebihi durasi sumber.',
            'manualStart.max' => 'Detik mulai melebihi durasi sumber.',
        ], ['manualStart' => 'Mulai', 'manualEnd' => 'Selesai', 'manualTitle' => 'Judul']);

        $start = (float) $this->manualStart;
        $end = (float) $this->manualEnd;

        [$start, $end] = $this->snapToSegments($source, $start, $end);

        if ($end - $start < $minClip || $end - $start > $maxClip) {
            $this->addError('manualEnd', sprintf('Durasi klip harus %d–%d detik (saat ini %s detik).', (int) $minClip, (int) $maxClip, number_format($end - $start, 1)));

            return;
        }

        $overlap = $source->clips()
            ->whereNotIn('status', [ClipStatus::Rejected])
            ->where('start_seconds', '<', $end)
            ->where('end_seconds', '>', $start)
            ->first();

        if ($overlap !== null) {
            $this->addError('manualStart', sprintf('Rentang ini tumpang tindih dengan clip "%s". Pilih rentang lain.', $overlap->title));

            return;
        }

        $title = trim($this->manualTitle);

        if ($title === '') {
            $title = sprintf('Klip manual %s–%s', $this->clock($start), $this->clock($end));
        }

        $clip = DB::transaction(function () use ($source, $start, $end, $title): Clip {
            return Clip::create([
                'video_source_id' => $source->id,
                'user_id' => $source->user_id,
                'transcript_id' => $source->transcript?->id,
                'start_seconds' => $start,
                'end_seconds' => $end,
                'duration_seconds' => round($end - $start, 3),
                'title' => $title,
                'caption' => null,
                'reason' => 'Dipilih manual oleh pengguna. Bukan hasil seleksi AI.',
                'status' => ClipStatus::Rendering,
                'foreground_width' => (int) config('clipflow.media.render.foreground_width_percent.default'),
                'blur_sigma' => (int) config('clipflow.media.render.blur_sigma.default'),
                'subtitles_enabled' => $source->transcript !== null,
            ]);
        });

        $source->update([
            'status' => VideoSourceStatus::Rendering,
            'processing_stage' => 'Merender klip manual',
            'failure_message' => null,
        ]);

        RenderClip::dispatch($clip);

        $activity->log('clip.created_manual', sprintf('Klip manual "%s" dibuat dan masuk antrean render.', $clip->title), $clip);

        $this->reset('manualStart', 'manualEnd', 'manualTitle');
        $this->dispatch('toast', message: 'Klip manual dibuat. Render berjalan di background.');
    }

    /**
     * Geser batas ke awal/akhir segmen terdekat agar kalimat tidak terpotong.
     *
     * @return array{0: float, 1: float}
     */
    private function snapToSegments(VideoSource $source, float $start, float $end): array
    {
        $segments = $source->transcript?->segments ?? [];
        $tolerance = 1.5;
        $minClip = max(1.0, (float) config('clipflow.ai.min_clip_seconds'));
        $maxClip = max($minClip, (float) config('clipflow.ai.max_clip_seconds'));

        foreach ($segments as $segment) {
            $segmentStart = (float) ($segment['start'] ?? 0);
            $segmentEnd = (float) ($segment['end'] ?? 0);

            if (abs($segmentStart - $start) <= $tolerance) {
                $start = min($segmentStart, $end - $minClip);
            }

            if (abs($segmentEnd - $end) <= $tolerance && ($segmentEnd - $start) <= $maxClip) {
                $end = $segmentEnd;
            }
        }

        return [max(0.0, round($start, 3)), round($end, 3)];
    }

    private function clock(float $seconds): string
    {
        $minutes = intdiv((int) $seconds, 60);
        $rest = (int) $seconds % 60;

        return sprintf('%d:%02d', $minutes, $rest);
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
