<?php

namespace App\Services;

use App\Exceptions\TranscriptionException;
use App\Models\Transcript;
use App\Models\VideoSource;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TranscriptionService
{
    public function __construct(
        private readonly ProcessRunner $processes,
        private readonly MediaProbeService $mediaProbe,
    ) {}

    public function transcribe(VideoSource $source): Transcript
    {
        $source->update([
            'status' => 'transcribing',
            'processing_stage' => 'Ekstrak audio',
            'failure_message' => null,
        ]);

        $inputPath = $this->storagePath($source->disk, $source->path);
        $workDirectory = storage_path('app/private/work/'.$source->id);
        $audioPath = $workDirectory.'/audio.wav';
        $outputPath = $workDirectory.'/transcript.json';
        File::ensureDirectoryExists($workDirectory);

        try {
            $this->mediaProbe->extractAudio($inputPath, $audioPath);
            $source->update(['processing_stage' => 'Transkripsi bahasa Indonesia']);
            $this->processes->run([
                config('clipflow.transcription.python_binary'),
                config('clipflow.transcription.script'),
                '--input', $audioPath,
                '--output', $outputPath,
                '--model', config('clipflow.transcription.model'),
                '--language', config('clipflow.transcription.language'),
                '--device', config('clipflow.transcription.device'),
                '--compute-type', config('clipflow.transcription.compute_type'),
                '--cpu-threads', (string) config('clipflow.transcription.cpu_threads'),
            ], config('clipflow.transcription.timeout'));

            $result = json_decode((string) file_get_contents($outputPath), true);

            if (! is_array($result) || ! is_string($result['text'] ?? null) || ! is_array($result['segments'] ?? null)) {
                throw new TranscriptionException('Output transkripsi tidak memiliki struktur yang valid.', 'transcription_output');
            }

            $segments = $this->normalizeSegments($result['segments']);
            $transcript = Transcript::updateOrCreate(
                ['video_source_id' => $source->id],
                [
                    'language' => config('clipflow.transcription.language'),
                    'model' => config('clipflow.transcription.model'),
                    'full_text' => trim($result['text']),
                    'segments' => $segments,
                    'status' => 'completed',
                    'error_message' => null,
                    'completed_at' => now(),
                ],
            );

            $source->update([
                'processing_stage' => 'Transkripsi selesai',
                'processed_at' => now(),
            ]);

            return $transcript;
        } catch (TranscriptionException $exception) {
            $this->markFailed($source, $exception->getMessage());
            throw $exception;
        } catch (\Throwable $exception) {
            $this->markFailed($source, 'Transkripsi gagal: '.$exception->getMessage());
            throw new TranscriptionException('Transkripsi gagal. Periksa Python dan faster-whisper.', 'transcription', $exception);
        } finally {
            File::deleteDirectory($workDirectory);
        }
    }

    /**
     * @param  array<int, mixed>  $segments
     * @return array<int, array{start: float, end: float, text: string}>
     */
    private function normalizeSegments(array $segments): array
    {
        $normalized = [];

        foreach ($segments as $segment) {
            if (! is_array($segment) || ! is_numeric($segment['start'] ?? null) || ! is_numeric($segment['end'] ?? null)) {
                continue;
            }

            $text = trim((string) ($segment['text'] ?? ''));

            if ($text === '' || (float) $segment['end'] <= (float) $segment['start']) {
                continue;
            }

            $normalized[] = [
                'start' => round((float) $segment['start'], 3),
                'end' => round((float) $segment['end'], 3),
                'text' => Str::limit($text, 1000, ''),
            ];
        }

        if ($normalized === []) {
            throw new TranscriptionException('Transkripsi tidak menghasilkan segmen bertimestamp.', 'transcription_output');
        }

        return $normalized;
    }

    private function storagePath(string $disk, string $path): string
    {
        return Storage::disk($disk)->path($path);
    }

    private function markFailed(VideoSource $source, string $message): void
    {
        $source->update([
            'status' => 'failed',
            'processing_stage' => 'Gagal',
            'failure_message' => Str::limit($message, 2000),
        ]);

        $source->transcript?->update([
            'status' => 'failed',
            'error_message' => Str::limit($message, 2000),
        ]);
    }
}
