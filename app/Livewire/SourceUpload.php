<?php

namespace App\Livewire;

use App\Enums\VideoSourceStatus;
use App\Exceptions\MediaProcessingException;
use App\Jobs\DownloadRemoteSource;
use App\Jobs\ProbeVideoSource;
use App\Models\VideoSource;
use App\Services\ActivityLogger;
use App\Services\ImportUrlValidator;
use App\Services\RemoteImportService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\WithFileUploads;

class SourceUpload extends AuthenticatedComponent
{
    use WithFileUploads;

    public mixed $video = null;

    public string $sourceMode = 'upload';

    public string $sourceUrl = '';

    public bool $rightsConfirmed = false;

    public function updatedSourceMode(): void
    {
        $this->resetValidation();
    }

    public function save(ActivityLogger $activity): void
    {
        if ($this->sourceMode === 'url') {
            $this->importFromUrl($activity);

            return;
        }

        $maxKilobytes = (int) ceil(config('clipflow.media.max_upload_bytes') / 1024);

        $this->validate([
            'video' => ['required', 'file', 'mimes:mp4,mov,webm', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:'.$maxKilobytes],
            'rightsConfirmed' => ['accepted'],
        ], [
            'video.required' => 'Pilih file video terlebih dahulu.',
            'video.mimes' => 'Format harus MP4, MOV, atau WebM.',
            'video.mimetypes' => 'Tipe MIME file tidak didukung. Gunakan MP4, MOV, atau WebM yang valid.',
            'video.max' => 'Ukuran file melebihi batas unggah yang dikonfigurasi.',
            'rightsConfirmed.accepted' => 'Anda harus mengonfirmasi memiliki hak penggunaan video.',
        ]);

        $user = auth()->user();
        $extension = strtolower($this->video->getClientOriginalExtension());
        $path = $this->video->storeAs(
            'video-sources/'.$user->id,
            Str::uuid()->toString().'.'.$extension,
            'local',
        );

        if (! is_string($path)) {
            throw ValidationException::withMessages(['video' => 'File gagal disimpan ke storage privat.']);
        }

        $source = VideoSource::create([
            'user_id' => $user->id,
            'original_name' => Str::limit($this->video->getClientOriginalName(), 255, ''),
            'extension' => $extension,
            'mime_type' => (string) $this->video->getMimeType(),
            'size_bytes' => (int) $this->video->getSize(),
            'path' => $path,
            'disk' => 'local',
            'source_type' => 'upload',
            'status' => VideoSourceStatus::Uploaded,
            'rights_confirmed_at' => now(),
        ]);

        ProbeVideoSource::dispatch($source);
        $activity->log('source.uploaded', sprintf('Sumber "%s" diunggah ke storage privat.', $source->original_name), $source);

        $this->reset('video', 'rightsConfirmed');
        $this->redirect(route('sources.show', $source));
    }

    private function importFromUrl(ActivityLogger $activity): void
    {
        $this->validate([
            'sourceUrl' => ['required', 'string', 'max:2048'],
            'rightsConfirmed' => ['accepted'],
        ], [
            'sourceUrl.required' => 'Masukkan link video terlebih dahulu.',
            'rightsConfirmed.accepted' => 'Anda harus mengonfirmasi memiliki hak penggunaan video.',
        ]);

        $validator = app(ImportUrlValidator::class);

        try {
            $url = $validator->validate($this->sourceUrl);
        } catch (MediaProcessingException $exception) {
            $this->addError('sourceUrl', $exception->getMessage());

            return;
        }

        $hash = hash('sha256', $url);
        $user = auth()->user();

        $duplicate = VideoSource::query()
            ->where('user_id', $user->id)
            ->where('source_url_hash', $hash)
            ->whereNotIn('status', [VideoSourceStatus::Failed])
            ->exists();

        if ($duplicate) {
            $this->addError('sourceUrl', 'Link ini sudah ada di workspace dan masih diproses atau sudah selesai.');

            return;
        }

        $activeImports = VideoSource::query()
            ->where('user_id', $user->id)
            ->where('source_type', 'url')
            ->whereIn('status', [VideoSourceStatus::Queued, VideoSourceStatus::Downloading])
            ->count();

        if ($activeImports >= (int) config('clipflow.download.max_concurrent_per_user')) {
            $this->addError('sourceUrl', 'Batas impor link aktif untuk akun ini sudah tercapai. Tunggu proses selesai.');

            return;
        }

        $source = VideoSource::create([
            'user_id' => $user->id,
            'original_name' => 'Impor link — '.$validator->redacted($url),
            'extension' => 'mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 0,
            'path' => 'imports/'.$user->id.'/pending-'.$hash.'.mp4',
            'disk' => 'local',
            'source_type' => 'url',
            'source_url' => $url,
            'source_url_hash' => $hash,
            'status' => VideoSourceStatus::Queued,
            'processing_stage' => 'Menunggu unduhan',
            'rights_confirmed_at' => now(),
        ]);

        DownloadRemoteSource::dispatch($source);
        $activity->log('source.import_queued', 'Impor link dimasukkan ke antrean: '.$validator->redacted($url), $source);

        $this->reset('sourceUrl', 'rightsConfirmed');
        $this->redirect(route('sources.show', $source));
    }

    public function render(): View
    {
        return view('livewire.source-upload', [
            'maxBytes' => (int) config('clipflow.media.max_upload_bytes'),
            'maxDurationSeconds' => (int) config('clipflow.media.max_duration_seconds'),
            'ytdlpAvailable' => app(RemoteImportService::class)->isAvailable(),
        ]);
    }
}
