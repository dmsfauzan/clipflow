<?php

namespace App\Console\Commands;

use App\Enums\PublicationStatus;
use App\Exceptions\PublishingException;
use App\Models\PublicationJob;
use App\Services\ActivityLogger;
use App\Services\YouTubePublishingService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ReconcileUploads extends Command
{
    protected $signature = 'clipflow:reconcile-uploads {--job= : ID publikasi tertentu}';

    protected $description = 'Periksa status upload yang belum pasti tanpa membuat upload duplikat';

    public function handle(YouTubePublishingService $publisher, ActivityLogger $activity): int
    {
        $query = PublicationJob::query()
            ->with(['clip', 'publishingChannel'])
            ->where('status', PublicationStatus::NeedsReconciliation);

        if ($id = $this->option('job')) {
            $query->whereKey((int) $id);
        }

        $jobs = $query->get();

        if ($jobs->isEmpty()) {
            $this->info('Tidak ada publikasi yang perlu direkonsiliasi.');

            return self::SUCCESS;
        }

        foreach ($jobs as $job) {
            try {
                $status = $publisher->reconcile($job);

                match ($status['state']) {
                    'complete' => $this->info(sprintf('[%d] Ditemukan video %s. Tandai uploaded.', $job->id, $status['video_id'])),
                    'incomplete' => $this->warn(sprintf('[%d] Upload belum selesai di YouTube. Status tetap needs_reconciliation.', $job->id)),
                    'expired' => $this->warn(sprintf('[%d] Sesi upload kedaluwarsa. Periksa YouTube Studio manual.', $job->id)),
                    'missing_session' => $this->warn(sprintf('[%d] Sesi upload tidak tersimpan. Periksa YouTube Studio manual.', $job->id)),
                    default => $this->warn(sprintf('[%d] Status belum pasti.', $job->id)),
                };

                $job->update([
                    'last_error' => match ($status['state']) {
                        'incomplete' => 'Pemeriksaan menunjukkan upload belum selesai.',
                        'expired' => 'Sesi upload kedaluwarsa; verifikasi manual di YouTube Studio sebelum mencoba lagi.',
                        'missing_session' => 'Sesi upload tidak tersimpan; verifikasi manual di YouTube Studio sebelum mencoba lagi.',
                        default => $job->last_error,
                    },
                ]);
            } catch (PublishingException $exception) {
                $activity->log(
                    'publication.reconcile_failed',
                    sprintf('Pemeriksaan publikasi "%s" gagal.', $job->title),
                    $job,
                    ['error' => Str::limit($exception->getMessage(), 500)],
                );

                $this->error(sprintf('[%d] %s', $job->id, $exception->getMessage()));
            }
        }

        return self::SUCCESS;
    }
}
