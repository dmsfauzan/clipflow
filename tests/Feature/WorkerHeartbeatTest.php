<?php

namespace Tests\Feature;

use App\Jobs\RecordWorkerHeartbeat;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WorkerHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_heartbeat_job_updates_settings(): void
    {
        $this->app->call([new RecordWorkerHeartbeat, 'handle']);

        $settings = app(SettingsService::class);

        $this->assertNotNull($settings->get('system.worker_heartbeat_at'));
        $this->assertNotNull($settings->get('system.worker_heartbeat_host'));
    }

    public function test_ping_command_dispatches_heartbeat_job(): void
    {
        Queue::fake();

        $this->artisan('clipflow:worker-ping')->assertSuccessful();

        Queue::assertPushed(RecordWorkerHeartbeat::class);
    }
}
