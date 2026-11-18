<?php

namespace App\Jobs;

use App\Enums\VideoSourceStatus;
use App\Exceptions\TranscriptionException;
use App\Models\VideoSource;
use App\Services\ActivityLogger;
use App\Services\ClipflowNotifier;
use App\Services\TranscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TranscribeVideoSource implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(public readonly VideoSource $source)
    {
        $this->onQueue('pipeline');
    }

    public function uniqueId(): string
    {
        return 'transcribe-source-'.$this->source->id;
    }

    public function handle(TranscriptionService $transcription, ActivityLogger $activity, ClipflowNotifier $notifier): void
    {
        $source = $this->source->fresh();

        if (! $source
            || $source->status !== VideoSourceStatus::Probing
            || ($source->transcript && $source->transcript->status?->value === 'completed')
        ) {
            return;
        }

        try {
            $transcript = $transcription->transcribe($source);

            $source->update([
                'status' => VideoSourceStatus::Selecting,
                'processing_stage' => 'Seleksi kandidat klip oleh AI',
                'failure_message' => null,
            ]);

            SelectClipsForSource::dispatch($source);

            $activity->log(
                'source.transcribed',
                sprintf('Transkripsi "%s" selesai (%d segmen).', $source->original_name, count($transcript->segments ?? [])),
                $source,
            );
        } catch (TranscriptionException $exception) {
            $activity->log('source.failed', sprintf('Transkripsi "%s" gagal.', $source->original_name), $source, [
                'error' => $exception->getMessage(),
            ]);

            $notifier->sourceFailed($source, $exception->getMessage());

            throw $exception;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $source = $this->source->fresh();

        if ($source && $source->status === VideoSourceStatus::Transcribing) {
            $source->update([
                'status' => VideoSourceStatus::Failed,
                'processing_stage' => 'Transkripsi gagal',
                'failure_message' => Str::limit((string) $exception?->getMessage(), 2000, '…'),
            ]);
        }
    }
}
