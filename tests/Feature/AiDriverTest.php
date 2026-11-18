<?php

namespace Tests\Feature;

use App\Exceptions\ClipSelectionException;
use App\Models\Transcript;
use App\Models\VideoSource;
use App\Services\Ai\AiChatClientFactory;
use App\Services\Ai\OllamaJsonChatClient;
use App\Services\Ai\OpenAiCompatibleJsonChatClient;
use App\Services\ClipSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsClipflowData;
use Tests\TestCase;

class AiDriverTest extends TestCase
{
    use BuildsClipflowData, RefreshDatabase;

    public function test_factory_selects_client_from_config(): void
    {
        config(['clipflow.ai.driver' => 'ollama']);
        $this->assertInstanceOf(OllamaJsonChatClient::class, app(AiChatClientFactory::class)->make());

        config(['clipflow.ai.driver' => '9router']);
        $this->assertInstanceOf(OpenAiCompatibleJsonChatClient::class, app(AiChatClientFactory::class)->make());
    }

    public function test_openai_compatible_driver_sends_bearer_and_json_mode(): void
    {
        config([
            'clipflow.ai.driver' => '9router',
            'clipflow.ai.base_url' => 'https://ai.example/v1',
            'clipflow.ai.model' => 'assist',
            'clipflow.ai.api_key' => 'secret-key-123',
        ]);

        Http::fake([
            'https://ai.example/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => '{"clips":[]}']],
                ],
            ]),
        ]);

        [$source, $transcript] = $this->sourceWithTranscript();

        $candidates = app(ClipSelectionService::class)->selectForSource($source, $transcript);

        $this->assertSame([], $candidates);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://ai.example/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer secret-key-123')
                && ($request['response_format']['type'] ?? null) === 'json_object'
                && ($request['model'] ?? null) === 'assist';
        });
    }

    public function test_missing_api_key_raises_clear_error_without_http_call(): void
    {
        config([
            'clipflow.ai.driver' => '9router',
            'clipflow.ai.base_url' => 'https://ai.example/v1',
        ]);
        putenv('CLIPFLOW_AI_API_KEY');
        config(['clipflow.ai.api_key' => null]);

        Http::preventStrayRequests();

        [$source, $transcript] = $this->sourceWithTranscript();

        $this->expectException(ClipSelectionException::class);
        $this->expectExceptionMessage('API key');

        app(ClipSelectionService::class)->selectForSource($source, $transcript);
    }

    public function test_code_fence_wrapped_json_is_accepted(): void
    {
        config([
            'clipflow.ai.driver' => '9router',
            'clipflow.ai.base_url' => 'https://ai.example/v1',
            'clipflow.ai.api_key' => 'secret-key-123',
        ]);

        Http::fake([
            'https://ai.example/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => "```json\n{\"clips\":[]}\n```"]],
                ],
            ]),
        ]);

        [$source, $transcript] = $this->sourceWithTranscript();

        $this->assertSame([], app(ClipSelectionService::class)->selectForSource($source, $transcript));
    }

    public function test_auth_error_does_not_leak_api_key(): void
    {
        config([
            'clipflow.ai.driver' => '9router',
            'clipflow.ai.base_url' => 'https://ai.example/v1',
            'clipflow.ai.api_key' => 'secret-key-123',
        ]);

        Http::fake([
            'https://ai.example/v1/chat/completions' => Http::response([
                'error' => ['message' => 'Invalid key secret-key-123'],
            ], 401),
        ]);

        [$source, $transcript] = $this->sourceWithTranscript();

        try {
            app(ClipSelectionService::class)->selectForSource($source, $transcript);
            $this->fail('Pengecualian auth seharusnya dilempar.');
        } catch (ClipSelectionException $exception) {
            $this->assertStringContainsString('API key', $exception->getMessage());
            $this->assertStringNotContainsString('secret-key-123', $exception->getMessage());
        }
    }

    public function test_test_connection_command_reports_success(): void
    {
        config([
            'clipflow.ai.driver' => '9router',
            'clipflow.ai.base_url' => 'https://ai.example/v1',
            'clipflow.ai.model' => 'assist',
            'clipflow.ai.api_key' => 'secret-key-123',
        ]);

        Http::fake([
            'https://ai.example/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '{"ok":true}']]],
            ]),
        ]);

        $this->artisan('clipflow:ai-test')->assertSuccessful();
    }

    /**
     * @return array{0: VideoSource, 1: Transcript}
     */
    private function sourceWithTranscript(): array
    {
        $source = $this->sourceFor($this->owner(), ['duration_seconds' => 120]);

        $transcript = $source->transcript()->create([
            'language' => 'id',
            'model' => 'test',
            'full_text' => 'Konten test',
            'segments' => [
                ['start' => 0, 'end' => 30, 'text' => 'Segmen pertama untuk kandidat klip.'],
                ['start' => 30, 'end' => 60, 'text' => 'Segmen kedua untuk kandidat klip.'],
                ['start' => 60, 'end' => 90, 'text' => 'Segmen ketiga untuk kandidat klip.'],
            ],
            'status' => 'completed',
        ]);

        return [$source->fresh(), $transcript];
    }
}
