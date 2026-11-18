<?php

namespace Tests\Concerns;

use App\Enums\ClipStatus;
use App\Enums\PublicationStatus;
use App\Models\Clip;
use App\Models\ClipApproval;
use App\Models\PublicationJob;
use App\Models\PublishingChannel;
use App\Models\User;
use App\Models\VideoSource;
use Illuminate\Support\Carbon;

trait BuildsClipflowData
{
    protected function owner(): User
    {
        return User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function channelFor(User $user, array $attributes = []): PublishingChannel
    {
        return PublishingChannel::factory()->create(array_merge([
            'user_id' => $user->id,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function sourceFor(User $user, array $attributes = []): VideoSource
    {
        return VideoSource::factory()->create(array_merge([
            'user_id' => $user->id,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function renderedClipFor(User $user, array $attributes = []): Clip
    {
        $source = VideoSource::factory()->create(['user_id' => $user->id]);

        return Clip::factory()->create(array_merge([
            'video_source_id' => $source->id,
            'user_id' => $user->id,
            'status' => ClipStatus::Review,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function approvalFor(Clip $clip, PublishingChannel $channel, array $attributes = []): ClipApproval
    {
        return ClipApproval::create(array_merge([
            'clip_id' => $clip->id,
            'user_id' => $clip->user_id,
            'publishing_channel_id' => $channel->id,
            'approval_version' => 1,
            'content_hash' => $clip->contentHash(),
            'privacy' => 'private',
            'made_for_kids' => false,
            'approved_at' => now(),
            'is_current' => true,
        ], $attributes));
    }

    /**
     * @return array{0: Clip, 1: PublishingChannel, 2: ClipApproval}
     */
    protected function approvedClipFor(User $user, ?PublishingChannel $channel = null): array
    {
        $clip = $this->renderedClipFor($user);
        $channel ??= $this->channelFor($user);
        $approval = $this->approvalFor($clip, $channel);
        $clip->update(['status' => ClipStatus::Approved]);

        return [$clip->fresh(), $channel, $approval];
    }

    protected function publicationJobFor(
        Clip $clip,
        ClipApproval $approval,
        PublishingChannel $channel,
        ?Carbon $scheduledFor = null,
        array $attributes = [],
    ): PublicationJob {
        $scheduledFor ??= now()->addHour();

        return PublicationJob::create(array_merge([
            'clip_id' => $clip->id,
            'clip_approval_id' => $approval->id,
            'publishing_channel_id' => $channel->id,
            'user_id' => $clip->user_id,
            'status' => PublicationStatus::Scheduled,
            'scheduled_for' => $scheduledFor,
            'slot_date' => $scheduledFor->copy()->timezone(config('clipflow.timezone'))->toDateString(),
            'slot_key' => PublicationJob::slotKey($channel->id, $scheduledFor),
            'privacy' => 'private',
            'made_for_kids' => false,
            'content_hash' => $approval->content_hash,
            'title' => $clip->title,
            'caption' => $clip->caption,
        ], $attributes));
    }
}
