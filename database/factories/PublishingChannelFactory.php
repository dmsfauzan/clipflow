<?php

namespace Database\Factories;

use App\Enums\ChannelStatus;
use App\Models\PublishingChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PublishingChannelFactory extends Factory
{
    protected $model = PublishingChannel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'youtube',
            'name' => 'Channel utama',
            'channel_identifier' => 'UC_TEST_'.fake()->hexColor(),
            'channel_title' => 'Channel YouTube Utama',
            'channel_handle' => '@channelutama',
            'avatar_url' => null,
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_type' => 'Bearer',
            'token_expires_at' => now()->addHour(),
            'scopes' => 'https://www.googleapis.com/auth/youtube.upload',
            'status' => ChannelStatus::Connected,
            'is_active' => true,
            'connected_at' => now(),
        ];
    }
}
