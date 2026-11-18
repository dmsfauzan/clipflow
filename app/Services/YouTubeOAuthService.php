<?php

namespace App\Services;

use App\Enums\ChannelStatus;
use App\Exceptions\PublishingException;
use App\Models\PublishingChannel;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class YouTubeOAuthService
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function isConfigured(): bool
    {
        return (bool) config('clipflow.youtube.client_id') && (bool) config('clipflow.youtube.client_secret');
    }

    public function authorizationUrl(string $state): string
    {
        $this->assertConfigured();

        $query = [
            'client_id' => config('clipflow.youtube.client_id'),
            'redirect_uri' => config('clipflow.youtube.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', config('clipflow.youtube.scopes')),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ];

        return (string) config('clipflow.youtube.auth_url').'?'.http_build_query($query);
    }

    /**
     * Tukar kode otorisasi dengan token Google.
     *
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, token_type: ?string, scope: ?string}
     */
    public function exchangeCode(string $code): array
    {
        $this->assertConfigured();

        try {
            $response = Http::asForm()
                ->connectTimeout(10)
                ->timeout((int) config('clipflow.youtube.request_timeout'))
                ->post(config('clipflow.youtube.token_url'), [
                    'client_id' => config('clipflow.youtube.client_id'),
                    'client_secret' => config('clipflow.youtube.client_secret'),
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => config('clipflow.youtube.redirect_uri'),
                ]);
        } catch (ConnectionException $exception) {
            throw new PublishingException('Koneksi ke Google gagal saat menukar kode otorisasi.', null, false, null, null, $exception);
        }

        if ($response->failed()) {
            throw new PublishingException('Google menolak permintaan token. Periksa konfigurasi klien OAuth.');
        }

        $data = $response->json();

        if (! is_array($data) || ! is_string($data['access_token'] ?? null)) {
            throw new PublishingException('Respons token Google tidak valid.');
        }

        return [
            'access_token' => (string) $data['access_token'],
            'refresh_token' => is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : null,
            'expires_in' => (int) ($data['expires_in'] ?? 3600),
            'token_type' => is_string($data['token_type'] ?? null) ? $data['token_type'] : null,
            'scope' => is_string($data['scope'] ?? null) ? $data['scope'] : null,
        ];
    }

    /**
     * @return array{channel_identifier: string, title: string, handle: ?string, avatar: ?string, description: ?string}|null
     */
    public function fetchChannelIdentity(string $accessToken): ?array
    {
        $response = Http::withToken($accessToken)
            ->connectTimeout(10)
            ->timeout((int) config('clipflow.youtube.request_timeout'))
            ->get(config('clipflow.youtube.api_url').'/channels', [
                'part' => 'snippet,contentDetails,statistics',
                'mine' => 'true',
                'maxResults' => 1,
            ]);

        if ($response->failed()) {
            return null;
        }

        $channel = $response->json('items.0');

        if (! is_array($channel)) {
            return null;
        }

        $snippet = $channel['snippet'] ?? [];

        return [
            'channel_identifier' => (string) ($channel['id'] ?? ''),
            'title' => (string) ($snippet['title'] ?? 'Channel YouTube'),
            'handle' => isset($snippet['customUrl']) ? (string) $snippet['customUrl'] : null,
            'avatar' => $snippet['thumbnails']['medium']['url'] ?? null,
            'description' => $snippet['description'] ?? null,
        ];
    }

    public function accessToken(PublishingChannel $channel): string
    {
        if ($channel->access_token === null) {
            throw new PublishingException('Channel belum terhubung ke YouTube.');
        }

        if ($channel->token_expires_at !== null && $channel->token_expires_at->gt(now()->addMinutes(5))) {
            return $channel->access_token;
        }

        if ($channel->refresh_token === null) {
            throw new PublishingException('Token akses kedaluwarsa dan refresh token tidak tersedia. Hubungkan ulang channel.');
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(10)
                ->timeout((int) config('clipflow.youtube.request_timeout'))
                ->post(config('clipflow.youtube.token_url'), [
                    'client_id' => config('clipflow.youtube.client_id'),
                    'client_secret' => config('clipflow.youtube.client_secret'),
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $channel->refresh_token,
                ]);
        } catch (ConnectionException $exception) {
            throw new PublishingException('Koneksi ke Google gagal saat memperbarui token.', null, false, null, null, $exception);
        }

        if ($response->failed()) {
            $channel->forceFill(['last_error' => 'Refresh token gagal pada '.now()->toDateTimeString()])->save();

            throw new PublishingException('Gagal memperbarui token YouTube. Hubungkan ulang channel.');
        }

        $data = $response->json();

        if (! is_array($data) || ! is_string($data['access_token'] ?? null)) {
            throw new PublishingException('Respons refresh token Google tidak valid.');
        }

        $channel->update([
            'access_token' => $data['access_token'],
            'token_type' => is_string($data['token_type'] ?? null) ? $data['token_type'] : $channel->token_type,
            'token_expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 3600)),
            'last_refreshed_at' => now(),
            'last_error' => null,
        ]);

        return $channel->access_token;
    }

    public function connect(PublishingChannel $channel, array $token, ?array $identity, User $actor): PublishingChannel
    {
        if ($identity === null || ($identity['channel_identifier'] ?? '') === '') {
            throw new PublishingException('Tidak dapat membaca identitas channel YouTube. Periksa izin akun Google.');
        }

        $channel->update([
            'name' => $identity['title'],
            'channel_identifier' => $identity['channel_identifier'],
            'channel_title' => $identity['title'],
            'channel_handle' => $identity['handle'],
            'avatar_url' => $identity['avatar'],
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? $channel->refresh_token,
            'token_type' => $token['token_type'],
            'token_expires_at' => now()->addSeconds($token['expires_in']),
            'scopes' => $token['scope'],
            'status' => ChannelStatus::Connected,
            'is_active' => true,
            'connected_at' => now(),
            'last_error' => null,
        ]);

        $this->activity->log('channel.connected', sprintf('Channel %s terhubung.', $channel->channel_title), $channel, [], $actor);

        return $channel->fresh();
    }

    public function disconnect(PublishingChannel $channel, User $actor): void
    {
        $channel->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'scopes' => null,
            'status' => ChannelStatus::Disconnected,
            'is_active' => false,
        ]);

        $this->activity->log('channel.disconnected', sprintf('Channel %s diputus.', $channel->name), $channel, [], $actor);
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new PublishingException('Kredensial Google OAuth belum dikonfigurasi di .env.');
        }
    }
}
