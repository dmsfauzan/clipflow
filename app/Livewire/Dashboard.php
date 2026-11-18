<?php

namespace App\Livewire;

use App\Enums\ClipStatus;
use App\Enums\PublicationStatus;
use App\Enums\VideoSourceStatus;
use App\Models\Clip;
use App\Models\PublicationJob;
use App\Models\User;
use App\Models\VideoSource;
use App\Services\SettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class Dashboard extends AuthenticatedComponent
{
    public function render(SettingsService $settings): View
    {
        /** @var User $user */
        $user = auth()->user();

        $realClips = Clip::query()
            ->where('user_id', $user->id)
            ->whereHas('videoSource', fn ($query) => $query->where('is_demo', false));

        $sourceCount = VideoSource::query()
            ->where('user_id', $user->id)
            ->where('is_demo', false)
            ->count();

        $reviewCount = (clone $realClips)->where('status', ClipStatus::Review)->count();
        $scheduledCount = (clone $realClips)->where('status', ClipStatus::Scheduled)->count();

        $timezone = (string) config('clipflow.timezone');
        $startOfDay = now()->timezone($timezone)->startOfDay();
        $endOfDay = now()->timezone($timezone)->endOfDay();

        $uploadsToday = PublicationJob::query()
            ->where('user_id', $user->id)
            ->where('status', PublicationStatus::Uploaded)
            ->whereBetween('uploaded_at', [$startOfDay, $endOfDay])
            ->count();

        $reviewClips = (clone $realClips)
            ->with('videoSource')
            ->where('status', ClipStatus::Review)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $upcomingJobs = PublicationJob::query()
            ->with(['clip', 'publishingChannel'])
            ->where('user_id', $user->id)
            ->whereIn('status', [PublicationStatus::Scheduled, PublicationStatus::Uploading])
            ->orderBy('scheduled_for')
            ->limit(5)
            ->get();

        $queuePending = DB::table('jobs')->whereNull('reserved_at')->count();
        $queueReserved = DB::table('jobs')->whereNotNull('reserved_at')->count();
        $queueFailed = DB::table('failed_jobs')->count();
        $processingSources = VideoSource::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                VideoSourceStatus::Queued,
                VideoSourceStatus::Downloading,
                VideoSourceStatus::Uploaded,
                VideoSourceStatus::Probing,
                VideoSourceStatus::Transcribing,
                VideoSourceStatus::Selecting,
                VideoSourceStatus::Rendering,
            ])
            ->count();
        $renderingClips = (clone $realClips)->where('status', ClipStatus::Rendering)->count();
        $failedClips = (clone $realClips)->where('status', ClipStatus::Failed)->count();

        $heartbeat = $settings->get('system.scheduler_heartbeat_at');
        $heartbeatAt = is_string($heartbeat) ? Carbon::parse($heartbeat) : null;
        $heartbeatHealthy = $heartbeatAt !== null && $heartbeatAt->gt(now()->subMinutes(5));

        $workerHeartbeat = $settings->get('system.worker_heartbeat_at');
        $workerHeartbeatAt = is_string($workerHeartbeat) ? Carbon::parse($workerHeartbeat) : null;
        $workerHealthy = $workerHeartbeatAt !== null && $workerHeartbeatAt->gt(now()->subMinutes(5));
        $reservedJobs = DB::table('jobs')->whereNotNull('reserved_at')->count();
        $workerBusy = $reservedJobs > 0;
        $queueBacklog = DB::table('jobs')->whereNull('reserved_at')->count();
        $workerInactive = ! $workerBusy && $queueBacklog > 0 && ! $workerHealthy;

        $demoSourceCount = VideoSource::query()->where('user_id', $user->id)->where('is_demo', true)->count();
        $demoReviewCount = Clip::query()
            ->where('user_id', $user->id)
            ->whereHas('videoSource', fn ($query) => $query->where('is_demo', true))
            ->where('status', ClipStatus::Review)
            ->count();

        $channels = $user->publishingChannels()->get();

        return view('livewire.dashboard', [
            'sourceCount' => $sourceCount,
            'reviewCount' => $reviewCount,
            'scheduledCount' => $scheduledCount,
            'uploadsToday' => $uploadsToday,
            'pendingApprovalCount' => (clone $realClips)->where('status', ClipStatus::Approved)->count(),
            'reviewClips' => $reviewClips,
            'upcomingJobs' => $upcomingJobs,
            'queuePending' => $queuePending,
            'queueReserved' => $queueReserved,
            'queueFailed' => $queueFailed,
            'processingSources' => $processingSources,
            'renderingClips' => $renderingClips,
            'failedClips' => $failedClips,
            'heartbeatAt' => $heartbeatAt,
            'heartbeatHealthy' => $heartbeatHealthy,
            'workerHeartbeatAt' => $workerHeartbeatAt,
            'workerHealthy' => $workerHealthy,
            'workerBusy' => $workerBusy,
            'workerInactive' => $workerInactive,
            'queueBacklog' => $queueBacklog,
            'reservedJobs' => $reservedJobs,
            'channels' => $channels,
            'paused' => $settings->boolean('publishing.paused'),
            'autoSchedule' => $settings->boolean('publishing.auto_schedule'),
            'dailyLimit' => (int) $settings->get('publishing.daily_limit'),
            'demoSourceCount' => $demoSourceCount,
            'demoReviewCount' => $demoReviewCount,
        ]);
    }
}
