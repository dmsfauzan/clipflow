<?php

namespace App\Console\Commands;

use App\Services\ProcessRunner;
use App\Services\SettingsService;
use App\Services\YouTubeQuotaService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class CheckEnvironment extends Command
{
    protected $signature = 'clipflow:check';

    protected $description = 'Periksa kesiapan tool pipeline: FFmpeg, FFprobe, Python, yt-dlp, Ollama';

    public function handle(): int
    {
        $tools = [
            'FFmpeg' => config('clipflow.media.ffmpeg_binary'),
            'FFprobe' => config('clipflow.media.ffprobe_binary'),
            'Python' => config('clipflow.transcription.python_binary'),
            'yt-dlp' => config('clipflow.download.ytdlp_binary'),
        ];

        $runner = app(ProcessRunner::class);
        $ok = true;

        foreach ($tools as $label => $binary) {
            $available = $runner->isAvailable($binary);
            $this->line(sprintf('%-10s: %s (%s)', $label, $available ? 'tersedia' : 'tidak ditemukan', $binary));
            $ok = $ok && $available;
        }

        $settings = app(SettingsService::class);
        $driver = $settings->aiDriver();
        $baseUrl = $settings->aiBaseUrl();
        $aiReachable = $this->checkAi($driver, $baseUrl, $settings->aiApiKey());
        $this->line(sprintf(
            '%-10s: %s (%s, model %s)',
            'AI',
            $aiReachable ? 'terhubung' : 'tidak dapat dihubungi',
            $driver.' @ '.$baseUrl,
            $settings->aiModel(),
        ));
        $ok = $ok && $aiReachable;

        if ($driver === '9router' && $settings->aiApiKey() === null) {
            $this->warn('CLIPFLOW_AI_API_KEY belum diisi. Gateway 9router akan menolak permintaan chat.');
        }

        $workerHeartbeat = $settings->get('system.worker_heartbeat_at');
        $workerAt = is_string($workerHeartbeat) ? Carbon::parse($workerHeartbeat) : null;
        $reservedJobs = DB::table('jobs')->whereNotNull('reserved_at')->count();
        $pendingJobs = DB::table('jobs')->whereNull('reserved_at')->count();
        $workerHealthy = $reservedJobs > 0 || ($workerAt !== null && $workerAt->gt(now()->subMinutes(5)));
        $workerInactive = ! $workerHealthy && $pendingJobs > 0;
        $this->line(sprintf(
            '%-10s: %s (reserved=%d, pending=%d, heartbeat=%s)',
            'Worker',
            $workerInactive ? 'tidak aktif' : ($reservedJobs > 0 ? 'memproses job' : ($workerHealthy ? 'aktif' : 'idle')),
            $reservedJobs,
            $pendingJobs,
            $workerAt?->diffForHumans() ?? 'belum ada',
        ));

        $quota = app(YouTubeQuotaService::class)->summary();
        $this->line(sprintf(
            '%-10s: %s unit/hari, %s unit/upload -> kapasitas %d/hari, terpakai %s unit',
            'Kuota YT',
            number_format($quota['daily_units'], 0, ',', '.'),
            number_format($quota['upload_cost'], 0, ',', '.'),
            $quota['capacity'],
            number_format($quota['used_today'], 0, ',', '.'),
        ));

        $script = (string) config('clipflow.transcription.script');
        $this->line(sprintf('%-10s: %s', 'Skrip transkripsi', is_file($script) ? $script : 'tidak ditemukan'));
        $ok = $ok && is_file($script);

        if (! $ok) {
            $this->warn('Pipeline belum siap. Lihat README untuk panduan instalasi tool yang hilang.');
        } else {
            $this->info('Semua tool utama terdeteksi.');
        }

        $uploadMax = $this->iniBytes((string) ini_get('upload_max_filesize'));
        $postMax = $this->iniBytes((string) ini_get('post_max_size'));
        $this->line(sprintf('%-10s: upload_max_filesize=%s, post_max_size=%s', 'PHP limit', ini_get('upload_max_filesize'), ini_get('post_max_size')));

        if ($uploadMax < $this->configuredMaxBytes() || $postMax < $this->configuredMaxBytes()) {
            $this->warn('Batas upload PHP lebih kecil dari CLIPFLOW_MAX_UPLOAD_BYTES. Naikkan upload_max_filesize dan post_max_size di php.ini.');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function configuredMaxBytes(): int
    {
        return (int) config('clipflow.media.max_upload_bytes');
    }

    private function iniBytes(string $value): int
    {
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function checkOllama(string $baseUrl): bool
    {
        try {
            $response = Http::baseUrl($baseUrl)
                ->connectTimeout(3)
                ->timeout(5)
                ->get('/api/tags');

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkAi(string $driver, string $baseUrl, ?string $apiKey): bool
    {
        if ($driver === 'ollama') {
            return $this->checkOllama($baseUrl);
        }

        try {
            $request = Http::baseUrl($baseUrl)->connectTimeout(3)->timeout(5)->acceptJson();

            if ($apiKey !== null) {
                $request = $request->withToken($apiKey);
            }

            return $request->get('/models')->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
