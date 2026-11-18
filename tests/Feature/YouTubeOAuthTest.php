<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsClipflowData;
use Tests\TestCase;

class YouTubeOAuthTest extends TestCase
{
    use BuildsClipflowData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'clipflow.youtube.client_id' => 'client-id-test',
            'clipflow.youtube.client_secret' => 'client-secret-test',
            'clipflow.youtube.redirect_uri' => 'http://localhost:8000/channels/youtube/callback',
        ]);
    }

    public function test_redirect_contains_state_and_no_secrets(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('channels.youtube.redirect'));

        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
        $this->assertStringContainsString('state=', $response->headers->get('Location'));
        $this->assertStringNotContainsString('client-secret-test', $response->headers->get('Location'));
        $this->assertNotEmpty(session('youtube_oauth_state'));
    }

    public function test_callback_with_wrong_state_does_not_connect(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('channels.youtube.redirect'));

        $response = $this->get(route('channels.youtube.callback', [
            'state' => 'wrong-state',
            'code' => 'code-123',
        ]));

        $response->assertRedirect(route('channels.youtube'));
        $this->assertDatabaseCount('publishing_channels', 0);
    }

    public function test_callback_connects_channel_without_exposing_tokens(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token-123',
                'refresh_token' => 'refresh-token-123',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
                'scope' => 'https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.readonly',
            ]),
            'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    ['id' => 'UC_TEST_1', 'snippet' => ['title' => 'Channel Tes', 'customUrl' => '@tes']],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);
        $this->withoutExceptionHandling();

        $redirect = $this->get(route('channels.youtube.redirect'));
        parse_str((string) parse_url((string) $redirect->headers->get('Location'), PHP_URL_QUERY), $query);
        $state = (string) ($query['state'] ?? '');

        $this->get(route('channels.youtube.callback', [
            'state' => $state,
            'code' => 'code-123',
        ]))->assertRedirect(route('channels.youtube'));

        $channel = $user->publishingChannels()->firstOrFail();

        $this->assertSame('connected', $channel->status->value);
        $this->assertNotEquals('access-token-123', DB::table('publishing_channels')->where('id', $channel->id)->value('access_token'));
        $this->assertArrayNotHasKey('access_token', $channel->toArray());
        $this->assertArrayNotHasKey('refresh_token', $channel->toArray());

        $html = (string) $this->get(route('channels.youtube'))->getContent();
        $position = strpos($html, 'access-token-123');

        $this->assertFalse(
            $position !== false,
            $position !== false ? 'Token bocor pada konteks: '.substr($html, max(0, $position - 400), 800) : '',
        );
    }

    public function test_disconnect_removes_tokens(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $channel = $this->channelFor($user);
        $this->actingAs($user);

        $this->post(route('channels.youtube.disconnect', $channel))->assertRedirect();

        $this->assertNull($channel->fresh()->access_token);
        $this->assertSame('disconnected', $channel->fresh()->status->value);
    }
}
