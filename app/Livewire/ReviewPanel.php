<?php

namespace App\Livewire;

use App\Enums\ClipStatus;
use App\Enums\PublicationStatus;
use App\Models\Clip;
use App\Models\PublishingChannel;
use App\Models\SubtitleTemplate;
use App\Services\ActivityLogger;
use App\Services\ApprovalService;
use App\Services\ClipRerenderService;
use App\Services\SchedulingService;
use App\Services\SettingsService;
use App\Services\SubtitleTemplateService;
use Illuminate\View\View;
use Livewire\Attributes\Validate;

class ReviewPanel extends AuthenticatedComponent
{
    public Clip $clip;

    #[Validate('required|string|max:200')]
    public string $title = '';

    #[Validate('nullable|string|max:3000')]
    public string $caption = '';

    #[Validate('required')]
    public ?int $channelId = null;

    #[Validate('required')]
    public string $privacy = 'private';

    public bool $madeForKids = false;

    public ?int $subtitleTemplateId = null;

    public $renderStart = null;

    public $renderEnd = null;

    public int $foregroundWidth = 90;

    public int $blurSigma = 18;

    public bool $subtitlesEnabled = true;

    public function mount(Clip $clip, SettingsService $settings): void
    {
        abort_unless(auth()->user()?->id === $clip->user_id, 403);

        $this->clip = $clip->load(['videoSource.subtitleTemplate', 'approvals.user', 'publicationJob.publishingChannel', 'subtitleTemplate']);
        $this->title = (string) $clip->title;
        $this->caption = (string) $clip->caption;
        $this->subtitleTemplateId = $clip->subtitle_template_id;
        $this->renderStart = (float) $clip->start_seconds;
        $this->renderEnd = (float) $clip->end_seconds;
        $this->foregroundWidth = (int) ($clip->foreground_width ?: config('clipflow.media.render.foreground_width_percent.default'));
        $this->blurSigma = (int) ($clip->blur_sigma ?? config('clipflow.media.render.blur_sigma.default'));
        $this->subtitlesEnabled = (bool) ($clip->subtitles_enabled ?? true);

        $channel = PublishingChannel::query()
            ->where('user_id', auth()->id())
            ->where('status', 'connected')
            ->where('is_active', true)
            ->first();

        $this->channelId = $channel?->id;
        $this->privacy = (string) $settings->get('publishing.default_privacy', 'private');

        if ($this->clip->publicationJob?->status?->value !== null) {
            $this->privacy = $this->clip->publicationJob->privacy->value;
            $this->madeForKids = $this->clip->publicationJob->made_for_kids;
        }
    }

    public function saveContent(ApprovalService $approvals, ActivityLogger $activity): void
    {
        $this->validate();

        $updated = $approvals->updateContent($this->clip, auth()->user(), $this->title, $this->caption !== '' ? $this->caption : null);
        $this->clip = $updated->fresh(['videoSource', 'approvals.user', 'publicationJob.publishingChannel']);

        $activity->log('clip.content_updated', sprintf('Konten klip "%s" diperbarui.', $updated->title), $updated);
        $this->dispatch('toast', message: 'Konten disimpan. Approval sebelumnya dibatalkan dan review ulang diperlukan.');
    }

    public function approve(ApprovalService $approvals, SchedulingService $scheduling, SettingsService $settings): void
    {
        $this->validate([
            'channelId' => ['required', 'integer'],
            'privacy' => ['required', 'in:private,unlisted,public'],
        ], [
            'channelId.required' => 'Pilih channel tujuan.',
        ]);

        $channel = PublishingChannel::query()->where('user_id', auth()->id())->find($this->channelId);

        try {
            $approval = $approvals->approve(
                $this->clip,
                auth()->user(),
                $channel,
                $this->privacy,
                $this->madeForKids,
            );

            if ($settings->boolean('publishing.auto_schedule')) {
                $scheduling->schedule($this->clip, $approval, null, auth()->user());
            }
        } catch (\Throwable $exception) {
            $this->addError('approve', $exception->getMessage());

            return;
        }

        $this->clip = $this->clip->fresh(['videoSource', 'approvals.user', 'publicationJob.publishingChannel']);
        $this->dispatch('toast', message: $settings->boolean('publishing.auto_schedule')
            ? 'Klip disetujui dan dijadwalkan upload.'
            : 'Klip disetujui. Upload tidak terjadi sampai dijadwalkan.');
    }

    public function reject(ApprovalService $approvals, ?string $reason = null): void
    {
        try {
            $approvals->reject($this->clip, auth()->user(), $reason !== null && $reason !== '' ? mb_substr($reason, 0, 1000) : null);
        } catch (\Throwable $exception) {
            $this->addError('reject', $exception->getMessage());

            return;
        }

        $this->clip = $this->clip->fresh(['videoSource', 'approvals.user', 'publicationJob.publishingChannel']);
        $this->dispatch('toast', message: 'Klip ditolak.');
    }

    public function restore(ApprovalService $approvals): void
    {
        $approvals->restoreToReview($this->clip, auth()->user());
        $this->clip = $this->clip->fresh(['videoSource', 'approvals.user', 'publicationJob.publishingChannel']);
        $this->dispatch('toast', message: 'Klip dikembalikan ke antrean review.');
    }

    public function scheduleNow(SchedulingService $scheduling): void
    {
        try {
            $approval = $this->clip->currentApproval;

            if (! $approval) {
                $this->addError('schedule', 'Klip belum memiliki approval aktif.');

                return;
            }

            $scheduling->schedule($this->clip, $approval, null, auth()->user());
        } catch (\Throwable $exception) {
            $this->addError('schedule', $exception->getMessage());

            return;
        }

        $this->clip = $this->clip->fresh(['videoSource', 'approvals.user', 'publicationJob.publishingChannel']);
        $this->dispatch('toast', message: 'Klip dijadwalkan pada slot berikutnya yang tersedia.');
    }

    public function cancelSchedule(SchedulingService $scheduling): void
    {
        $job = $this->clip->publicationJob;

        if (! $job || $job->status !== PublicationStatus::Scheduled) {
            return;
        }

        try {
            $scheduling->cancel($job, auth()->user());
        } catch (\Throwable $exception) {
            $this->addError('cancel', $exception->getMessage());

            return;
        }

        $this->clip = $this->clip->fresh(['videoSource', 'approvals.user', 'publicationJob.publishingChannel']);
        $this->dispatch('toast', message: 'Jadwal dibatalkan dan klip dikembalikan ke review.');
    }

    public function rerender(ClipRerenderService $rerender): void
    {
        $this->updateRender($rerender);
    }

    public function updateRender(ClipRerenderService $rerender): void
    {
        $sourceDuration = (float) ($this->clip->videoSource->duration_seconds ?? 0);
        $minClip = max(1.0, (float) config('clipflow.ai.min_clip_seconds'));
        $maxClip = max($minClip, (float) config('clipflow.ai.max_clip_seconds'));

        $this->validate([
            'renderStart' => ['required', 'numeric', 'min:0', 'max:'.$sourceDuration],
            'renderEnd' => ['required', 'numeric', 'gt:renderStart', 'max:'.$sourceDuration],
            'foregroundWidth' => ['required', 'integer', 'min:'.config('clipflow.media.render.foreground_width_percent.min'), 'max:'.config('clipflow.media.render.foreground_width_percent.max')],
            'blurSigma' => ['required', 'integer', 'min:'.config('clipflow.media.render.blur_sigma.min'), 'max:'.config('clipflow.media.render.blur_sigma.max')],
        ], [
            'renderEnd.gt' => 'Waktu selesai harus lebih besar dari waktu mulai.',
            'renderEnd.max' => 'Waktu selesai melebihi durasi sumber.',
        ]);

        if ((float) $this->renderEnd - (float) $this->renderStart < $minClip || (float) $this->renderEnd - (float) $this->renderStart > $maxClip) {
            $this->addError('renderEnd', sprintf('Durasi klip harus %d–%d detik.', (int) $minClip, (int) $maxClip));

            return;
        }

        try {
            $this->clip = $rerender->updateSettings($this->clip, auth()->user(), [
                'start_seconds' => round((float) $this->renderStart, 3),
                'end_seconds' => round((float) $this->renderEnd, 3),
                'duration_seconds' => round((float) $this->renderEnd - (float) $this->renderStart, 3),
                'foreground_width' => $this->foregroundWidth,
                'blur_sigma' => $this->blurSigma,
                'subtitles_enabled' => $this->subtitlesEnabled,
            ]);
        } catch (\Throwable $exception) {
            $this->addError('rerender', $exception->getMessage());

            return;
        }

        $this->clip = $this->clip->fresh(['videoSource', 'approvals.user', 'publicationJob.publishingChannel']);
        $this->dispatch('toast', message: 'Render ulang dijadwalkan. Approval dan jadwal lama dibatalkan.');
    }

    public function applyTemplate(ClipRerenderService $rerender): void
    {
        $this->validate([
            'subtitleTemplateId' => ['nullable', 'integer', 'exists:subtitle_templates,id'],
        ], [], ['subtitleTemplateId' => 'Template subtitle']);

        if (! in_array($this->clip->status, [ClipStatus::Review, ClipStatus::Failed], true)) {
            $this->addError('subtitleTemplate', 'Template hanya dapat diterapkan pada klip berstatus review atau gagal.');

            return;
        }

        $this->clip->update(['subtitle_template_id' => $this->subtitleTemplateId]);

        try {
            $rerender->queue($this->clip->fresh());
        } catch (\Throwable $exception) {
            $this->addError('subtitleTemplate', $exception->getMessage());

            return;
        }

        $this->clip = $this->clip->fresh(['videoSource.subtitleTemplate', 'approvals.user', 'publicationJob.publishingChannel', 'subtitleTemplate']);
        $this->dispatch('toast', message: 'Template diterapkan dan render ulang dijadwalkan.');
    }

    public function render(): View
    {
        $this->clip->load(['videoSource.subtitleTemplate', 'approvals.user', 'publicationJob.publishingChannel', 'subtitleTemplate']);

        $effectiveTemplate = $this->clip->subtitleTemplate
            ?? $this->clip->videoSource?->subtitleTemplate
            ?? app(SubtitleTemplateService::class)->active();

        return view('livewire.review-panel', [
            'channels' => PublishingChannel::query()->where('user_id', auth()->id())->get(),
            'templates' => SubtitleTemplate::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'effectiveTemplateName' => $effectiveTemplate?->name ?? 'Default Clipflow',
            'approval' => $this->clip->currentApproval,
            'job' => $this->clip->publicationJob,
            'autoSchedule' => app(SettingsService::class)->boolean('publishing.auto_schedule'),
            'renderedUrl' => $this->clip->rendered_path ? route('media.clip.preview', $this->clip) : null,
            'thumbnailUrl' => $this->clip->thumbnail_path ? route('media.clip.thumbnail', $this->clip) : null,
            'downloadUrl' => $this->clip->rendered_path ? route('media.clip.download', $this->clip) : null,
        ]);
    }
}
