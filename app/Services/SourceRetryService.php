<?php

namespace App\Services;

use App\Enums\ClipStatus;
use App\Enums\VideoSourceStatus;
use App\Exceptions\MediaProcessingException;
use App\Jobs\DownloadRemoteSource;
use App\Jobs\ProbeVideoSource;
use App\Models\VideoSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SourceRetryService
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function retry(VideoSource $source): void
    {
        if ($source->status !== VideoSourceStatus::Failed) {
            throw new MediaProcessingException('Hanya sumber berstatus gagal yang dapat dicoba ulang.', 'retry_status');
        }

        $needsDownload = $source->isImport()
            && ! Storage::disk($source->disk)->exists($source->path);

        DB::transaction(function () use ($source, $needsDownload): void {
            $source->clips()->whereIn('status', [ClipStatus::Failed, ClipStatus::Rendering])->delete();
            $source->transcript()->delete();
            $source->update([
                'status' => $needsDownload ? VideoSourceStatus::Queued : VideoSourceStatus::Uploaded,
                'processing_stage' => null,
                'failure_message' => null,
                'probed_at' => null,
                'processed_at' => null,
                'duration_seconds' => null,
                'import_progress' => null,
                'import_started_at' => null,
                'import_completed_at' => null,
            ]);
        });

        if ($needsDownload) {
            DownloadRemoteSource::dispatch($source->fresh());
        } else {
            ProbeVideoSource::dispatch($source->fresh());
        }

        $this->activity->log('source.retried', sprintf('Sumber "%s" dikirim ulang ke pipeline.', $source->original_name), $source);
    }
}
