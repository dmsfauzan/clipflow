<?php

namespace App\Services\Ai;

use App\Exceptions\ClipSelectionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class OpenAiCompatibleJsonChatClient implements AiJsonChatClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $modelName,
        private readonly ?string $apiKey,
        private readonly int $timeout,
        private readonly bool $jsonResponseFormat = true,
        private readonly float $temperature = 0.2,
    ) {}

    public function chat(string $prompt): string
    {
        if (! $this->hasCredential()) {
            throw new ClipSelectionException(
                'API key penyedia AI belum diisi. Set CLIPFLOW_AI_API_KEY di .env.',
                'ai_auth',
            );
        }

        $payload = [
            'model' => $this->modelName,
            'stream' => false,
            'temperature' => $this->temperature,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        if ($this->jsonResponseFormat) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = $this->request()->post($this->endpoint(), $payload);
        } catch (ConnectionException $exception) {
            throw new ClipSelectionException(
                'Tidak dapat terhubung ke penyedia AI. Periksa CLIPFLOW_AI_BASE_URL dan pastikan gateway berjalan.',
                'ai_unreachable',
                $exception,
            );
        }

        if ($response->failed()) {
            throw new ClipSelectionException(
                $this->failureMessage($response->status(), $response->json('error.message')),
                $this->failureCategory($response->status()),
            );
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new ClipSelectionException('Respons penyedia AI tidak berisi konten.', 'ai_response');
        }

        if ($response->json('choices.0.finish_reason') === 'length') {
            throw new ClipSelectionException(
                'Respons AI terpotong karena batas panjang. Kurangi kandidat per chunk atau gunakan model dengan output lebih besar.',
                'ai_truncated',
            );
        }

        return $content;
    }

    public function driver(): string
    {
        return '9router';
    }

    public function endpoint(): string
    {
        return rtrim($this->baseUrl, '/').'/chat/completions';
    }

    public function model(): string
    {
        return $this->modelName;
    }

    public function hasCredential(): bool
    {
        return is_string($this->apiKey) && trim($this->apiKey) !== '';
    }

    private function request(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->asJson()
            ->withToken((string) $this->apiKey)
            ->acceptJson();
    }

    private function failureCategory(int $status): string
    {
        return match (true) {
            in_array($status, [401, 403], true) => 'ai_auth',
            $status === 429 => 'ai_quota',
            $status >= 500 => 'ai_server',
            default => 'ai_request',
        };
    }

    private function failureMessage(int $status, mixed $message): string
    {
        $detail = is_string($message) && trim($message) !== '' ? ' '.mb_substr(trim($message), 0, 300) : '';

        if ($detail !== '' && is_string($this->apiKey) && $this->apiKey !== '') {
            $detail = str_replace($this->apiKey, '[redacted]', $detail);
        }

        return match (true) {
            in_array($status, [401, 403], true) => 'API key AI ditolak oleh penyedia. Periksa CLIPFLOW_AI_API_KEY.'.$detail,
            $status === 429 => 'Kuota atau limit penyedia AI tercapai.'.$detail,
            $status >= 500 => 'Penyedia AI mengalami gangguan (HTTP '.$status.').'.$detail,
            default => 'Penyedia AI menolak permintaan (HTTP '.$status.').'.$detail,
        };
    }
}
