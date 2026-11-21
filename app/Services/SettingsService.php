<?php

namespace App\Services;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_KEY = 'clipflow.settings';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $values = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $stored = null;

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $publishing = (array) config('clipflow.publishing', []);
        $ai = (array) config('clipflow.ai', []);
        $transcription = (array) config('clipflow.transcription', []);
        $retention = (array) config('clipflow.retention', []);
        $render = (array) data_get(config('clipflow', []), 'media.render', []);

        return [
            'publishing.daily_limit' => $publishing['daily_limit'] ?? 30,
            'publishing.slot_start' => $publishing['slot_start'] ?? '08:00',
            'publishing.slot_end' => $publishing['slot_end'] ?? '22:30',
            'publishing.slot_interval_minutes' => $publishing['slot_interval_minutes'] ?? 30,
            'publishing.max_concurrent' => $publishing['max_concurrent'] ?? 1,
            'publishing.overdue_grace_minutes' => $publishing['overdue_grace_minutes'] ?? 120,
            'publishing.dispatches_per_run' => $publishing['dispatches_per_run'] ?? 3,
            'publishing.auto_schedule' => $publishing['auto_schedule'] ?? false,
            'publishing.default_privacy' => $publishing['default_privacy'] ?? 'private',
            'publishing.safe_retries' => $publishing['safe_retries'] ?? 2,
            'publishing.horizon_days' => $publishing['horizon_days'] ?? 60,
            'publishing.quota_daily_units' => $publishing['youtube_daily_quota_units'] ?? 10000,
            'publishing.quota_upload_cost' => $publishing['youtube_upload_cost_units'] ?? 1600,
            'publishing.quota_reserve' => $publishing['youtube_quota_reserve_units'] ?? 200,
            'publishing.paused' => false,
            'pipeline.whisper_model' => $transcription['model'] ?? 'small',
            'pipeline.ollama_base_url' => $ai['base_url'] ?? 'http://127.0.0.1:11434',
            'pipeline.ollama_model' => $ai['model'] ?? 'llama3.1:8b',
            'pipeline.ai_driver' => $ai['driver'] ?? 'ollama',
            'pipeline.ai_base_url' => $ai['base_url'] ?? 'http://127.0.0.1:11434',
            'pipeline.ai_model' => $ai['model'] ?? 'llama3.1:8b',
            'pipeline.min_clip_seconds' => $ai['min_clip_seconds'] ?? 15,
            'pipeline.max_clip_seconds' => $ai['max_clip_seconds'] ?? 60,
            'pipeline.max_candidates' => $ai['max_candidates'] ?? 30,
            'pipeline.render_width' => $render['width'] ?? 1080,
            'pipeline.render_height' => $render['height'] ?? 1920,
            'pipeline.foreground_width_percent' => data_get($render, 'foreground_width_percent.default', 90),
            'pipeline.blur_sigma' => data_get($render, 'blur_sigma.default', 18),
            'retention.source_days' => $retention['source_days'] ?? 0,
            'retention.clip_days' => $retention['clip_days'] ?? 0,
            'system.scheduler_heartbeat_at' => null,
            'system.worker_heartbeat_at' => null,
            'system.worker_heartbeat_host' => null,
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();

        return $values[$key] ?? $default;
    }

    public function boolean(string $key): bool
    {
        return (bool) $this->get($key, false);
    }

    public function integer(string $key): int
    {
        return (int) $this->get($key, 0);
    }

    public function float(string $key): float
    {
        return (float) $this->get($key, 0);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Driver AI aktif: "ollama" atau "9router" (OpenAI-compatible).
     */
    public function aiDriver(): string
    {
        $driver = $this->string('pipeline.ai_driver', (string) config('clipflow.ai.driver'));

        return in_array($driver, ['ollama', '9router', 'openai', 'openai-compatible'], true) ? $driver : 'ollama';
    }

    public function aiBaseUrl(): string
    {
        $stored = $this->stored('pipeline.ai_base_url') ?? $this->stored('pipeline.ollama_base_url');

        return rtrim((string) ($stored ?? config('clipflow.ai.base_url')), '/');
    }

    public function aiModel(): string
    {
        $stored = $this->stored('pipeline.ai_model') ?? $this->stored('pipeline.ollama_model');

        return (string) ($stored ?? config('clipflow.ai.model'));
    }

    public function aiResponseFormat(): bool
    {
        return (bool) config('clipflow.ai.response_format');
    }

    public function aiTimeout(): int
    {
        return max(30, (int) config('clipflow.ai.timeout'));
    }

    public function aiApiKey(): ?string
    {
        $key = config('clipflow.ai.api_key');

        return is_string($key) && trim($key) !== '' ? $key : null;
    }

    public function set(string $key, mixed $value): void
    {
        ApplicationSetting::updateOrCreate(
            ['key' => $key],
            ['value' => json_encode($value)],
        );

        $this->flush();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            ApplicationSetting::updateOrCreate(
                ['key' => $key],
                ['value' => json_encode($value)],
            );
        }

        $this->flush();
    }

    public function flush(): void
    {
        $this->values = null;
        $this->stored = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values ??= array_merge($this->defaults(), $this->storedValues());
    }

    private function stored(string $key): mixed
    {
        return $this->storedValues()[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function storedValues(): array
    {
        return $this->stored ??= ApplicationSetting::query()
            ->pluck('value', 'key')
            ->map(fn (?string $value): mixed => $value === null ? null : json_decode($value, true))
            ->all();
    }
}
