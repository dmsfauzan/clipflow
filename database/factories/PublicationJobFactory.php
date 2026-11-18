<?php

namespace Database\Factories;

use App\Enums\PublicationPrivacy;
use App\Enums\PublicationStatus;
use App\Models\Clip;
use App\Models\ClipApproval;
use App\Models\PublicationJob;
use App\Models\PublishingChannel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class PublicationJobFactory extends Factory
{
    protected $model = PublicationJob::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clip = Clip::factory()->create();

        $scheduledFor = Carbon::parse(fake()->dateTimeBetween('+1 hour', '+3 days'));

        return [
            'clip_id' => $clip->id,
            'clip_approval_id' => ClipApproval::factory()->for($clip)->create(['user_id' => $clip->user_id])->id,
            'publishing_channel_id' => PublishingChannel::factory(),
            'user_id' => $clip->user_id,
            'status' => PublicationStatus::Scheduled,
            'scheduled_for' => $scheduledFor,
            'slot_date' => $scheduledFor->toDateString(),
            'slot_key' => 'slot-'.fake()->uuid(),
            'privacy' => PublicationPrivacy::Private,
            'made_for_kids' => false,
            'content_hash' => hash('sha256', 'test-content'),
            'title' => $clip->title,
            'caption' => $clip->caption,
        ];
    }

    /**
     * Publikasi dengan relasi yang benar untuk satu klip, approval, dan channel.
     */
    public function forCombination(Clip $clip, ClipApproval $approval, PublishingChannel $channel, Carbon $scheduledFor): static
    {
        return $this->state(fn (): array => [
            'clip_id' => $clip->id,
            'clip_approval_id' => $approval->id,
            'publishing_channel_id' => $channel->id,
            'user_id' => $clip->user_id,
            'scheduled_for' => $scheduledFor,
            'slot_date' => $scheduledFor->toDateString(),
            'slot_key' => PublicationJob::slotKey($channel->id, $scheduledFor),
            'content_hash' => $approval->content_hash,
            'title' => $clip->title,
            'caption' => $clip->caption,
        ]);
    }
}
