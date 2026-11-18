<?php

namespace App\Services\Ai;

use App\Services\SettingsService;

class AiChatClientFactory
{
    public function __construct(private readonly SettingsService $settings) {}

    public function make(): AiJsonChatClient
    {
        $baseUrl = $this->settings->aiBaseUrl();
        $model = $this->settings->aiModel();
        $timeout = $this->settings->aiTimeout();

        return match ($this->driver()) {
            'ollama' => new OllamaJsonChatClient($baseUrl, $model, $timeout),
            default => new OpenAiCompatibleJsonChatClient(
                $baseUrl,
                $model,
                $this->settings->aiApiKey(),
                $timeout,
                $this->settings->aiResponseFormat(),
            ),
        };
    }

    public function driver(): string
    {
        return $this->settings->aiDriver();
    }
}
