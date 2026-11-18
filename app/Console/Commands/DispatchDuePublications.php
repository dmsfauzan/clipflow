<?php

namespace App\Console\Commands;

use App\Enums\PublicationStatus;
use App\Jobs\UploadPublication;
use App\Models\PublicationJob;
use App\Services\ActivityLogger;
use App\Services\SchedulingService;
use App\Services\SettingsService;
use Illuminate\Console\Command;

class DispatchDuePublications extends Command
{
    protected $signature = 'clipflow:dispatch-uploads {--limit= : Batas jumlah dispatch pada run ini}';

    protected $description = 'Kirim job upload yang sudah jatuh tempo tanpa memuat seluruh backlog sekaligus';

    public function handle(
        SchedulingService $scheduling,
        SettingsService $settings,
        ActivityLogger $activity,
    ): int {
        $limit = (int) ($this->option('limit') ?? $settings->get('publishing.dispatches_per_run'));
        $limit = max(1, $limit);
        $maxConcurrent = max(1, (int) $settings->get('publishing.max_concurrent'));

        $activeCount = PublicationJob::query()->where('status', PublicationStatus::Uploading)->count();
        $capacity = max(0, $maxConcurrent - $activeCount);

        if ($scheduling->isPaused()) {
            $this->info('Antrean upload sedang dijeda. Tidak ada upload baru.');

            return self::SUCCESS;
        }

        if ($capacity === 0) {
            $this->info('Batas upload bersamaan sudah tercapai. Tidak mengirim job baru.');

            return self::SUCCESS;
        }

        $limit = min($limit, $capacity);
        $grace = max(0, (int) $settings->get('publishing.overdue_grace_minutes'));

        $due = PublicationJob::query()
            ->with('clip')
            ->where('status', PublicationStatus::Scheduled)
            ->where('scheduled_for', '<=', now())
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->get();

        $dispatched = 0;

        foreach ($due as $job) {
            $overdueMinutes = (int) floor((now()->getTimestamp() - $job->scheduled_for->getTimestamp()) / 60);

            if ($overdueMinutes > $grace) {
                $next = $scheduling->nextAvailableSlot($job->publishingChannel, now());

                $job->update([
                    'scheduled_for' => $next,
                    'slot_date' => $next->copy()->timezone(config('clipflow.timezone'))->toDateString(),
                    'slot_key' => PublicationJob::slotKey($job->publishing_channel_id, $next),
                    'last_error' => 'Jadwal terlalu lama lewat dan digeser ke slot berikutnya agar backlog tidak diunggah sekaligus.',
                ]);

                $activity->log(
                    'publication.rescheduled',
                    sprintf('Jadwal klip "%s" digeser karena melewati batas toleransi.', $job->title),
                    $job,
                );

                continue;
            }

            UploadPublication::dispatch($job);
            $dispatched++;

            $activity->log(
                'publication.dispatched',
                sprintf('Job upload klip "%s" dikirim ke antrean.', $job->title),
                $job,
            );

            if ($dispatched >= $limit) {
                break;
            }
        }

        $pending = PublicationJob::query()->where('status', PublicationStatus::Scheduled)->count();
        $this->info(sprintf('Dispatch: %d job. Masih menunggu: %d job.', $dispatched, $pending));

        return self::SUCCESS;
    }
}
