<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramNotifier
{
    public function configured(): bool
    {
        return (bool) config('clipflow.notifications.telegram.enabled')
            && is_string(config('clipflow.notifications.telegram.bot_token'))
            && trim((string) config('clipflow.notifications.telegram.bot_token')) !== ''
            && is_string(config('clipflow.notifications.telegram.chat_id'))
            && trim((string) config('clipflow.notifications.telegram.chat_id')) !== '';
    }

    public function send(string $message): bool
    {
        if (! $this->configured()) {
            return false;
        }

        $token = (string) config('clipflow.notifications.telegram.bot_token');

        try {
            $response = Http::timeout(8)
                ->connectTimeout(3)
                ->post('https://api.telegram.org/bot'.$token.'/sendMessage', [
                    'chat_id' => (string) config('clipflow.notifications.telegram.chat_id'),
                    'text' => Str::limit($message, 3800, '…'),
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);
        } catch (\Throwable $exception) {
            Log::warning('Telegram notification failed', ['error' => $exception->getMessage()]);

            return false;
        }

        if ($response->failed()) {
            Log::warning('Telegram notification rejected', ['status' => $response->status()]);

            return false;
        }

        return true;
    }

    public function test(): bool
    {
        return $this->send('✅ <b>clipflow.</b> — notifikasi Telegram aktif.');
    }
}
