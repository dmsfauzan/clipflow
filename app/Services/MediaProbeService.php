<?php

namespace App\Services;

use App\Exceptions\MediaProcessingException;
use App\Exceptions\ProcessException;
use Illuminate\Support\Facades\File;

class MediaProbeService
{
    public function __construct(private readonly ProcessRunner $processes) {}

    /**
     * @return array{
     *     duration_seconds: float,
     *     width: int,
     *     height: int,
     *     has_video: bool,
     *     has_audio: bool,
     *     video_codec: ?string,
     *     audio_codec: ?string,
     *     container: ?string
     * }
     */
    public function probe(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            throw new MediaProcessingException('File video tidak ditemukan di storage privat.');
        }

        try {
            $output = $this->processes->run([
                config('clipflow.media.ffprobe_binary'),
                '-v', 'error',
                '-print_format', 'json',
                '-show_format',
                '-show_streams',
                $absolutePath,
            ], config('clipflow.media.probe_timeout'));
        } catch (ProcessException $exception) {
            throw new MediaProcessingException(
                'FFprobe tidak dapat membaca file. Pastikan FFmpeg terpasang dan file tidak korup.',
                'media_probe',
                $exception,
            );
        }

        $data = json_decode($output, true);

        if (! is_array($data)) {
            throw new MediaProcessingException('Output FFprobe tidak valid.', 'media_probe');
        }

        $streams = $data['streams'] ?? [];
        $videoStream = collect($streams)->firstWhere('codec_type', 'video');
        $audioStream = collect($streams)->firstWhere('codec_type', 'audio');

        if ($videoStream === null) {
            throw new MediaProcessingException('File tidak memiliki stream video yang valid.', 'media_probe');
        }

        $duration = $this->durationFrom($data, $videoStream);

        if ($duration <= 0) {
            throw new MediaProcessingException('Durasi video tidak dapat ditentukan.', 'media_probe');
        }

        if ($audioStream === null) {
            return [
                'duration_seconds' => round($duration, 3),
                'width' => (int) ($videoStream['width'] ?? 0),
                'height' => (int) ($videoStream['height'] ?? 0),
                'has_video' => true,
                'has_audio' => false,
                'video_codec' => $videoStream['codec_name'] ?? null,
                'audio_codec' => null,
                'container' => $data['format']['format_name'] ?? null,
            ];
        }

        return [
            'duration_seconds' => round($duration, 3),
            'width' => (int) ($videoStream['width'] ?? 0),
            'height' => (int) ($videoStream['height'] ?? 0),
            'has_video' => true,
            'has_audio' => true,
            'video_codec' => $videoStream['codec_name'] ?? null,
            'audio_codec' => $audioStream['codec_name'] ?? null,
            'container' => $data['format']['format_name'] ?? null,
        ];
    }

    public function extractAudio(string $inputPath, string $outputPath): void
    {
        File::ensureDirectoryExists(dirname($outputPath));

        try {
            $this->processes->run([
                config('clipflow.media.ffmpeg_binary'),
                '-hide_banner',
                '-loglevel', 'error',
                '-y',
                '-i', $inputPath,
                '-vn',
                '-ac', '1',
                '-ar', '16000',
                '-c:a', 'pcm_s16le',
                $outputPath,
            ], config('clipflow.media.extract_timeout'));
        } catch (ProcessException $exception) {
            throw new MediaProcessingException(
                'Ekstraksi audio gagal. Periksa instalasi FFmpeg dan format video sumber.',
                'audio_extraction',
                $exception,
            );
        }

        if (! is_file($outputPath) || filesize($outputPath) === 0) {
            throw new MediaProcessingException('File audio hasil ekstraksi kosong.', 'audio_extraction');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $videoStream
     */
    private function durationFrom(array $data, array $videoStream): float
    {
        $candidates = [
            $data['format']['duration'] ?? null,
            $videoStream['duration'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (float) $candidate > 0) {
                return (float) $candidate;
            }
        }

        return 0.0;
    }
}
