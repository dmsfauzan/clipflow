<?php

namespace App\Jobs;

use App\Enums\ClipStatus;
use App\Enums\VideoSourceStatus;
use App\Exceptions\RenderingException;
use App\Models\Clip;
use App\Services\ActivityLogger;
use App\Services\ClipflowNotifier;
use App\Services\VideoRenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class RenderClip implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly Clip $clip)
    {
        $this->onQueue('pipeline');
    }

    public function uniqueId(): string
    {
        return 'render-clip-'.$this->clip->id;
    }

    public function handle(VideoRenderService $renderer, ActivityLogger $activity, ClipflowNotifier $notifier): void
    {
        $clip = $this->clip->fresh();

        if (! $clip || $clip->status !== ClipStatus::Rendering) {
            return;
        }

        $clip->update([
            'render_started_at' => now(),
            'failure_message' => null,
        ]);

        try {
            $result = $renderer->render($clip, $clip->videoSource);

            $clip->update([
                'rendered_path' => $result['video'],
                'thumbnail_path' => $result['thumbnail'],
                'srt_path' => $result['subtitle'],
                'ass_path' => $result['ass'],
                'rendered_size_bytes' => $result['size_bytes'],
                'rendered_width' => $result['width'],
                'rendered_height' => $result['height'],
                'media_hash' => $result['media_hash'],
                'rendered_at' => now(),
                'status' => ClipStatus::Review,
                'failure_message' => null,
            ]);

            $source = $clip->videoSource()->first();

            $activity->log('clip.rendered', sprintf('Klip "%s" selesai dirender dan menunggu review.', $clip->title), $clip);

            if ($source !== null) {
                $remaining = $source->clips()->where('status', ClipStatus::Rendering)->count();
                $total = $source->clips()->count();

                $source->update([
                    'status' => $remaining > 0 ? VideoSourceStatus::Rendering : VideoSourceStatus::Ready,
                    'processing_stage' => $remaining > 0
                        ? sprintf('Merender klip (%d/%d)', $total - $remaining, $total)
                        : 'Semua klip selesai dirender',
                    'processed_at' => now(),
                ]);

                if ($remaining === 0) {
                    $reviewCount = $source->clips()->where('status', ClipStatus::Review)->count();
                    $failedCount = $source->clips()->where('status', ClipStatus::Failed)->count();

                    if ($reviewCount > 0) {
                        $notifier->clipsReady($source, $reviewCount, $failedCount);
                    }
                }
            }
        } catch (RenderingException $exception) {
            $clip->update([
                'status' => ClipStatus::Failed,
                'failure_message' => Str::limit($exception->getMessage(), 2000),
            ]);

            $activity->log('clip.failed', sprintf('Render klip "%s" gagal.', $clip->title), $clip, [
                'error' => $exception->getMessage(),
            ]);

            $source = $clip->videoSource()->first();

            if ($source !== null && $source->clips()->where('status', ClipStatus::Rendering)->count() === 0) {
                $failedCount = $source->clips()->where('status', ClipStatus::Failed)->count();

                $source->update([
                    'status' => VideoSourceStatus::Ready,
                    'processing_stage' => $failedCount > 0
                        ? sprintf('Selesai dengan %d klip gagal dirender', $failedCount)
                        : 'Semua klip selesai dirender',
                ]);
            }

            throw $exception;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $clip = $this->clip->fresh();

        if ($clip && $clip->status === ClipStatus::Rendering) {
            $clip->update([
                'status' => ClipStatus::Failed,
                'failure_message' => Str::limit((string) $exception?->getMessage(), 2000, '…'),
            ]);
        }
    }
}
