<?php

namespace App\Services;

use App\Enums\PublicationStatus;
use App\Enums\UploadAttemptStatus;
use App\Enums\UploadErrorCategory;
use App\Exceptions\PublishingException;
use App\Models\PublicationJob;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class YouTubePublishingService
{
    public function __construct(
        private readonly YouTubeOAuthService $oauth,
        private readonly ActivityLogger $activity,
    ) {}

    public function upload(PublicationJob $job): void
    {
        $job->loadMissing(['clip', 'approval', 'publishingChannel']);
        $attempt = null;

        try {
            $this->assertUploadReady($job);

            $attemptNumber = (int) $job->attempt_count + 1;
            $attempt = $job->attempts()->create([
                'attempt_number' => $attemptNumber,
                'status' => UploadAttemptStatus::Started,
                'started_at' => now(),
            ]);

            $job->update([
                'status' => PublicationStatus::Uploading,
                'attempt_count' => $attemptNumber,
                'last_attempted_at' => now(),
                'started_at' => now(),
            ]);

            $sessionUri = $job->resumable_session_uri ?: $this->initiateSession($job);
            $job->update(['resumable_session_uri' => $sessionUri]);

            $this->transferFile($job, $sessionUri);

            $attempt->update([
                'status' => UploadAttemptStatus::Succeeded,
                'finished_at' => now(),
            ]);
        } catch (PublishingException $exception) {
            $attempt?->update([
                'status' => $exception->uncertain ? UploadAttemptStatus::Uncertain : UploadAttemptStatus::Failed,
                'error_category' => $exception->errorCategory?->value,
                'http_status' => $exception->httpStatus,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            $job->update([
                'status' => $exception->uncertain ? PublicationStatus::NeedsReconciliation : PublicationStatus::Failed,
                'error_category' => $exception->errorCategory?->value,
                'last_error' => $exception->getMessage(),
            ]);

            if ($exception->uncertain) {
                $this->activity->log(
                    'publication.uncertain',
                    'Upload mungkin diterima YouTube; job ditandai perlu pemeriksaan dan tidak diulang otomatis.',
                    $job,
                );
            }

            throw $exception;
        } catch (\Throwable $exception) {
            $attempt?->update([
                'status' => UploadAttemptStatus::Uncertain,
                'error_category' => UploadErrorCategory::Uncertain->value,
                'error_message' => 'Status transfer tidak pasti; upload tidak diulang otomatis.',
                'finished_at' => now(),
            ]);
            $job->update([
                'status' => PublicationStatus::NeedsReconciliation,
                'error_category' => UploadErrorCategory::Uncertain->value,
                'last_error' => 'Status upload belum pasti. Gunakan pemeriksaan sebelum mencoba lagi.',
            ]);

            throw new PublishingException(
                'Status upload belum pasti karena gangguan koneksi. Sistem tidak mengulang otomatis.',
                UploadErrorCategory::Uncertain,
                true,
                null,
                null,
                $exception,
            );
        }
    }

    /**
     * Periksa status sesi resumable tanpa mengunggah ulang.
     *
     * @return array{state: string, next_offset: ?int, video_id: ?string}
     */
    public function checkStatus(PublicationJob $job): array
    {
        $job->loadMissing(['publishingChannel', 'clip']);
        $sessionUri = $job->resumable_session_uri;

        if (! is_string($sessionUri) || $sessionUri === '') {
            return ['state' => 'missing_session', 'next_offset' => null, 'video_id' => null];
        }

        $token = $this->oauth->accessToken($job->publishingChannel);
        $size = $this->fileSize($job);

        try {
            $response = Http::withToken($token)
                ->connectTimeout(10)
                ->timeout((int) config('clipflow.youtube.request_timeout'))
                ->withHeaders(['Content-Range' => 'bytes */'.$size])
                ->put($sessionUri);
        } catch (ConnectionException) {
            throw new PublishingException('Tidak dapat memeriksa status upload ke YouTube.', UploadErrorCategory::Network);
        }

        if ($response->successful()) {
            $videoId = $this->videoId($response);

            if ($videoId !== null) {
                $this->markUploaded($job, $videoId);

                return ['state' => 'complete', 'next_offset' => null, 'video_id' => $videoId];
            }

            return ['state' => 'unknown', 'next_offset' => null, 'video_id' => null];
        }

        if ($response->status() === 308) {
            return ['state' => 'incomplete', 'next_offset' => $this->nextOffset($response), 'video_id' => null];
        }

        if (in_array($response->status(), [404, 410], true)) {
            return ['state' => 'expired', 'next_offset' => null, 'video_id' => null];
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new PublishingException('Izin YouTube tidak valid saat pemeriksaan upload.', UploadErrorCategory::Auth, false, $response->status());
        }

        throw new PublishingException('Pemeriksaan upload gagal (HTTP '.$response->status().').', UploadErrorCategory::Network, false, $response->status());
    }

    /**
     * Lanjutkan sesi resumable yang sudah ada tanpa membuat upload baru.
     */
    public function resume(PublicationJob $job): void
    {
        $status = $this->checkStatus($job);

        if ($status['state'] === 'complete') {
            return;
        }

        if ($status['state'] === 'expired' || $status['state'] === 'missing_session') {
            throw new PublishingException(
                'Sesi upload tidak tersedia lagi. Periksa YouTube Studio secara manual sebelum mengunggah ulang.',
                UploadErrorCategory::Uncertain,
            );
        }

        $offset = $status['next_offset'] ?? 0;
        $sessionUri = (string) $job->resumable_session_uri;

        $attemptNumber = (int) $job->attempt_count + 1;
        $attempt = $job->attempts()->create([
            'attempt_number' => $attemptNumber,
            'status' => UploadAttemptStatus::Started,
            'started_at' => now(),
        ]);

        $job->update([
            'status' => PublicationStatus::Uploading,
            'attempt_count' => $attemptNumber,
            'last_attempted_at' => now(),
        ]);

        try {
            $this->transferFile($job, $sessionUri, $offset);

            $attempt->update(['status' => UploadAttemptStatus::Succeeded, 'finished_at' => now()]);
        } catch (PublishingException $exception) {
            $attempt->update([
                'status' => $exception->uncertain ? UploadAttemptStatus::Uncertain : UploadAttemptStatus::Failed,
                'error_category' => $exception->errorCategory?->value,
                'http_status' => $exception->httpStatus,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
            $job->update([
                'status' => $exception->uncertain ? PublicationStatus::NeedsReconciliation : PublicationStatus::Failed,
                'error_category' => $exception->errorCategory?->value,
                'last_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return array{state: string, next_offset: ?int, video_id: ?string}
     */
    public function reconcile(PublicationJob $job): array
    {
        return $this->checkStatus($job);
    }

    private function initiateSession(PublicationJob $job): string
    {
        $token = $this->oauth->accessToken($job->publishingChannel);
        $size = $this->fileSize($job);

        $metadata = [
            'snippet' => [
                'title' => $job->title,
                'description' => $job->caption ?? '',
                'categoryId' => '22',
            ],
            'status' => [
                'privacyStatus' => $job->privacy->value,
                'selfDeclaredMadeForKids' => $job->made_for_kids,
            ],
        ];

        try {
            $response = Http::withToken($token)
                ->connectTimeout(10)
                ->timeout((int) config('clipflow.youtube.request_timeout'))
                ->withHeaders([
                    'X-Upload-Content-Length' => (string) $size,
                    'X-Upload-Content-Type' => 'video/mp4',
                ])
                ->withBody(json_encode($metadata, JSON_THROW_ON_ERROR), 'application/json')
                ->post(config('clipflow.youtube.upload_url').'?uploadType=resumable&part=snippet,status');
        } catch (ConnectionException $exception) {
            throw new PublishingException('Koneksi ke YouTube gagal saat memulai upload.', UploadErrorCategory::Network, false, null, null, $exception);
        }

        if ($response->failed()) {
            throw $this->exceptionFromResponse($response, false);
        }

        $location = $response->header('Location');

        if (! is_string($location) || $location === '') {
            throw new PublishingException('YouTube tidak mengembalikan sesi upload resumable.', UploadErrorCategory::Validation);
        }

        return $location;
    }

    private function transferFile(PublicationJob $job, string $sessionUri, int $offset = 0): void
    {
        $path = Storage::disk('local')->path((string) $job->clip->rendered_path);
        $size = $this->fileSize($job);
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new PublishingException('File render tidak dapat dibuka.', UploadErrorCategory::Validation);
        }

        if ($offset > 0) {
            fseek($handle, $offset);
        }

        $token = $this->oauth->accessToken($job->publishingChannel);
        $remaining = $size - $offset;

        try {
            $response = Http::withToken($token)
                ->connectTimeout(10)
                ->timeout((int) config('clipflow.youtube.upload_timeout'))
                ->withHeaders([
                    'Content-Type' => 'video/mp4',
                    'Content-Length' => (string) $remaining,
                    'Content-Range' => sprintf('bytes %d-%d/%d', $offset, $size - 1, $size),
                ])
                ->withBody($handle, 'video/mp4')
                ->put($sessionUri);
        } catch (ConnectionException $exception) {
            throw new PublishingException(
                'Koneksi terputus saat transfer video. Upload tidak akan diulang tanpa pemeriksaan.',
                UploadErrorCategory::Uncertain,
                true,
                null,
                null,
                $exception,
            );
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($response->successful()) {
            $videoId = $this->videoId($response);

            if ($videoId === null) {
                throw new PublishingException('YouTube menerima respons tanpa ID video.', UploadErrorCategory::Uncertain, true, $response->status());
            }

            $this->markUploaded($job, $videoId);

            return;
        }

        if ($response->status() === 308) {
            $next = $this->nextOffset($response);

            throw new PublishingException(
                $next === null
                    ? 'YouTube belum menyelesaikan pemrosesan upload.'
                    : sprintf('Upload belum selesai (byte %d).', $next),
                UploadErrorCategory::Network,
                true,
                $response->status(),
            );
        }

        throw $this->exceptionFromResponse($response, true);
    }

    private function markUploaded(PublicationJob $job, string $videoId): void
    {
        $job->update([
            'status' => PublicationStatus::Uploaded,
            'youtube_video_id' => $videoId,
            'uploaded_at' => now(),
            'last_error' => null,
            'error_category' => null,
        ]);

        $job->clip->update(['status' => 'uploaded']);

        $this->activity->log(
            'publication.uploaded',
            sprintf('YouTube menerima video untuk klip "%s". Status "uploaded" berarti API sudah mengembalikan ID, bukan jaminan video sudah publik.', $job->title),
            $job,
            ['youtube_video_id' => $videoId],
        );
    }

    private function exceptionFromResponse(Response $response, bool $duringTransfer): PublishingException
    {
        $status = $response->status();
        $message = (string) ($response->json('error.message') ?? 'YouTube menolak permintaan upload.');

        $category = match (true) {
            in_array($status, [401, 403], true) => UploadErrorCategory::Auth,
            $status === 429 => UploadErrorCategory::Quota,
            $status >= 500 && $duringTransfer => UploadErrorCategory::Uncertain,
            $status >= 500 => UploadErrorCategory::Network,
            $status >= 400 && $status < 500 => UploadErrorCategory::Validation,
            default => UploadErrorCategory::Unknown,
        };

        $uncertain = $duringTransfer && in_array($status, [500, 502, 503, 504], true);

        return new PublishingException(
            Str::limit($message, 1000),
            $category,
            $uncertain,
            $status,
        );
    }

    private function nextOffset(Response $response): ?int
    {
        $range = $response->header('Range');

        if (! is_string($range) || ! preg_match('/bytes=0-(\d+)/', $range, $matches)) {
            return null;
        }

        return (int) $matches[1] + 1;
    }

    private function videoId(Response $response): ?string
    {
        $id = $response->json('id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function fileSize(PublicationJob $job): int
    {
        $path = Storage::disk('local')->path((string) $job->clip->rendered_path);
        $size = is_file($path) ? filesize($path) : false;

        if ($size === false || $size <= 0) {
            throw new PublishingException('File render tidak tersedia.', UploadErrorCategory::Validation);
        }

        return (int) $size;
    }

    private function assertUploadReady(PublicationJob $job): void
    {
        if (! in_array($job->status, [PublicationStatus::Scheduled, PublicationStatus::Uploading], true)) {
            throw new PublishingException('Job publikasi tidak berada dalam status terjadwal.', UploadErrorCategory::Conflict);
        }

        if ($job->scheduled_for->isFuture()) {
            throw new PublishingException('Waktu publikasi belum jatuh tempo.', UploadErrorCategory::Validation);
        }

        if (! $job->approval->isValidFor($job->clip)) {
            throw new PublishingException('Approval sudah tidak valid untuk konten ini.', UploadErrorCategory::Validation);
        }

        if (! hash_equals((string) $job->content_hash, (string) $job->approval->content_hash)) {
            throw new PublishingException('Snapshot publikasi tidak cocok dengan approval.', UploadErrorCategory::Validation);
        }

        if ($job->approval->publishing_channel_id !== $job->publishing_channel_id) {
            throw new PublishingException('Channel tujuan tidak cocok dengan approval.', UploadErrorCategory::Validation);
        }

        if (! $job->publishingChannel->isConnected()) {
            throw new PublishingException('Channel YouTube tidak terhubung.', UploadErrorCategory::Auth);
        }

        $this->fileSize($job);

        $path = Storage::disk('local')->path((string) $job->clip->rendered_path);
        $actualHash = hash_file('sha256', $path);

        if (! is_string($job->clip->media_hash) || ! hash_equals($job->clip->media_hash, (string) $actualHash)) {
            throw new PublishingException('File render berubah sejak approval. Review ulang diperlukan.', UploadErrorCategory::Validation);
        }
    }
}
