<?php

namespace App\Jobs;

use App\Enums\VideoSourceStatus;
use App\Exceptions\MediaProcessingException;
use App\Models\VideoSource;
use App\Services\ActivityLogger;
use App\Services\ClipflowNotifier;
use App\Services\MediaProbeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProbeVideoSource implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly VideoSource $source)
    {
        $this->onQueue('pipeline');
    }

    public function uniqueId(): string
    {
        return 'probe-source-'.$this->source->id;
    }

    public function handle(MediaProbeService $mediaProbe, ActivityLogger $activity, ClipflowNotifier $notifier): void
    {
        $source = $this->source->fresh();

        if (! $source || $source->status !== VideoSourceStatus::Uploaded || $source->probed_at !== null) {
            return;
        }

        $source->update([
            'status' => VideoSourceStatus::Probing,
            'processing_stage' => 'Memeriksa file dengan FFprobe',
            'failure_message' => null,
        ]);

        try {
            $info = $mediaProbe->probe(Storage::disk($source->disk)->path($source->path));

            $maxDuration = (int) config('clipflow.media.max_duration_seconds');

            if ($maxDuration > 0 && $info['duration_seconds'] > $maxDuration) {
                throw new MediaProcessingException(sprintf(
                    'Durasi video %.1f menit melebihi batas %d jam. Batas durasi dapat diubah di .env.',
                    $info['duration_seconds'] / 60,
                    intdiv($maxDuration, 3600),
                ), 'duration_limit');
            }

            $source->update([
                'duration_seconds' => $info['duration_seconds'],
                'width' => $info['width'],
                'height' => $info['height'],
                'has_video' => $info['has_video'],
                'has_audio' => $info['has_audio'],
                'metadata' => array_merge($source->metadata ?? [], [
                    'video_codec' => $info['video_codec'],
                    'audio_codec' => $info['audio_codec'],
                    'container' => $info['container'],
                ]),
                'probed_at' => now(),
            ]);

            TranscribeVideoSource::dispatch($source);
        } catch (MediaProcessingException $exception) {
            $source->update([
                'status' => VideoSourceStatus::Failed,
                'processing_stage' => 'Gagal memeriksa media',
                'failure_message' => Str::limit($exception->getMessage(), 2000),
            ]);

            $activity->log('source.failed', sprintf('Sumber "%s" gagal diproses.', $source->original_name), $source, [
                'error' => $exception->getMessage(),
            ]);

            $notifier->sourceFailed($source, $exception->getMessage());

            throw $exception;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $source = $this->source->fresh();

        if ($source && in_array($source->status, [VideoSourceStatus::Uploaded, VideoSourceStatus::Probing], true)) {
            $source->update([
                'status' => VideoSourceStatus::Failed,
                'processing_stage' => 'Gagal memeriksa media',
                'failure_message' => Str::limit((string) $exception?->getMessage(), 2000, '…'),
            ]);
        }
    }
}
