<?php

namespace App\Console\Commands;

use App\Jobs\RecordWorkerHeartbeat;
use Illuminate\Console\Command;

class WorkerHeartbeat extends Command
{
    protected $signature = 'clipflow:worker-ping';

    protected $description = 'Kirim job heartbeat agar dashboard bisa mendeteksi worker pipeline yang aktif';

    public function handle(): int
    {
        RecordWorkerHeartbeat::dispatch();

        return self::SUCCESS;
    }
}
