<?php

namespace App\Jobs;

use App\Enums\ClipStatus;
use App\Enums\VideoSourceStatus;
use App\Exceptions\ClipSelectionException;
use App\Models\Clip;
use App\Models\VideoSource;
use App\Services\ActivityLogger;
use App\Services\ClipflowNotifier;
use App\Services\ClipSelectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SelectClipsForSource implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly VideoSource $source)
    {
        $this->onQueue('pipeline');
    }

    public function uniqueId(): string
    {
        return 'select-clips-source-'.$this->source->id;
    }

    public function handle(ClipSelectionService $selection, ActivityLogger $activity, ClipflowNotifier $notifier): void
    {
        $source = $this->source->fresh();

        if (! $source || $source->status !== VideoSourceStatus::Selecting) {
            return;
        }

        if ($source->clips()->exists()) {
            $source->update([
                'status' => VideoSourceStatus::Ready,
                'processing_stage' => 'Klip sudah pernah dipilih',
            ]);

            return;
        }

        $transcript = $source->transcript;

        if ($transcript === null) {
            $source->update([
                'status' => VideoSourceStatus::Failed,
                'processing_stage' => 'Transkripsi tidak ditemukan',
                'failure_message' => 'Transkripsi tidak tersedia sehingga seleksi klip tidak dapat dijalankan.',
            ]);

            return;
        }

        try {
            $candidates = $selection->selectForSource($source, $transcript);
        } catch (\Throwable $exception) {
            $message = $exception instanceof ClipSelectionException
                ? $exception->getMessage()
                : 'Seleksi AI gagal: '.$exception->getMessage();

            $source->update([
                'status' => VideoSourceStatus::Failed,
                'processing_stage' => 'Seleksi AI gagal',
                'failure_message' => Str::limit($message, 2000),
            ]);

            $activity->log('source.failed', sprintf('Seleksi klip "%s" gagal.', $source->original_name), $source, [
                'error' => $message,
            ]);

            $notifier->sourceFailed($source, $message);

            throw $exception;
        }

        if ($candidates === []) {
            $source->update([
                'status' => VideoSourceStatus::Ready,
                'processing_stage' => 'Tidak ada kandidat klip yang lolos validasi',
                'failure_message' => null,
            ]);

            $activity->log('source.no_candidates', 'AI tidak menghasilkan kandidat klip yang valid.', $source);

            return;
        }

        $clips = DB::transaction(function () use ($source, $transcript, $candidates): array {
            $created = [];

            foreach ($candidates as $candidate) {
                $clip = Clip::create([
                    'video_source_id' => $source->id,
                    'user_id' => $source->user_id,
                    'transcript_id' => $transcript->id,
                    'start_seconds' => $candidate['start'],
                    'end_seconds' => $candidate['end'],
                    'duration_seconds' => round($candidate['end'] - $candidate['start'], 3),
                    'title' => $candidate['title'],
                    'caption' => $candidate['caption'],
                    'reason' => $candidate['reason'],
                    'ai_score' => $candidate['score'],
                    'status' => ClipStatus::Rendering,
                    'render_started_at' => null,
                ]);

                $created[] = $clip;
            }

            $source->update([
                'status' => VideoSourceStatus::Rendering,
                'processing_stage' => count($created).' klip sedang dirender',
            ]);

            return $created;
        });

        foreach ($clips as $clip) {
            RenderClip::dispatch($clip);
        }

        $activity->log(
            'source.clips_selected',
            sprintf('%d kandidat klip dipilih dari "%s". Skor AI bersifat heuristik, bukan prediksi pasti viral.', count($clips), $source->original_name),
            $source,
            ['clip_ids' => array_map(fn (Clip $clip): int => $clip->id, $clips)],
        );
    }

    public function failed(?\Throwable $exception): void
    {
        $source = $this->source->fresh();

        if ($source && $source->status === VideoSourceStatus::Selecting) {
            $source->update([
                'status' => VideoSourceStatus::Failed,
                'processing_stage' => 'Seleksi AI gagal',
                'failure_message' => Str::limit((string) $exception?->getMessage(), 2000, '…'),
            ]);
        }
    }
}
