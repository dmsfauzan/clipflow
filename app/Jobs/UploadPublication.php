<?php

namespace App\Jobs;

use App\Enums\PublicationStatus;
use App\Enums\UploadErrorCategory;
use App\Exceptions\PublishingException;
use App\Models\PublicationJob;
use App\Services\ActivityLogger;
use App\Services\ClipflowNotifier;
use App\Services\SchedulingService;
use App\Services\SettingsService;
use App\Services\YouTubePublishingService;
use App\Services\YouTubeQuotaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UploadPublication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public readonly PublicationJob $publicationJob)
    {
        $this->onQueue('uploads');
    }

    public function handle(
        YouTubePublishingService $publisher,
        SchedulingService $scheduling,
        SettingsService $settings,
        YouTubeQuotaService $quota,
        ActivityLogger $activity,
        ClipflowNotifier $notifier,
    ): void {
        $job = $this->publicationJob->fresh();

        if (! $job || $job->status !== PublicationStatus::Scheduled) {
            return;
        }

        $channel = $job->publishingChannel;

        if (! $channel || ! $channel->isConnected()) {
            $job->update([
                'status' => PublicationStatus::Failed,
                'error_category' => UploadErrorCategory::Auth->value,
                'last_error' => 'Channel YouTube belum terhubung. Upload dibatalkan tanpa percobaan.',
            ]);

            $activity->log('publication.failed', sprintf('Upload klip "%s" gagal: channel belum terhubung.', $job->title), $job);
            $notifier->uploadFailed($job, 'Channel YouTube belum terhubung.');

            return;
        }

        if ($scheduling->isPaused()) {
            $this->requeue($job, 300, 'Antrean sedang dijeda.');

            return;
        }

        if ($job->scheduled_for->isFuture()) {
            $this->requeue($job, max(60, (int) $job->scheduled_for->diffInSeconds(now()) + 5), 'Belum jatuh tempo.');

            return;
        }

        $dailyLimit = max(1, (int) $settings->get('publishing.daily_limit'));
        $effectiveLimit = $quota->effectiveDailyLimit($dailyLimit);

        if ($scheduling->uploadedToday($channel) >= $effectiveLimit) {
            $this->reschedule($job, $scheduling, 'Batas harian channel sudah tercapai.');

            return;
        }

        if (! $quota->canUpload($channel)) {
            $this->reschedule($job, $scheduling, 'Kuota YouTube Data API tidak cukup untuk upload berikutnya.');

            return;
        }

        $claimed = DB::transaction(function () use ($job): ?PublicationJob {
            $locked = PublicationJob::query()->whereKey($job->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== PublicationStatus::Scheduled) {
                return null;
            }

            $locked->update(['status' => PublicationStatus::Uploading]);

            return $locked;
        });

        if (! $claimed) {
            return;
        }

        try {
            $publisher->upload($claimed);

            $uploaded = $claimed->fresh();

            if ($uploaded !== null && $uploaded->status === PublicationStatus::Uploaded) {
                $notifier->uploadSucceeded($uploaded);
            }
        } catch (PublishingException $exception) {
            $fresh = $claimed->fresh();

            if (! $fresh) {
                return;
            }

            $safeRetries = max(0, (int) $settings->get('publishing.safe_retries'));
            $safeToRetry = $exception->errorCategory?->isSafeToRetry() ?? false;
            $uncertain = $fresh->status === PublicationStatus::NeedsReconciliation;

            if ($safeToRetry && ! $uncertain && (int) $fresh->attempt_count <= $safeRetries) {
                $backoffMinutes = $this->backoffMinutes((int) $fresh->attempt_count);
                $fresh->update(['status' => PublicationStatus::Scheduled]);

                $activity->log(
                    'publication.retry_scheduled',
                    sprintf('Upload klip "%s" dijadwalkan ulang setelah kegagalan jaringan.', $fresh->title),
                    $fresh,
                    ['attempt' => (int) $fresh->attempt_count, 'backoff_minutes' => $backoffMinutes],
                );

                self::dispatch($fresh)->delay(now()->addMinutes($backoffMinutes));
            } else {
                if ($fresh->status === PublicationStatus::NeedsReconciliation) {
                    $notifier->uploadNeedsReview($fresh, $exception->getMessage());
                } elseif ($fresh->status === PublicationStatus::Failed) {
                    $notifier->uploadFailed($fresh, $exception->getMessage());
                }
            }
        }
    }

    private function requeue(PublicationJob $job, int $seconds, string $reason): void
    {
        $job->update(['last_error' => $reason]);
        self::dispatch($job)->delay(now()->addSeconds($seconds));
    }

    private function reschedule(PublicationJob $job, SchedulingService $scheduling, string $reason): void
    {
        $next = $scheduling->nextAvailableSlot($job->publishingChannel, now()->addDay()->startOfDay());

        $job->update([
            'status' => PublicationStatus::Scheduled,
            'scheduled_for' => $next,
            'slot_date' => $next->copy()->timezone(config('clipflow.timezone'))->toDateString(),
            'slot_key' => PublicationJob::slotKey($job->publishing_channel_id, $next),
            'last_error' => $reason,
        ]);

        self::dispatch($job)->delay(max(60, (int) $next->diffInSeconds(now())));
    }

    private function backoffMinutes(int $attempt): int
    {
        $backoff = config('clipflow.publishing.retry_backoff_minutes', [5, 20]);
        $backoff = is_array($backoff) && $backoff !== [] ? $backoff : [5];
        $index = min(max(0, $attempt - 1), count($backoff) - 1);

        return (int) ($backoff[$index] ?? 5);
    }

    public function failed(?\Throwable $exception): void
    {
        $job = $this->publicationJob->fresh();

        if (! $job) {
            return;
        }

        if ($job->status === PublicationStatus::Uploading) {
            $job->update([
                'status' => PublicationStatus::Failed,
                'error_category' => UploadErrorCategory::Unknown->value,
                'last_error' => Str::limit((string) $exception?->getMessage(), 2000, '…'),
            ]);
        }

        if ($job->clip && $job->clip->status?->value === 'uploading') {
            $job->clip->update(['status' => 'failed']);
        }
    }
}
