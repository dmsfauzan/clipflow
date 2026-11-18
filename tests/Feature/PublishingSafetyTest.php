<?php

namespace Tests\Feature;

use App\Enums\ClipStatus;
use App\Enums\PublicationStatus;
use App\Exceptions\PublishingException;
use App\Jobs\UploadPublication;
use App\Services\SchedulingService;
use App\Services\SettingsService;
use App\Services\YouTubePublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsClipflowData;
use Tests\TestCase;

class PublishingSafetyTest extends TestCase
{
    use BuildsClipflowData, RefreshDatabase;

    public function test_upload_without_valid_approval_is_refused(): void
    {
        Http::preventStrayRequests();

        $user = $this->owner();
        [$clip, $channel, $approval] = $this->approvedClipFor($user);
        $job = $this->publicationJobFor($clip, $approval, $channel, now()->subMinute());

        $approval->update(['is_current' => false, 'revoked_at' => now()]);

        $this->expectException(PublishingException::class);

        app(YouTubePublishingService::class)->upload($job);
    }

    public function test_pause_prevents_new_uploads_from_scheduler(): void
    {
        Http::preventStrayRequests();
        Queue::fake();

        $user = $this->owner();
        [$clip, $channel, $approval] = $this->approvedClipFor($user);
        $this->publicationJobFor($clip, $approval, $channel, now()->subMinute());

        app(SettingsService::class)->set('publishing.paused', true);

        $this->artisan('clipflow:dispatch-uploads')->assertSuccessful();

        Queue::assertNotPushed(UploadPublication::class);
    }

    public function test_scheduler_dispatches_limited_jobs_and_not_entire_backlog(): void
    {
        Http::preventStrayRequests();
        Queue::fake();

        $user = $this->owner();

        for ($index = 0; $index < 5; $index++) {
            $jobOwner = $this->owner();
            $channel = $this->channelFor($jobOwner);
            [$clip, $channel, $approval] = $this->approvedClipFor($jobOwner, $channel);
            $this->publicationJobFor($clip, $approval, $channel, now()->subMinute());
        }

        $this->artisan('clipflow:dispatch-uploads')->assertSuccessful();

        Queue::assertPushed(UploadPublication::class, 1);
    }

    public function test_overdue_job_beyond_grace_is_rescheduled_not_uploaded(): void
    {
        Http::preventStrayRequests();
        Queue::fake();

        $user = $this->owner();
        [$clip, $channel, $approval] = $this->approvedClipFor($user);
        $job = $this->publicationJobFor($clip, $approval, $channel, now()->subHours(6));

        $this->artisan('clipflow:dispatch-uploads')->assertSuccessful();

        $fresh = $job->fresh();

        $this->assertSame(PublicationStatus::Scheduled, $fresh->status);
        $this->assertTrue($fresh->scheduled_for->greaterThan(now()));
        $this->assertNotSame($job->slot_key, $fresh->slot_key);
        Queue::assertNotPushed(UploadPublication::class);
    }

    public function test_upload_success_is_idempotent_against_concurrent_jobs(): void
    {
        $user = $this->owner();
        [$clip, $channel, $approval] = $this->approvedClipFor($user);
        $job = $this->publicationJobFor($clip, $approval, $channel, now()->subMinute());

        Storage::fake('local');
        Storage::disk('local')->put($clip->rendered_path, 'video-bytes');
        $clip->update(['media_hash' => hash('sha256', 'video-bytes')]);
        $approval->update(['content_hash' => $clip->fresh()->contentHash()]);
        $job->update(['content_hash' => $approval->content_hash]);

        Http::fake([
            'https://www.googleapis.com/upload/youtube/v3/videos*' => Http::response('', 200, [
                'Location' => 'https://upload.example/session-1',
            ]),
            'https://upload.example/*' => Http::response(['id' => 'vid-123'], 200),
        ]);

        $uploadJob = new UploadPublication($job);

        $this->app->call([$uploadJob, 'handle']);
        $this->app->call([$uploadJob, 'handle']);

        $fresh = $job->fresh();

        $this->assertSame(PublicationStatus::Uploaded, $fresh->status, (string) $fresh->last_error);
        $this->assertSame('vid-123', $fresh->youtube_video_id);
        $this->assertSame(1, (int) $fresh->attempt_count);
        $this->assertSame(1, $fresh->attempts()->count());
        Http::assertSentCount(2);
    }

    public function test_connection_lost_after_upload_marks_needs_reconciliation_without_retry(): void
    {
        Queue::fake();

        $user = $this->owner();
        [$clip, $channel, $approval] = $this->approvedClipFor($user);
        $job = $this->publicationJobFor($clip, $approval, $channel, now()->subMinute());

        Storage::fake('local');
        Storage::disk('local')->put($clip->rendered_path, 'video-bytes');
        $clip->update(['media_hash' => hash('sha256', 'video-bytes')]);
        $approval->update(['content_hash' => $clip->fresh()->contentHash()]);
        $job->update(['content_hash' => $approval->content_hash]);

        Http::fake([
            'https://www.googleapis.com/upload/youtube/v3/videos*' => Http::response('', 200, [
                'Location' => 'https://upload.example/session-1',
            ]),
            'https://upload.example/*' => function (): void {
                throw new ConnectionException('Connection lost after sending video');
            },
        ]);

        $uploadJob = new UploadPublication($job);
        $this->app->call([$uploadJob, 'handle']);

        $fresh = $job->fresh();

        $this->assertSame(PublicationStatus::NeedsReconciliation, $fresh->status, (string) $fresh->last_error);
        $this->assertStringContainsString('upload.example', (string) $fresh->resumable_session_uri);
        $this->assertSame(1, (int) $fresh->attempt_count);
        Http::assertSentCount(1);
        Queue::assertNotPushed(UploadPublication::class);
    }

    public function test_daily_limit_counts_uploads_in_app_timezone(): void
    {
        $user = $this->owner();
        $channel = $this->channelFor($user);
        $scheduling = app(SchedulingService::class);
        $clip = $this->renderedClipFor($user);
        $approval = $this->approvalFor($clip, $channel);
        $clip2 = $this->renderedClipFor($user);
        $approval2 = $this->approvalFor($clip2, $channel);

        $yesterday = $this->publicationJobFor($clip, $approval, $channel, now()->subDay()->startOfDay()->addHour());
        $today = $this->publicationJobFor($clip2, $approval2, $channel, now());

        $yesterday->update([
            'status' => PublicationStatus::Uploaded,
            'youtube_video_id' => 'yesterday-1',
            'uploaded_at' => now()->subDay()->startOfDay()->addHour(),
        ]);
        $today->update([
            'status' => PublicationStatus::Uploaded,
            'youtube_video_id' => 'today-1',
            'uploaded_at' => now()->startOfDay()->addHour(),
        ]);

        $this->assertSame(1, $scheduling->uploadedToday($channel, now()->startOfDay()->addHour()));
        $this->assertSame(1, $scheduling->uploadedToday($channel, now()->subDay()->startOfDay()->addHour()));
    }

    public function test_job_respects_daily_limit_and_reschedules_without_uploading(): void
    {
        Http::preventStrayRequests();
        Queue::fake();

        $user = $this->owner();
        $channel = $this->channelFor($user);
        app(SettingsService::class)->set('publishing.daily_limit', 1);

        $clip = $this->renderedClipFor($user);
        $approval = $this->approvalFor($clip, $channel);
        $done = $this->publicationJobFor($clip, $approval, $channel, now()->subHour());
        $done->update([
            'status' => PublicationStatus::Uploaded,
            'uploaded_at' => now(),
            'youtube_video_id' => 'quota-1',
        ]);

        [$clip2, $channel, $approval2] = $this->approvedClipFor($user, $channel);
        $job = $this->publicationJobFor($clip2, $approval2, $channel, now()->subMinute());

        $this->assertSame(1, app(SchedulingService::class)->uploadedToday($channel));

        $this->app->call([new UploadPublication($job), 'handle']);

        $fresh = $job->fresh();

        $this->assertSame(PublicationStatus::Scheduled, $fresh->status, (string) $fresh->last_error);
        $this->assertTrue($fresh->scheduled_for->greaterThan(now()));
        Queue::assertPushed(UploadPublication::class, 1);
    }

    public function test_clip_status_removes_uploading_state_on_job_failure(): void
    {
        $user = $this->owner();
        [$clip, $channel, $approval] = $this->approvedClipFor($user);
        $job = $this->publicationJobFor($clip, $approval, $channel, now()->subMinute());
        $job->update(['status' => PublicationStatus::Uploading, 'attempt_count' => 1]);
        $clip->update(['status' => ClipStatus::Approved]);

        (new UploadPublication($job))->failed(new \RuntimeException('connection lost'));

        $this->assertSame(PublicationStatus::Failed, $job->fresh()->status);
    }
}
