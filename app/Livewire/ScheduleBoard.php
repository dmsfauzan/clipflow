<?php

namespace App\Livewire;

use App\Enums\PublicationStatus;
use App\Exceptions\PublishingException;
use App\Jobs\UploadPublication;
use App\Models\PublicationJob;
use App\Services\ActivityLogger;
use App\Services\SchedulingService;
use App\Services\SettingsService;
use App\Services\YouTubePublishingService;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ScheduleBoard extends AuthenticatedComponent
{
    public bool $showAll = false;

    public function pause(SchedulingService $scheduling): void
    {
        $scheduling->pause(auth()->user());
        $this->dispatch('toast', message: 'Antrean upload dijeda. Tidak ada upload baru.');
    }

    public function resume(SchedulingService $scheduling): void
    {
        $scheduling->resume(auth()->user());
        $this->dispatch('toast', message: 'Antrean upload dilanjutkan.');
    }

    public function cancel(int $jobId, SchedulingService $scheduling): void
    {
        $job = PublicationJob::query()->where('user_id', auth()->id())->findOrFail($jobId);

        try {
            $scheduling->cancel($job, auth()->user());
            $this->dispatch('toast', message: 'Jadwal dibatalkan dan klip kembali ke review.');
        } catch (\Throwable $exception) {
            $this->addError('cancel-'.$jobId, $exception->getMessage());
        }
    }

    public function reconcile(int $jobId, YouTubePublishingService $publisher): void
    {
        $job = PublicationJob::query()->where('user_id', auth()->id())->findOrFail($jobId);

        if ($job->status !== PublicationStatus::NeedsReconciliation) {
            $this->addError('reconcile-'.$jobId, 'Hanya publikasi berstatus perlu pemeriksaan yang dapat direkonsiliasi.');

            return;
        }

        try {
            $result = $publisher->reconcile($job);

            $message = match ($result['state']) {
                'complete' => 'Video ditemukan dan ditandai uploaded.',
                'incomplete' => 'YouTube menyatakan upload belum selesai. Gunakan Lanjutkan upload untuk melanjutkan sesi yang sama.',
                'expired', 'missing_session' => 'Sesi upload tidak tersedia. Periksa YouTube Studio secara manual; sistem tidak akan membuat upload baru.',
                default => 'Status belum dapat dipastikan. Tidak ada upload baru dibuat.',
            };
        } catch (PublishingException $exception) {
            $message = $exception->getMessage();
        }

        $this->dispatch('toast', message: $message);
    }

    public function resumeUpload(int $jobId, YouTubePublishingService $publisher): void
    {
        $job = PublicationJob::query()->where('user_id', auth()->id())->findOrFail($jobId);

        if ($job->status !== PublicationStatus::NeedsReconciliation) {
            return;
        }

        try {
            $publisher->resume($job);
            $this->dispatch('toast', message: 'Sesi upload dilanjutkan tanpa membuat upload baru.');
        } catch (PublishingException $exception) {
            $this->addError('resume-'.$jobId, $exception->getMessage());
        }
    }

    public function retryFailed(int $jobId, SchedulingService $scheduling, SettingsService $settings, ActivityLogger $activity): void
    {
        $job = PublicationJob::query()
            ->with(['clip', 'approval', 'publishingChannel'])
            ->where('user_id', auth()->id())
            ->findOrFail($jobId);

        if ($job->status !== PublicationStatus::Failed) {
            return;
        }

        if (! in_array($job->error_category, ['network', 'unknown'], true)) {
            $this->addError('retry-'.$jobId, 'Error ini tidak aman diulang otomatis. Perbaiki penyebabnya lalu coba lagi secara manual.');

            return;
        }

        if (! $job->publishingChannel?->isConnected()) {
            $this->addError('retry-'.$jobId, 'Channel YouTube belum terhubung.');

            return;
        }

        if (! $job->approval?->isValidFor($job->clip)) {
            $this->addError('retry-'.$jobId, 'Approval tidak valid. Minta review ulang.');

            return;
        }

        $path = Storage::disk('local')->path((string) $job->clip?->rendered_path);

        if (! is_file($path)) {
            $this->addError('retry-'.$jobId, 'File render tidak tersedia.');

            return;
        }

        $job->update([
            'status' => PublicationStatus::Scheduled,
            'error_category' => null,
            'last_error' => 'Dijadwalkan ulang manual oleh operator.',
        ]);

        UploadPublication::dispatch($job);
        $activity->log('publication.manual_retry', sprintf('Upload "%s" dijadwalkan ulang manual.', $job->title), $job);
        $this->dispatch('toast', message: 'Upload dijadwalkan ulang secara manual.');
    }

    public function render(SettingsService $settings): View
    {
        $jobs = PublicationJob::query()
            ->where('user_id', auth()->id())
            ->with(['clip', 'publishingChannel'])
            ->orderBy('scheduled_for')
            ->when(! $this->showAll, fn ($query) => $query->whereIn('status', [PublicationStatus::Scheduled, PublicationStatus::Uploading, PublicationStatus::NeedsReconciliation]))
            ->limit($this->showAll ? 100 : 50)
            ->get();

        $timezone = (string) config('clipflow.timezone');
        $today = now()->timezone($timezone)->startOfDay();

        $uploadedToday = PublicationJob::query()
            ->where('user_id', auth()->id())
            ->where('status', PublicationStatus::Uploaded)
            ->whereBetween('uploaded_at', [$today, $today->copy()->endOfDay()])
            ->count();

        return view('livewire.schedule-board', [
            'jobs' => $jobs,
            'today' => $today,
            'paused' => $settings->boolean('publishing.paused'),
            'dailyLimit' => (int) $settings->get('publishing.daily_limit'),
            'slotStart' => $settings->string('publishing.slot_start'),
            'slotEnd' => $settings->string('publishing.slot_end'),
            'timezone' => $timezone,
            'uploadedToday' => $uploadedToday,
            'needsAttention' => $jobs->where('status', PublicationStatus::NeedsReconciliation)->count(),
        ]);
    }
}
