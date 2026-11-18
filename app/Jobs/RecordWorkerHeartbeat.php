<?php

namespace App\Jobs;

use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordWorkerHeartbeat implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 120;

    public function __construct()
    {
        $this->onQueue('pipeline');
    }

    public function uniqueId(): string
    {
        return 'worker-heartbeat';
    }

    public function handle(SettingsService $settings): void
    {
        $settings->setMany([
            'system.worker_heartbeat_at' => now()->toIso8601String(),
            'system.worker_heartbeat_host' => gethostname() ?: 'unknown',
        ]);
    }
}
