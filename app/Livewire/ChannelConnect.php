<?php

namespace App\Livewire;

use App\Models\PublishingChannel;
use App\Services\ActivityLogger;
use App\Services\YouTubeOAuthService;
use Illuminate\View\View;
use Livewire\Component;

class ChannelConnect extends Component
{
    public function disconnect(int $channelId, YouTubeOAuthService $oauth, ActivityLogger $activity): void
    {
        $channel = PublishingChannel::query()->where('user_id', auth()->id())->findOrFail($channelId);
        $oauth->disconnect($channel, auth()->user());

        $this->dispatch('toast', message: 'Channel YouTube diputus. Token dihapus dari server.');
    }

    public function render(YouTubeOAuthService $oauth): View
    {
        $channel = PublishingChannel::query()->firstOrCreate(
            ['user_id' => auth()->id(), 'provider' => 'youtube'],
            ['name' => 'Channel YouTube utama'],
        );

        return view('livewire.channel-connect', [
            'provider' => [
                'id' => (int) $channel->id,
                'name' => (string) ($channel->channel_title ?: $channel->name),
                'identifier' => (string) ($channel->channel_identifier ?? ''),
                'handle' => (string) ($channel->channel_handle ?? ''),
                'avatar' => $channel->avatar_url,
                'is_connected' => $channel->isConnected(),
                'status_label' => $channel->status->label(),
                'connected_at' => $channel->connected_at?->format('d M Y H:i'),
                'token_expires_at' => $channel->token_expires_at?->format('d M Y H:i'),
                'last_refreshed_at' => $channel->last_refreshed_at?->format('d M Y H:i'),
            ],
            'oauthConfigured' => $oauth->isConfigured(),
            'scopes' => config('clipflow.youtube.scopes'),
        ]);
    }
}
