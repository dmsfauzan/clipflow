<?php

namespace App\Services;

use App\Exceptions\MediaProcessingException;
use App\Models\VideoSource;
use Illuminate\Support\Facades\File;

class RemoteImportService
{
    public function __construct(
        private readonly ProcessRunner $processes,
    ) {}

    public function isAvailable(): bool
    {
        return $this->processes->isAvailable((string) config('clipflow.download.ytdlp_binary'));
    }

    /**
     * Unduh video dari link ke direktori kerja privat.
     *
     * @param  callable(int): void|null  $onProgress
     * @return array{path: string, size: int, info: array<string, mixed>, work_directory: string}
     */
    public function download(VideoSource $source, ?callable $onProgress = null): array
    {
        $url = (string) $source->source_url;
        $workDirectory = $this->workDirectory($source);
        File::ensureDirectoryExists($workDirectory);

        $lastPercent = null;

        $this->processes->runStreaming(
            $this->buildCommand($url, $workDirectory),
            (int) config('clipflow.download.timeout'),
            function (string $type, string $chunk) use (&$lastPercent, $onProgress): void {
                if (preg_match_all('/\[download\]\s+(\d+(?:\.\d+)?)%/', $chunk, $matches) === 0) {
                    return;
                }

                $percent = (int) min(100, max(0, (int) floor((float) end($matches[1]))));

                if ($percent !== $lastPercent) {
                    $lastPercent = $percent;

                    if ($onProgress !== null) {
                        $onProgress($percent);
                    }
                }
            },
        );

        $mediaPath = $this->findMediaFile($workDirectory);
        $info = $this->readInfo($workDirectory);
        $size = (int) filesize($mediaPath);
        $maxBytes = (int) config('clipflow.media.max_upload_bytes');

        if ($size <= 0) {
            throw new MediaProcessingException('Hasil unduhan kosong.', 'download_output');
        }

        if ($maxBytes > 0 && $size > $maxBytes) {
            throw new MediaProcessingException(sprintf(
                'Ukuran hasil unduhan (%s) melebihi batas %s.',
                number_format($size / 1048576, 1, ',', '.').' MB',
                number_format($maxBytes / 1048576, 0, ',', '.').' MB',
            ), 'download_size');
        }

        $duration = $info['duration'] ?? null;
        $maxDuration = (int) config('clipflow.media.max_duration_seconds');

        if (is_numeric($duration) && $maxDuration > 0 && (float) $duration > $maxDuration) {
            throw new MediaProcessingException(sprintf(
                'Durasi video %.1f menit melebihi batas %d jam.',
                (float) $duration / 60,
                intdiv($maxDuration, 3600),
            ), 'download_duration');
        }

        return [
            'path' => $mediaPath,
            'size' => $size,
            'info' => $info,
            'work_directory' => $workDirectory,
        ];
    }

    public function cleanup(string $workDirectory): void
    {
        File::deleteDirectory($workDirectory);
    }

    public function workDirectory(VideoSource $source): string
    {
        return storage_path('app/private/imports/'.$source->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function readInfo(string $workDirectory): array
    {
        $files = glob($workDirectory.'/*.info.json') ?: [];

        if ($files === []) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($files[0]), true);

        if (! is_array($decoded)) {
            return [];
        }

        return [
            'extractor' => $decoded['extractor_key'] ?? ($decoded['extractor'] ?? null),
            'id' => $decoded['id'] ?? null,
            'title' => $decoded['title'] ?? null,
            'uploader' => $decoded['uploader'] ?? ($decoded['channel'] ?? null),
            'duration' => $decoded['duration'] ?? null,
            'webpage_url' => $decoded['webpage_url'] ?? null,
            'upload_date' => $decoded['upload_date'] ?? null,
            'max_height' => $decoded['height'] ?? null,
        ];
    }

    private function findMediaFile(string $workDirectory): string
    {
        $candidates = [];

        foreach (glob($workDirectory.'/*') ?: [] as $file) {
            if (! is_file($file)) {
                continue;
            }

            if (preg_match('/\.(info\.json|part|ytdl|temp|srt|vtt|json)$/i', $file) === 1) {
                continue;
            }

            $candidates[] = $file;
        }

        if ($candidates === []) {
            throw new MediaProcessingException(
                'yt-dlp tidak menghasilkan file media. Pastikan link valid dan yt-dlp terpasang.',
                'download_output',
            );
        }

        usort($candidates, fn (string $a, string $b): int => filesize($b) <=> filesize($a));

        return $candidates[0];
    }

    /**
     * @return list<string>
     */
    private function buildCommand(string $url, string $workDirectory): array
    {
        $command = [
            $this->processes->resolve((string) config('clipflow.download.ytdlp_binary')),
            '--no-playlist',
            '--newline',
            '--progress',
            '--no-color',
            '-f', $this->formatSelector(),
            '--merge-output-format', 'mp4',
            '--max-filesize', (string) config('clipflow.media.max_upload_bytes'),
            '--socket-timeout', (string) config('clipflow.download.socket_timeout'),
            '--retries', (string) config('clipflow.download.retries'),
            '--fragment-retries', (string) config('clipflow.download.retries'),
            '--write-info-json',
            '--restrict-filenames',
            '-o', $workDirectory.'/%(id)s.%(ext)s',
        ];

        $cookies = config('clipflow.download.cookies_file');

        if (is_string($cookies) && $cookies !== '' && is_file($cookies)) {
            array_push($command, '--cookies', $cookies);
        }

        $ffmpeg = $this->processes->resolve((string) config('clipflow.media.ffmpeg_binary'));

        if (is_file($ffmpeg)) {
            array_push($command, '--ffmpeg-location', $ffmpeg);
        }

        $command[] = $url;

        return $command;
    }

    private function formatSelector(): string
    {
        $custom = config('clipflow.download.format');

        if (is_string($custom) && trim($custom) !== '') {
            return trim($custom);
        }

        $height = max(144, (int) config('clipflow.download.max_height'));

        return sprintf(
            'bv*[height<=%1$d][ext=mp4]+ba[ext=m4a]/b[height<=%1$d][ext=mp4]/b[height<=%1$d]/b',
            $height,
        );
    }
}
