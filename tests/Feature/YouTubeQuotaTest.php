<?php

namespace Tests\Feature;

use App\Enums\PublicationStatus;
use App\Jobs\UploadPublication;
use App\Models\UploadAttempt;
use App\Services\SchedulingService;
use App\Services\SettingsService;
use App\Services\YouTubeQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsClipflowData;
use Tests\TestCase;

class YouTubeQuotaTest extends TestCase
{
    use BuildsClipflowData, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_capacity_is_derived_from_quota_and_upload_cost(): void
    {
        $quota = app(YouTubeQuotaService::class);

        $this->assertSame(10000, $quota->dailyUnits());
        $this->assertSame(1600, $quota->uploadCost());
        $this->assertSame(6, $quota->capacity());
        $this->assertSame(6, $quota->effectiveDailyLimit(30));
        $this->assertSame(2, $quota->effectiveDailyLimit(2));
    }

    public function test_used_units_are_counted_from_attempts_today(): void
    {
        $user = $this->owner();
        [$clip, $channel, $approval] = $this->approvedClipFor($user);
        $job = $this->publicationJobFor($clip, $approval, $channel, now()->subHour());

        foreach (range(1, 2) as $number) {
            UploadAttempt::create([
                'publication_job_id' => $job->id,
                'attempt_number' => $number,
                'status' => 'failed',
                'started_at' => now(),
            ]);
        }

        $quota = app(YouTubeQuotaService::class);

        $this->assertSame(3200, $quota->usedToday());
        $this->assertSame(6600, $quota->remaining());
        $this->assertTrue($quota->canUpload());
    }

    public function test_scheduling_respects_quota_capacity_even_if_daily_limit_is_high(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 5, 23, 0, 0, 'Asia/Jakarta'));

        app(SettingsService::class)->setMany([
            'publishing.daily_limit' => 30,
            'publishing.quota_daily_units' => 3200,
            'publishing.quota_upload_cost' => 1600,
            'publishing.quota_reserve' => 0,
        ]);

        $user = $this->owner();
        $channel = $this->channelFor($user);
        $clip = $this->renderedClipFor($user, ['status' => 'approved']);
        $approval = $this->approvalFor($clip, $channel);

        $tomorrow = Carbon::create(2026, 1, 6, 8, 0, 0, 'Asia/Jakarta');

        foreach ([0, 1] as $index) {
            $slot = $tomorrow->copy()->addMinutes($index * 30);
            $this->publicationJobFor($clip, $approval, $channel, $slot, [
                'clip_approval_id' => $index === 0
                    ? $approval->id
                    : $this->approvalFor($clip, $channel, ['approval_version' => 2])->id,
            ]);
        }

        $next = app(SchedulingService::class)->nextAvailableSlot($channel);

        $this->assertSame('2026-01-07 08:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_upload_job_is_rescheduled_when_quota_is_exhausted(): void
    {
        Http::preventStrayRequests();
        Queue::fake();

        app(SettingsService::class)->setMany([
            'publishing.quota_daily_units' => 3200,
            'publishing.quota_upload_cost' => 1600,
            'publishing.quota_reserve' => 0,
        ]);

        $user = $this->owner();
        [$clip, $channel, $approval] = $this->approvedClipFor($user);
        $job = $this->publicationJobFor($clip, $approval, $channel, now()->subMinute());

        foreach ([1, 2] as $number) {
            UploadAttempt::create([
                'publication_job_id' => $job->id,
                'attempt_number' => $number,
                'status' => 'failed',
                'started_at' => now(),
            ]);
        }

        $this->app->call([new UploadPublication($job), 'handle']);

        $fresh = $job->fresh();

        $this->assertSame(PublicationStatus::Scheduled, $fresh->status);
        $this->assertStringContainsString('Kuota', (string) $fresh->last_error);
        $this->assertTrue($fresh->scheduled_for->greaterThan(now()));
        Queue::assertPushed(UploadPublication::class, 1);
    }
}
