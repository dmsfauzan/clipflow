<?php

namespace Tests\Feature;

use App\Enums\ClipStatus;
use App\Enums\PublicationStatus;
use App\Exceptions\AuthorizationException;
use App\Models\PublicationJob;
use App\Services\ApprovalService;
use App\Services\SchedulingService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsClipflowData;
use Tests\TestCase;

class SchedulingServiceTest extends TestCase
{
    use BuildsClipflowData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'clipflow.publishing.youtube_daily_quota_units' => 1000000,
            'clipflow.publishing.youtube_upload_cost_units' => 1,
            'clipflow.publishing.youtube_quota_reserve_units' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_slot_is_always_in_the_future(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 5, 10, 7, 0, 'Asia/Jakarta'));

        $user = $this->owner();
        $channel = $this->channelFor($user);

        $slot = app(SchedulingService::class)->nextAvailableSlot($channel);

        $this->assertTrue($slot->greaterThan(now()));
        $this->assertSame('2026-01-05 10:30:00', $slot->format('Y-m-d H:i:s'));
    }

    public function test_thirty_first_slot_moves_to_next_day(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 5, 23, 0, 0, 'Asia/Jakarta'));

        $user = $this->owner();
        $channel = $this->channelFor($user);
        $clip = $this->renderedClipFor($user, ['status' => ClipStatus::Approved]);
        $approval = $this->approvalFor($clip, $channel);

        $tomorrow = Carbon::create(2026, 1, 6, 8, 0, 0, 'Asia/Jakarta');

        for ($index = 0; $index < 30; $index++) {
            $slot = $tomorrow->copy()->addMinutes($index * 30);

            PublicationJob::create([
                'clip_id' => $clip->id,
                'clip_approval_id' => $index === 0 ? $approval->id : $this->approvalFor($clip, $channel, ['approval_version' => $index + 1])->id,
                'publishing_channel_id' => $channel->id,
                'user_id' => $user->id,
                'status' => PublicationStatus::Scheduled,
                'scheduled_for' => $slot,
                'slot_date' => $slot->toDateString(),
                'slot_key' => PublicationJob::slotKey($channel->id, $slot),
                'privacy' => 'private',
                'made_for_kids' => false,
                'content_hash' => $approval->content_hash,
                'title' => 'Klip '.$index,
                'caption' => 'Caption',
            ]);
        }

        $next = app(SchedulingService::class)->nextAvailableSlot($channel);

        $this->assertSame('2026-01-07 08:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_schedule_requires_approval_and_creates_future_job(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 5, 9, 0, 0, 'Asia/Jakarta'));

        $user = $this->owner();
        $channel = $this->channelFor($user);
        $clip = $this->renderedClipFor($user);
        $approval = $this->approvalFor($clip, $channel);

        $this->expectException(AuthorizationException::class);
        app(SchedulingService::class)->schedule($clip, $approval, null, $user);
    }

    public function test_cancel_returns_clip_to_review_and_revokes_approval(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 5, 9, 0, 0, 'Asia/Jakarta'));

        $user = $this->owner();
        $channel = $this->channelFor($user);
        $clip = $this->renderedClipFor($user);
        $approvals = app(ApprovalService::class);
        $scheduling = app(SchedulingService::class);

        $approval = $approvals->approve($clip, $user, $channel, 'private', false);
        $job = $scheduling->schedule($clip->fresh(), $approval, null, $user);

        $scheduling->cancel($job, $user, 'Dibatalkan test');

        $this->assertSame(PublicationStatus::Cancelled, $job->fresh()->status);
        $this->assertNull($job->fresh()->slot_key);
        $this->assertSame(ClipStatus::Review, $clip->fresh()->status);
        $this->assertNotNull($approval->fresh()->revoked_at);
    }

    public function test_schedule_is_idempotent_for_same_approval(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 5, 9, 0, 0, 'Asia/Jakarta'));

        $user = $this->owner();
        $channel = $this->channelFor($user);
        $clip = $this->renderedClipFor($user);
        $approvals = app(ApprovalService::class);
        $scheduling = app(SchedulingService::class);

        $approval = $approvals->approve($clip, $user, $channel, 'private', false);
        $first = $scheduling->schedule($clip->fresh(), $approval, null, $user);
        $second = $scheduling->schedule($clip->fresh(), $approval->fresh(), null, $user);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('publication_jobs', 1);
    }

    public function test_daily_limit_is_respected_when_scheduling(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 5, 8, 0, 0, 'Asia/Jakarta'));

        $user = $this->owner();
        $channel = $this->channelFor($user);
        app(SettingsService::class)->set('publishing.daily_limit', 1);

        $clip = $this->renderedClipFor($user, ['status' => ClipStatus::Approved]);
        $approval = $this->approvalFor($clip, $channel);
        $this->publicationJobFor($clip, $approval, $channel, Carbon::create(2026, 1, 5, 9, 0, 0, 'Asia/Jakarta'));

        $next = app(SchedulingService::class)->nextAvailableSlot($channel);

        $this->assertSame('2026-01-06 08:00:00', $next->format('Y-m-d H:i:s'));
    }
}
