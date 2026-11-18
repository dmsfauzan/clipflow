<?php

namespace App\Jobs;

use App\Enums\VideoSourceStatus;
use App\Models\VideoSource;
use App\Services\ActivityLogger;
use App\Services\ImportUrlValidator;
use App\Services\RemoteImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadRemoteSource implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3900;

    public function __construct(public readonly VideoSource $source)
    {
        $this->onQueue('pipeline');
        $this->timeout = max(600, (int) config('clipflow.download.timeout') + 300);
    }

    public function uniqueId(): string
    {
        return 'download-source-'.$this->source->id;
    }

    public function handle(
        RemoteImportService $imports,
        ImportUrlValidator $urlValidator,
        ActivityLogger $activity,
    ): void {
        $source = $this->source->fresh();

        if (! $source
            || $source->status !== VideoSourceStatus::Queued
            || ! $source->isImport()
            || ! is_string($source->source_url)
            || $source->source_url === ''
        ) {
            return;
        }

        $source->update([
            'status' => VideoSourceStatus::Downloading,
            'processing_stage' => 'Menyiapkan unduhan',
            'failure_message' => null,
            'import_started_at' => now(),
            'import_progress' => 0,
        ]);

        try {
            $result = $imports->download($source, function (int $percent) use ($source): void {
                $source->update([
                    'import_progress' => $percent,
                    'processing_stage' => 'Mengunduh dari link: '.$percent.'%',
                ]);
            });

            $extension = strtolower((string) pathinfo($result['path'], PATHINFO_EXTENSION)) ?: 'mp4';
            $relative = 'video-sources/'.$source->user_id.'/'.Str::uuid()->toString().'.'.$extension;
            $destination = Storage::disk('local')->path($relative);
            File::ensureDirectoryExists(dirname($destination));
            File::move($result['path'], $destination);

            $title = $result['info']['title'] ?? null;
            $title = is_string($title) && trim($title) !== '' ? trim($title) : pathinfo($source->original_name, PATHINFO_FILENAME);

            $source->update([
                'path' => $relative,
                'extension' => $extension,
                'mime_type' => $this->mimeTypeFor($extension),
                'size_bytes' => $result['size'],
                'downloaded_bytes' => $result['size'],
                'original_name' => Str::limit($title.'.'.$extension, 255, ''),
                'metadata' => array_merge($source->metadata ?? [], ['import' => $result['info']]),
                'status' => VideoSourceStatus::Uploaded,
                'processing_stage' => 'Unduhan selesai, menunggu pemeriksaan media',
                'import_progress' => 100,
                'import_completed_at' => now(),
                'failure_message' => null,
            ]);

            $imports->cleanup($result['work_directory']);

            ProbeVideoSource::dispatch($source->fresh());

            $activity->log(
                'source.imported',
                sprintf('Sumber "%s" berhasil diunduh dari %s.', $source->original_name, $urlValidator->redacted($source->source_url)),
                $source,
                ['size_bytes' => $result['size']],
            );
        } catch (\Throwable $exception) {
            $imports->cleanup($imports->workDirectory($source));

            $source->update([
                'status' => VideoSourceStatus::Failed,
                'processing_stage' => 'Gagal mengunduh dari link',
                'failure_message' => Str::limit($exception->getMessage(), 2000, '…'),
                'import_progress' => null,
            ]);

            $activity->log(
                'source.failed',
                sprintf('Unduhan sumber dari %s gagal.', $urlValidator->redacted((string) $source->source_url)),
                $source,
                ['error' => Str::limit($exception->getMessage(), 500, '…')],
            );

            throw $exception;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $source = $this->source->fresh();

        if ($source && $source->status === VideoSourceStatus::Downloading) {
            $source->update([
                'status' => VideoSourceStatus::Failed,
                'processing_stage' => 'Gagal mengunduh dari link',
                'failure_message' => Str::limit((string) $exception?->getMessage(), 2000, '…'),
                'import_progress' => null,
            ]);
        }
    }

    private function mimeTypeFor(string $extension): string
    {
        return match ($extension) {
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
            default => 'video/mp4',
        };
    }
}
