<?php

namespace App\Providers;

use App\Services\SettingsService;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $recordHeartbeat = function (): void {
            rescue(function (): void {
                $settings = app(SettingsService::class);
                $last = $settings->get('system.worker_heartbeat_at');
                $lastAt = is_string($last) ? Carbon::parse($last) : null;

                if ($lastAt === null || $lastAt->lt(now()->subSeconds(20))) {
                    $settings->setMany([
                        'system.worker_heartbeat_at' => now()->toIso8601String(),
                        'system.worker_heartbeat_host' => gethostname() ?: 'unknown',
                    ]);
                }
            }, report: false);
        };

        Event::listen(JobProcessing::class, $recordHeartbeat);
        Event::listen(JobProcessed::class, $recordHeartbeat);
    }
}
