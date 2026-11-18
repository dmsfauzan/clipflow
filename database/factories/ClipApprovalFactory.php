<?php

namespace Database\Factories;

use App\Models\Clip;
use App\Models\ClipApproval;
use App\Models\PublishingChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClipApprovalFactory extends Factory
{
    protected $model = ClipApproval::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clip_id' => Clip::factory(),
            'user_id' => User::factory(),
            'publishing_channel_id' => PublishingChannel::factory(),
            'approval_version' => 1,
            'content_hash' => hash('sha256', 'test-content'),
            'privacy' => 'private',
            'made_for_kids' => false,
            'approved_at' => now(),
            'is_current' => true,
        ];
    }
}
