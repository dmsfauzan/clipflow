<?php

namespace Tests\Feature;

use App\Services\ClipflowNotifier;
use App\Services\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsClipflowData;
use Tests\TestCase;

class TelegramNotifierTest extends TestCase
{
    use BuildsClipflowData, RefreshDatabase;

    public function test_disabled_notifier_does_not_send_http_request(): void
    {
        config(['clipflow.notifications.telegram.enabled' => false]);
        Http::preventStrayRequests();

        $this->assertFalse(app(TelegramNotifier::class)->send('tes'));
    }

    public function test_enabled_notifier_sends_message_to_telegram(): void
    {
        config([
            'clipflow.notifications.telegram.enabled' => true,
            'clipflow.notifications.telegram.bot_token' => 'test-token',
            'clipflow.notifications.telegram.chat_id' => '12345',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $this->assertTrue(app(TelegramNotifier::class)->send('Halo <b>clipflow</b>'));

        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), '/bottest-token/sendMessage')
                && ($request['chat_id'] ?? null) === '12345'
                && str_contains((string) ($request['text'] ?? ''), 'clipflow');
        });
    }

    public function test_failed_response_returns_false_without_throwing(): void
    {
        config([
            'clipflow.notifications.telegram.enabled' => true,
            'clipflow.notifications.telegram.bot_token' => 'test-token',
            'clipflow.notifications.telegram.chat_id' => '12345',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => false], 401),
        ]);

        $this->assertFalse(app(TelegramNotifier::class)->send('tes'));
    }

    public function test_clip_ready_notification_escapes_title_and_contains_link(): void
    {
        config([
            'clipflow.notifications.telegram.enabled' => true,
            'clipflow.notifications.telegram.bot_token' => 'test-token',
            'clipflow.notifications.telegram.chat_id' => '12345',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $user = $this->owner();
        $source = $this->sourceFor($user, ['original_name' => 'Video <script> & Uji']);

        app(ClipflowNotifier::class)->clipsReady($source, 5, 1);

        Http::assertSent(function (Request $request): bool {
            $text = (string) ($request['text'] ?? '');

            return str_contains($text, '5 klip siap review')
                && str_contains($text, '&lt;script&gt;')
                && str_contains($text, route('review.index'))
                && str_contains($text, '1 klip gagal dirender');
        });
    }

    public function test_upload_notifications_include_reason(): void
    {
        config([
            'clipflow.notifications.telegram.enabled' => true,
            'clipflow.notifications.telegram.bot_token' => 'test-token',
            'clipflow.notifications.telegram.chat_id' => '12345',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $user = $this->owner();
        [$clip, $channel, $approval] = $this->approvedClipFor($user);
        $job = $this->publicationJobFor($clip, $approval, $channel, now()->subMinute());

        app(ClipflowNotifier::class)->uploadNeedsReview($job, 'Koneksi terputus');

        Http::assertSent(fn (Request $request): bool => str_contains((string) ($request['text'] ?? ''), 'Koneksi terputus'));
    }
}
