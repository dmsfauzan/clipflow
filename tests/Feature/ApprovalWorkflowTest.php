<?php

namespace Tests\Feature;

use App\Enums\ClipStatus;
use App\Enums\PublicationStatus;
use App\Exceptions\AuthorizationException;
use App\Livewire\ReviewList;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\SchedulingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsClipflowData;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use BuildsClipflowData, RefreshDatabase;

    public function test_clip_without_render_cannot_be_approved(): void
    {
        $user = $this->owner();
        $channel = $this->channelFor($user);
        $clip = $this->renderedClipFor($user, [
            'status' => ClipStatus::Review,
            'rendered_path' => null,
            'media_hash' => null,
            'rendered_at' => null,
        ]);

        $this->expectException(AuthorizationException::class);

        app(ApprovalService::class)->approve($clip, $user, $channel, 'private', false);
    }

    public function test_approval_requires_connected_channel(): void
    {
        $user = $this->owner();
        $channel = $this->channelFor($user, ['status' => 'disconnected', 'is_active' => false]);
        $clip = $this->renderedClipFor($user);

        $this->expectException(AuthorizationException::class);

        app(ApprovalService::class)->approve($clip, $user, $channel, 'private', false);
    }

    public function test_repeated_approval_does_not_create_duplicate(): void
    {
        $user = $this->owner();
        $channel = $this->channelFor($user);
        $clip = $this->renderedClipFor($user);
        $service = app(ApprovalService::class);

        $first = $service->approve($clip, $user, $channel, 'private', false);
        $second = $service->approve($clip->fresh(), $user, $channel, 'private', false);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('clip_approvals', 1);
        $this->assertSame(ClipStatus::Approved, $clip->fresh()->status);
    }

    public function test_content_change_after_approval_revokes_approval(): void
    {
        $user = $this->owner();
        $channel = $this->channelFor($user);
        $clip = $this->renderedClipFor($user);
        $service = app(ApprovalService::class);
        $approval = $service->approve($clip, $user, $channel, 'private', false);

        $service->updateContent($clip->fresh(), $user, 'Judul baru setelah approval', $clip->caption);

        $this->assertNotNull($approval->fresh()->revoked_at);
        $this->assertFalse($approval->fresh()->is_current);
        $this->assertSame(ClipStatus::Review, $clip->fresh()->status);
    }

    public function test_content_change_cancels_scheduled_job(): void
    {
        $user = $this->owner();
        $channel = $this->channelFor($user);
        $clip = $this->renderedClipFor($user);
        $approvals = app(ApprovalService::class);
        $scheduling = app(SchedulingService::class);

        $approval = $approvals->approve($clip, $user, $channel, 'private', false);
        $job = $scheduling->schedule($clip->fresh(), $approval, null, $user);

        $approvals->updateContent($clip->fresh(), $user, 'Judul diubah saat terjadwal', $clip->caption);

        $this->assertSame(PublicationStatus::Cancelled, $job->fresh()->status);
        $this->assertNull($job->fresh()->slot_key);
        $this->assertSame(ClipStatus::Review, $clip->fresh()->status);
    }

    public function test_batch_approval_only_processes_selected_clips(): void
    {
        $user = $this->owner();
        $channel = $this->channelFor($user);
        $selected = $this->renderedClipFor($user);
        $unselected = $this->renderedClipFor($user);

        Livewire::actingAs($user)
            ->test(ReviewList::class)
            ->set('channelId', $channel->id)
            ->set('privacy', 'private')
            ->set('selectedIds', [$selected->id])
            ->call('approveSelected')
            ->assertHasNoErrors();

        $this->assertSame(ClipStatus::Approved, $selected->fresh()->status);
        $this->assertSame(ClipStatus::Review, $unselected->fresh()->status);
        $this->assertDatabaseCount('clip_approvals', 1);
    }

    public function test_guest_cannot_open_clip_review(): void
    {
        $user = User::factory()->create();
        $clip = $this->renderedClipFor($user);

        $this->get(route('review.show', $clip))->assertRedirect(route('login'));
    }
}
