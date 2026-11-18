<?php

namespace App\Livewire;

use App\Services\ActivityLogger;
use App\Services\ProcessRunner;
use App\Services\SettingsService;
use App\Services\SubtitleTemplateService;
use App\Services\TelegramNotifier;
use App\Services\YouTubeOAuthService;
use App\Services\YouTubeQuotaService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingsForm extends AuthenticatedComponent
{
    public int $dailyLimit = 30;

    public string $slotStart = '08:00';

    public string $slotEnd = '22:30';

    public int $slotInterval = 30;

    public bool $autoSchedule = false;

    public string $defaultPrivacy = 'private';

    public string $whisperModel = 'small';

    public string $aiDriver = 'ollama';

    public string $aiBaseUrl = '';

    public string $aiModel = '';

    public float $minClipSeconds = 15;

    public float $maxClipSeconds = 60;

    public int $maxCandidates = 30;

    public int $renderWidth = 720;

    public int $renderHeight = 1280;

    public int $retentionSourceDays = 0;

    public int $retentionClipDays = 0;

    public int $quotaDailyUnits = 10000;

    public int $quotaUploadCost = 1600;

    public int $quotaReserve = 200;

    public function mount(SettingsService $settings): void
    {
        $this->dailyLimit = (int) $settings->get('publishing.daily_limit');
        $this->slotStart = $settings->string('publishing.slot_start');
        $this->slotEnd = $settings->string('publishing.slot_end');
        $this->slotInterval = (int) $settings->get('publishing.slot_interval_minutes');
        $this->autoSchedule = $settings->boolean('publishing.auto_schedule');
        $this->defaultPrivacy = $settings->string('publishing.default_privacy');
        $this->whisperModel = $settings->string('pipeline.whisper_model');
        $this->aiDriver = $settings->aiDriver();
        $this->aiBaseUrl = $settings->aiBaseUrl();
        $this->aiModel = $settings->aiModel();
        $this->minClipSeconds = (float) $settings->get('pipeline.min_clip_seconds');
        $this->maxClipSeconds = (float) $settings->get('pipeline.max_clip_seconds');
        $this->maxCandidates = (int) $settings->get('pipeline.max_candidates');
        $this->renderWidth = (int) $settings->get('pipeline.render_width');
        $this->renderHeight = (int) $settings->get('pipeline.render_height');
        $this->retentionSourceDays = (int) $settings->get('retention.source_days');
        $this->retentionClipDays = (int) $settings->get('retention.clip_days');
        $this->quotaDailyUnits = (int) $settings->get('publishing.quota_daily_units');
        $this->quotaUploadCost = (int) $settings->get('publishing.quota_upload_cost');
        $this->quotaReserve = (int) $settings->get('publishing.quota_reserve');
    }

    public function save(SettingsService $settings, ActivityLogger $activity): void
    {
        $this->validate([
            'dailyLimit' => ['required', 'integer', 'min:1', 'max:30'],
            'slotStart' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'slotEnd' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'slotInterval' => ['required', 'integer', 'min:5', 'max:240'],
            'defaultPrivacy' => ['required', 'in:private,unlisted,public'],
            'whisperModel' => ['required', 'string', 'max:64'],
            'aiDriver' => ['required', 'in:ollama,9router'],
            'aiBaseUrl' => ['required', 'url', 'max:255'],
            'aiModel' => ['required', 'string', 'max:128'],
            'minClipSeconds' => ['required', 'numeric', 'min:5', 'max:120'],
            'maxClipSeconds' => ['required', 'numeric', 'min:10', 'max:240'],
            'maxCandidates' => ['required', 'integer', 'min:1', 'max:30'],
            'renderWidth' => ['required', 'integer', 'min:360', 'max:1920'],
            'renderHeight' => ['required', 'integer', 'min:640', 'max:1920'],
            'retentionSourceDays' => ['required', 'integer', 'min:0', 'max:3650'],
            'retentionClipDays' => ['required', 'integer', 'min:0', 'max:3650'],
            'quotaDailyUnits' => ['required', 'integer', 'min:0', 'max:10000000'],
            'quotaUploadCost' => ['required', 'integer', 'min:1', 'max:100000'],
            'quotaReserve' => ['required', 'integer', 'min:0', 'max:1000000'],
        ], [
            'slotStart.regex' => 'Jam mulai format HH:MM.',
            'slotEnd.regex' => 'Jam akhir format HH:MM.',
            'aiDriver.in' => 'Driver AI harus ollama atau 9router.',
        ], [
            'dailyLimit' => 'Batas harian',
            'slotStart' => 'Jam mulai',
            'slotEnd' => 'Jam akhir',
            'slotInterval' => 'Interval slot',
            'whisperModel' => 'Model Whisper',
            'aiDriver' => 'Driver AI',
            'aiBaseUrl' => 'Base URL AI',
            'aiModel' => 'Model AI',
            'minClipSeconds' => 'Durasi minimal klip',
            'maxClipSeconds' => 'Durasi maksimal klip',
            'maxCandidates' => 'Kandidat per sumber',
            'renderWidth' => 'Lebar render',
            'renderHeight' => 'Tinggi render',
            'retentionSourceDays' => 'Retensi sumber',
            'retentionClipDays' => 'Retensi klip',
            'quotaDailyUnits' => 'Kuota harian',
            'quotaUploadCost' => 'Biaya upload',
            'quotaReserve' => 'Reserve kuota',
        ]);

        if ($this->slotStart >= $this->slotEnd) {
            $this->addError('slotEnd', 'Jam akhir harus setelah jam mulai.');

            return;
        }

        if ($this->maxClipSeconds <= $this->minClipSeconds) {
            $this->addError('maxClipSeconds', 'Durasi maksimal harus lebih besar dari durasi minimal.');

            return;
        }

        $settings->setMany([
            'publishing.daily_limit' => $this->dailyLimit,
            'publishing.slot_start' => $this->slotStart,
            'publishing.slot_end' => $this->slotEnd,
            'publishing.slot_interval_minutes' => $this->slotInterval,
            'publishing.auto_schedule' => $this->autoSchedule,
            'publishing.default_privacy' => $this->defaultPrivacy,
            'pipeline.whisper_model' => $this->whisperModel,
            'pipeline.ai_driver' => $this->aiDriver,
            'pipeline.ai_base_url' => rtrim($this->aiBaseUrl, '/'),
            'pipeline.ai_model' => $this->aiModel,
            'pipeline.ollama_base_url' => rtrim($this->aiBaseUrl, '/'),
            'pipeline.ollama_model' => $this->aiModel,
            'pipeline.min_clip_seconds' => $this->minClipSeconds,
            'pipeline.max_clip_seconds' => $this->maxClipSeconds,
            'pipeline.max_candidates' => $this->maxCandidates,
            'pipeline.render_width' => $this->renderWidth,
            'pipeline.render_height' => $this->renderHeight,
            'retention.source_days' => $this->retentionSourceDays,
            'retention.clip_days' => $this->retentionClipDays,
            'publishing.quota_daily_units' => $this->quotaDailyUnits,
            'publishing.quota_upload_cost' => $this->quotaUploadCost,
            'publishing.quota_reserve' => $this->quotaReserve,
        ]);

        Cache::forget('clipflow.ai.status');

        $activity->log('settings.updated', 'Pengaturan pipeline dan publikasi diperbarui.');
        $this->dispatch('toast', message: 'Pengaturan disimpan.');
    }

    public function testAiConnection(): void
    {
        $base = rtrim($this->aiBaseUrl, '/');

        try {
            if ($this->aiDriver === 'ollama') {
                $response = Http::baseUrl($base)->connectTimeout(3)->timeout(10)->get('/api/tags');

                if (! $response->successful()) {
                    $this->addError('aiBaseUrl', 'Ollama menjawab dengan HTTP '.$response->status().'.');

                    return;
                }

                $models = collect($response->json('models', []))->pluck('name')->filter()->take(5)->join(', ');

                $this->dispatch('toast', message: 'Ollama terhubung'.($models !== '' ? '. Model: '.$models : '.'));

                return;
            }

            $models = $this->aiRequest()->get('/models');

            if (! $models->successful()) {
                $this->addError('aiBaseUrl', 'Gateway AI menjawab HTTP '.$models->status().'.');

                return;
            }

            if (! $this->hasApiKey()) {
                $this->addError('aiBaseUrl', 'Gateway terjangkau, tetapi CLIPFLOW_AI_API_KEY belum diisi sehingga chat akan ditolak.');

                return;
            }

            $chat = $this->aiRequest()->timeout(30)->post('/chat/completions', [
                'model' => $this->aiModel,
                'stream' => false,
                'messages' => [['role' => 'user', 'content' => 'Balas hanya JSON: {"ok":true}']],
            ]);

            if ($chat->failed()) {
                $detail = $chat->json('error.message');

                $this->addError('aiBaseUrl', 'Chat ditolak (HTTP '.$chat->status().')'.(is_string($detail) ? ': '.$detail : '.'));

                return;
            }

            $content = (string) $chat->json('choices.0.message.content');

            $this->dispatch('toast', message: 'Gateway AI terhubung. Balasan: '.Str::limit(trim($content), 80, '…'));
        } catch (\Throwable) {
            $this->addError('aiBaseUrl', 'Gateway AI tidak dapat dihubungi. Periksa base URL dan pastikan service berjalan.');
        }
    }

    public function testTelegram(TelegramNotifier $telegram): void
    {
        if (! $telegram->configured()) {
            $this->addError('telegram', 'Telegram belum dikonfigurasi. Isi CLIPFLOW_TELEGRAM_ENABLED, TELEGRAM_BOT_TOKEN, dan TELEGRAM_CHAT_ID di .env.');

            return;
        }

        if ($telegram->test()) {
            $this->dispatch('toast', message: 'Pesan uji Telegram terkirim.');
        } else {
            $this->addError('telegram', 'Pesan uji gagal dikirim. Periksa token, chat ID, dan log.');
        }
    }

    public function render(SettingsService $settings, YouTubeOAuthService $oauth, ProcessRunner $processes): View
    {
        $tools = Cache::remember('clipflow.tools.status', 60, function () use ($processes): array {
            return [
                'ffmpeg' => $processes->isAvailable(config('clipflow.media.ffmpeg_binary')),
                'ffprobe' => $processes->isAvailable(config('clipflow.media.ffprobe_binary')),
                'python' => $processes->isAvailable(config('clipflow.transcription.python_binary')),
                'ytdlp' => $processes->isAvailable((string) config('clipflow.download.ytdlp_binary')),
                'whisper_script' => is_file(config('clipflow.transcription.script')),
            ];
        });

        $ai = Cache::remember('clipflow.ai.status', 60, function () use ($settings): array {
            $driver = $settings->aiDriver();
            $base = $settings->aiBaseUrl();
            $reachable = false;
            $models = [];

            try {
                $response = $driver === 'ollama'
                    ? Http::baseUrl($base)->connectTimeout(2)->timeout(4)->get('/api/tags')
                    : $this->aiRequestFor($settings)->connectTimeout(2)->timeout(5)->get('/models');

                $reachable = $response->successful();
                $models = $driver === 'ollama'
                    ? collect($response->json('models', []))->pluck('name')->filter()->values()->all()
                    : collect($response->json('data', []))->pluck('id')->filter()->values()->all();
            } catch (\Throwable) {
                $reachable = false;
            }

            return [
                'driver' => $driver,
                'base_url' => $base,
                'endpoint' => $driver === 'ollama' ? rtrim($base, '/').'/api/chat' : rtrim($base, '/').'/chat/completions',
                'model' => $settings->aiModel(),
                'reachable' => $reachable,
                'models' => array_slice($models, 0, 8),
                'key_configured' => $settings->aiApiKey() !== null,
            ];
        });

        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        return view('livewire.settings-form', [
            'tools' => $tools,
            'ai' => $ai,
            'quota' => app(YouTubeQuotaService::class)->summary(),
            'subtitleTemplate' => app(SubtitleTemplateService::class)->active(),
            'telegramConfigured' => app(TelegramNotifier::class)->configured(),
            'oauthConfigured' => $oauth->isConfigured(),
            'storageFree' => is_int($free) ? $free : null,
            'storageTotal' => is_int($total) ? $total : null,
            'pythonBinary' => config('clipflow.transcription.python_binary'),
            'ffmpegBinary' => config('clipflow.media.ffmpeg_binary'),
            'ffprobeBinary' => config('clipflow.media.ffprobe_binary'),
            'transcriptionScript' => config('clipflow.transcription.script'),
            'paused' => $settings->boolean('publishing.paused'),
            'schedulerHeartbeat' => $settings->get('system.scheduler_heartbeat_at'),
            'workerHeartbeat' => $settings->get('system.worker_heartbeat_at'),
            'workerHost' => $settings->get('system.worker_heartbeat_host'),
            'pendingJobs' => DB::table('jobs')->whereNull('reserved_at')->count(),
            'reservedJobs' => DB::table('jobs')->whereNotNull('reserved_at')->count(),
        ]);
    }

    private function aiRequest(): PendingRequest
    {
        return $this->aiRequestFor(app(SettingsService::class));
    }

    private function aiRequestFor(SettingsService $settings): PendingRequest
    {
        $request = Http::baseUrl($settings->aiBaseUrl())->acceptJson();

        $key = $settings->aiApiKey();

        return $key !== null ? $request->withToken($key) : $request;
    }

    private function hasApiKey(): bool
    {
        return app(SettingsService::class)->aiApiKey() !== null;
    }
}
