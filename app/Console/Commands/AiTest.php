<?php

namespace App\Console\Commands;

use App\Exceptions\ClipSelectionException;
use App\Services\Ai\AiChatClientFactory;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AiTest extends Command
{
    protected $signature = 'clipflow:ai-test {--prompt= : Prompt kustom untuk uji koneksi}';

    protected $description = 'Uji koneksi driver AI aktif (Ollama atau 9router/OpenAI-compatible)';

    public function handle(AiChatClientFactory $clients, SettingsService $settings): int
    {
        $client = $clients->make();

        $this->line('Driver   : '.$client->driver());
        $this->line('Endpoint : '.$client->endpoint());
        $this->line('Model    : '.$client->model());
        $this->line('API key  : '.($client->hasCredential() ? 'terisi' : 'belum diisi'));

        $prompt = (string) ($this->option('prompt') ?: 'Balas hanya JSON: {"ok":true,"sumber":"clipflow"}');

        try {
            $content = $client->chat($prompt);
        } catch (ClipSelectionException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Koneksi AI berhasil.');
        $this->line('Balasan  : '.Str::limit(trim($content), 500, '…'));

        $decoded = json_decode(trim($content), true);
        $this->line('JSON     : '.(is_array($decoded) ? 'valid' : 'tidak valid (tetap ditangani oleh validator AI)'));

        if ($settings->aiDriver() === '9router' && ! $client->hasCredential()) {
            $this->warn('API key kosong; permintaan chat akan ditolak gateway.');
        }

        return self::SUCCESS;
    }
}
