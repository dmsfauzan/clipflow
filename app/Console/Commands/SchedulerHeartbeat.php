<?php

namespace App\Console\Commands;

use App\Services\SettingsService;
use Illuminate\Console\Command;

class SchedulerHeartbeat extends Command
{
    protected $signature = 'clipflow:heartbeat';

    protected $description = 'Catat bahwa scheduler sedang berjalan untuk dashboard';

    public function handle(SettingsService $settings): int
    {
        $settings->set('system.scheduler_heartbeat_at', now()->toIso8601String());

        return self::SUCCESS;
    }
}
