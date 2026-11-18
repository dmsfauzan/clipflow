<?php

namespace Tests\Feature;

use App\Enums\VideoSourceStatus;
use App\Exceptions\ProcessException;
use App\Jobs\DownloadRemoteSource;
use App\Jobs\ProbeVideoSource;
use App\Livewire\SourceUpload;
use App\Models\User;
use App\Models\VideoSource;
use App\Services\ImportUrlValidator;
use App\Services\ProcessRunner;
use App\Services\SourceRetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsClipflowData;
use Tests\TestCase;

class RemoteImportTest extends TestCase
{
    use BuildsClipflowData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['clipflow.download.dns_check' => false]);
        Storage::fake('local');
    }

    public function test_import_rejects_unsupported_scheme_and_private_hosts(): void
    {
        $user = User::factory()->create();
        Queue::fake();

        foreach (['ftp://example.com/video.mp4', 'http://localhost/video.mp4', 'http://127.0.0.1/video.mp4', 'http://192.168.1.10/video.mp4', 'bukan url'] as $url) {
            Livewire::actingAs($user)
                ->test(SourceUpload::class)
                ->set('sourceMode', 'url')
                ->set('sourceUrl', $url)
                ->set('rightsConfirmed', true)
                ->call('save')
                ->assertHasErrors(['sourceUrl']);
        }

        $this->assertDatabaseCount('video_sources', 0);
        Queue::assertNotPushed(DownloadRemoteSource::class);
    }

    public function test_import_requires_rights_confirmation(): void
    {
        $user = User::factory()->create();
        Queue::fake();

        Livewire::actingAs($user)
            ->test(SourceUpload::class)
            ->set('sourceMode', 'url')
            ->set('sourceUrl', 'https://www.youtube.com/watch?v=abc123')
            ->call('save')
            ->assertHasErrors(['rightsConfirmed']);

        Queue::assertNotPushed(DownloadRemoteSource::class);
    }

    public function test_import_creates_queued_source_and_dispatches_download(): void
    {
        $user = User::factory()->create();
        Queue::fake();

        Livewire::actingAs($user)
            ->test(SourceUpload::class)
            ->set('sourceMode', 'url')
            ->set('sourceUrl', 'https://www.youtube.com/watch?v=abc123')
            ->set('rightsConfirmed', true)
            ->call('save')
            ->assertHasNoErrors();

        $source = VideoSource::query()->firstOrFail();

        $this->assertSame('url', $source->source_type);
        $this->assertSame(VideoSourceStatus::Queued, $source->status);
        $this->assertNotNull($source->source_url_hash);
        Queue::assertPushed(DownloadRemoteSource::class);
    }

    public function test_duplicate_link_is_rejected_while_still_active(): void
    {
        $user = User::factory()->create();
        Queue::fake();

        $this->sourceFor($user, [
            'source_type' => 'url',
            'source_url' => 'https://www.youtube.com/watch?v=abc123',
            'source_url_hash' => hash('sha256', 'https://www.youtube.com/watch?v=abc123'),
            'status' => VideoSourceStatus::Queued,
        ]);

        Livewire::actingAs($user)
            ->test(SourceUpload::class)
            ->set('sourceMode', 'url')
            ->set('sourceUrl', 'https://www.youtube.com/watch?v=abc123')
            ->set('rightsConfirmed', true)
            ->call('save')
            ->assertHasErrors(['sourceUrl']);

        $this->assertDatabaseCount('video_sources', 1);
    }

    public function test_download_job_stores_file_and_dispatches_probe(): void
    {
        Queue::fake();
        $this->fakeYtDlp();

        $source = $this->queuedImportSource();

        $this->app->call([new DownloadRemoteSource($source), 'handle']);

        $source->refresh();

        $this->assertSame(VideoSourceStatus::Uploaded, $source->status);
        $this->assertStringStartsWith('video-sources/'.$source->user_id.'/', $source->path);
        $this->assertStringContainsString('Video Uji', $source->original_name);
        $this->assertSame(2048, $source->downloaded_bytes);
        $this->assertSame(100, $source->import_progress);
        $this->assertSame('Youtube', $source->metadata['import']['extractor'] ?? null);
        $this->assertNotNull($source->import_completed_at);
        Queue::assertPushed(ProbeVideoSource::class);
    }

    public function test_download_failure_marks_source_failed_without_probe(): void
    {
        Queue::fake();

        $this->instance(ProcessRunner::class, new class extends ProcessRunner
        {
            public function runStreaming(array $command, int $timeout, callable $onOutput, ?string $workingDirectory = null): string
            {
                throw new ProcessException('yt-dlp: video tidak tersedia');
            }
        });

        $source = $this->queuedImportSource();

        try {
            $this->app->call([new DownloadRemoteSource($source), 'handle']);
        } catch (\Throwable) {
            // kegagalan dicatat ke sumber, bukan dilempar ke pengguna
        }

        $source->refresh();

        $this->assertSame(VideoSourceStatus::Failed, $source->status);
        $this->assertStringContainsString('yt-dlp', (string) $source->failure_message);
        Queue::assertNotPushed(ProbeVideoSource::class);
    }

    public function test_retry_service_requeues_download_for_import_source(): void
    {
        Queue::fake();

        $source = $this->sourceFor(User::factory()->create(), [
            'source_type' => 'url',
            'source_url' => 'https://www.youtube.com/watch?v=abc123',
            'path' => 'imports/1/pending.mp4',
            'status' => VideoSourceStatus::Failed,
            'failure_message' => 'gagal',
        ]);

        app(SourceRetryService::class)->retry($source);

        $this->assertSame(VideoSourceStatus::Queued, $source->fresh()->status);
        Queue::assertPushed(DownloadRemoteSource::class);
        Queue::assertNotPushed(ProbeVideoSource::class);
    }

    public function test_redaction_hides_secret_query_parameters(): void
    {
        $redacted = app(ImportUrlValidator::class)->redacted('https://example.com/video?token=rahasia123');

        $this->assertStringNotContainsString('rahasia123', $redacted);
        $this->assertStringContainsString('example.com', $redacted);
    }

    private function queuedImportSource(): VideoSource
    {
        $user = User::factory()->create();

        return $this->sourceFor($user, [
            'source_type' => 'url',
            'source_url' => 'https://www.youtube.com/watch?v=abc123',
            'source_url_hash' => hash('sha256', 'https://www.youtube.com/watch?v=abc123'),
            'status' => VideoSourceStatus::Queued,
            'processing_stage' => 'Menunggu unduhan',
        ]);
    }

    private function fakeYtDlp(): void
    {
        $this->instance(ProcessRunner::class, new class extends ProcessRunner
        {
            public function runStreaming(array $command, int $timeout, callable $onOutput, ?string $workingDirectory = null): string
            {
                $outputTemplate = $command[array_search('-o', $command, true) + 1];
                $directory = dirname($outputTemplate);

                file_put_contents($directory.'/abc123.mp4', str_repeat('v', 2048));
                file_put_contents($directory.'/abc123.info.json', json_encode([
                    'id' => 'abc123',
                    'title' => 'Video Uji Impor',
                    'duration' => 90,
                    'extractor_key' => 'Youtube',
                    'uploader' => 'Kanal Uji',
                    'webpage_url' => 'https://www.youtube.com/watch?v=abc123',
                ]));

                $onOutput('out', "[download]  50.0% of 2.00MiB\n");
                $onOutput('out', "[download] 100.0% of 2.00MiB\n");

                return '';
            }
        });
    }
}
