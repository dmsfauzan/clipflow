<?php

namespace App\Services\Ai;

use App\Exceptions\ClipSelectionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OllamaJsonChatClient implements AiJsonChatClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $modelName,
        private readonly int $timeout,
        private readonly float $temperature = 0.2,
    ) {}

    public function chat(string $prompt): string
    {
        $endpoint = $this->endpoint();

        try {
            $response = Http::timeout($this->timeout)
                ->asJson()
                ->post($endpoint, [
                    'model' => $this->modelName,
                    'stream' => false,
                    'format' => 'json',
                    'options' => [
                        'temperature' => $this->temperature,
                    ],
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new ClipSelectionException(
                'Tidak dapat terhubung ke Ollama. Pastikan Ollama berjalan dan model sudah diunduh.',
                'ai_unreachable',
                $exception,
            );
        }

        if ($response->failed()) {
            throw new ClipSelectionException(
                sprintf('Ollama menolak permintaan (HTTP %s).', $response->status()),
                'ai_request',
            );
        }

        $content = $response->json('message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new ClipSelectionException('Respons Ollama tidak berisi konten.', 'ai_response');
        }

        return $content;
    }

    public function driver(): string
    {
        return 'ollama';
    }

    public function endpoint(): string
    {
        return rtrim($this->baseUrl, '/').'/api/chat';
    }

    public function model(): string
    {
        return $this->modelName;
    }

    public function hasCredential(): bool
    {
        return true;
    }
}
