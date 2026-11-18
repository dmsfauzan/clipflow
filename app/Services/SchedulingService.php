<?php

namespace App\Services;

use App\Enums\ClipStatus;
use App\Enums\PublicationStatus;
use App\Exceptions\AuthorizationException;
use App\Models\Clip;
use App\Models\ClipApproval;
use App\Models\PublicationJob;
use App\Models\PublishingChannel;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SchedulingService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ActivityLogger $activity,
        private readonly YouTubeQuotaService $quota,
    ) {}

    public function isPaused(): bool
    {
        return $this->settings->boolean('publishing.paused');
    }

    public function pause(User $actor): void
    {
        $this->settings->set('publishing.paused', true);
        $this->activity->log('queue.paused', 'Antrean upload dijeda.', null, [], $actor);
    }

    public function resume(User $actor): void
    {
        $this->settings->set('publishing.paused', false);
        $this->activity->log('queue.resumed', 'Antrean upload dilanjutkan.', null, [], $actor);
    }

    public function schedule(Clip $clip, ClipApproval $approval, ?Carbon $after = null, ?User $actor = null): PublicationJob
    {
        return DB::transaction(function () use ($clip, $approval, $after, $actor): PublicationJob {
            $lockedClip = Clip::query()->whereKey($clip->id)->lockForUpdate()->firstOrFail();

            $existing = PublicationJob::query()
                ->where('clip_approval_id', $approval->id)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $active = PublicationJob::query()
                ->where('clip_id', $lockedClip->id)
                ->active()
                ->exists();

            if ($active) {
                throw new AuthorizationException('Klip ini masih memiliki jadwal atau upload aktif.');
            }

            if ($lockedClip->status !== ClipStatus::Approved) {
                throw new AuthorizationException('Klip harus disetujui sebelum dapat dijadwalkan.');
            }

            $approval->refresh();

            if (! $approval->isValidFor($lockedClip)) {
                throw new AuthorizationException('Approval sudah tidak valid. Minta review ulang.');
            }

            $channel = $approval->publishingChannel;

            if (! $channel instanceof PublishingChannel || ! $channel->isConnected()) {
                throw new AuthorizationException('Channel YouTube tujuan belum terhubung.');
            }

            $slot = $this->nextAvailableSlot($channel, $after);

            $job = new PublicationJob([
                'clip_id' => $lockedClip->id,
                'clip_approval_id' => $approval->id,
                'publishing_channel_id' => $channel->id,
                'user_id' => $lockedClip->user_id,
                'status' => PublicationStatus::Scheduled,
                'scheduled_for' => $slot,
                'slot_date' => $slot->copy()->timezone(config('clipflow.timezone'))->toDateString(),
                'slot_key' => PublicationJob::slotKey($channel->id, $slot),
                'privacy' => $approval->privacy,
                'made_for_kids' => $approval->made_for_kids,
                'content_hash' => $approval->content_hash,
                'title' => $lockedClip->title,
                'caption' => $lockedClip->caption,
            ]);

            try {
                $job->save();
            } catch (QueryException $exception) {
                throw new AuthorizationException(
                    'Slot jadwal baru saja terisi. Muat ulang halaman dan coba lagi.',
                    'slot_conflict',
                    $exception,
                );
            }

            $lockedClip->update(['status' => ClipStatus::Scheduled]);
            $approval->update(['scheduled_at' => $slot]);

            $this->activity->log(
                'publication.scheduled',
                sprintf('Klip "%s" dijadwalkan pada %s.', $lockedClip->title, $slot->format('d M Y H:i')),
                $job,
                ['scheduled_for' => $slot->toIso8601String(), 'channel_id' => $channel->id],
                $actor,
            );

            return $job;
        });
    }

    public function nextAvailableSlot(PublishingChannel $channel, ?Carbon $after = null): Carbon
    {
        $timezone = (string) config('clipflow.timezone');
        $earliest = ($after ?? now())->copy()->timezone($timezone)->addMinute()->startOfMinute();
        $dailyLimit = $this->quota->effectiveDailyLimit(max(1, (int) $this->settings->get('publishing.daily_limit')));
        $startTime = $this->parseTime((string) $this->settings->get('publishing.slot_start'), '08:00');
        $endTime = $this->parseTime((string) $this->settings->get('publishing.slot_end'), '22:30');
        $interval = max(5, (int) $this->settings->get('publishing.slot_interval_minutes'));
        $horizonDays = max(1, (int) $this->settings->get('publishing.horizon_days'));

        for ($dayOffset = 0; $dayOffset <= $horizonDays; $dayOffset++) {
            $date = $earliest->copy()->addDays($dayOffset)->startOfDay();

            $scheduledForDate = PublicationJob::query()
                ->where('publishing_channel_id', $channel->id)
                ->whereDate('slot_date', $date->toDateString())
                ->whereNotIn('status', [PublicationStatus::Cancelled])
                ->count();

            if ($scheduledForDate >= $dailyLimit) {
                continue;
            }

            $slots = $this->slotsForDay($date, $startTime, $endTime, $interval);

            foreach ($slots as $slot) {
                if ($slot->lt($earliest)) {
                    continue;
                }

                $key = PublicationJob::slotKey($channel->id, $slot);

                if (PublicationJob::query()->where('slot_key', $key)->exists()) {
                    continue;
                }

                return $slot;
            }
        }

        throw new AuthorizationException('Tidak ada slot upload tersedia dalam horizon penjadwalan.');
    }

    public function cancel(PublicationJob $job, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($job, $actor, $reason): void {
            $lockedJob = PublicationJob::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();

            if ($lockedJob->status !== PublicationStatus::Scheduled) {
                throw new AuthorizationException('Hanya jadwal yang belum diunggah dapat dibatalkan.');
            }

            $lockedJob->update([
                'status' => PublicationStatus::Cancelled,
                'slot_key' => null,
                'cancelled_at' => now(),
                'last_error' => $reason,
            ]);

            $clip = $lockedJob->clip;
            $approval = $lockedJob->approval;

            $approval->update([
                'is_current' => false,
                'revoked_at' => now(),
                'revoke_reason' => $reason ?: 'Jadwal upload dibatalkan sebelum upload.',
            ]);

            $clip->update(['status' => ClipStatus::Review]);

            $this->activity->log(
                'publication.cancelled',
                sprintf('Jadwal klip "%s" dibatalkan dan dikembalikan ke review.', $clip->title),
                $lockedJob,
                ['reason' => $reason],
                $actor,
            );
        });
    }

    public function uploadedToday(PublishingChannel $channel, ?Carbon $day = null): int
    {
        $timezone = (string) config('clipflow.timezone');
        $localDay = ($day ?? now())->copy()->timezone($timezone);
        $start = $localDay->copy()->startOfDay()->timezone(config('app.timezone'));
        $end = $localDay->copy()->endOfDay()->timezone(config('app.timezone'));

        return PublicationJob::query()
            ->where('publishing_channel_id', $channel->id)
            ->where('status', PublicationStatus::Uploaded)
            ->whereBetween('uploaded_at', [$start, $end])
            ->count();
    }

    public function scheduledCountForDay(PublishingChannel $channel, Carbon $day): int
    {
        return PublicationJob::query()
            ->where('publishing_channel_id', $channel->id)
            ->whereDate('slot_date', $day->copy()->timezone(config('clipflow.timezone'))->toDateString())
            ->whereNotIn('status', [PublicationStatus::Cancelled])
            ->count();
    }

    /**
     * @return array<int, Carbon>
     */
    private function slotsForDay(Carbon $date, string $startTime, string $endTime, int $interval): array
    {
        [$startHour, $startMinute] = array_map('intval', explode(':', $startTime));
        [$endHour, $endMinute] = array_map('intval', explode(':', $endTime));

        $cursor = $date->copy()->setTime($startHour, $startMinute);
        $end = $date->copy()->setTime($endHour, $endMinute);
        $slots = [];

        while ($cursor->lte($end)) {
            $slots[] = $cursor->copy();
            $cursor->addMinutes($interval);
        }

        return $slots;
    }

    private function parseTime(string $value, string $fallback): string
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1 ? $value : $fallback;
    }

    /**
     * @return array<int, array{date: string, jobs: array<int, PublicationJob>}>
     */
    public function upcomingGrouped(int $limit = 50): array
    {
        $jobs = PublicationJob::query()
            ->with(['clip', 'publishingChannel'])
            ->whereIn('status', [PublicationStatus::Scheduled, PublicationStatus::Uploading])
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->get();

        return $jobs
            ->groupBy(fn (PublicationJob $job): string => $job->scheduled_for->timezone(config('clipflow.timezone'))->toDateString())
            ->map(fn ($group, string $date): array => [
                'date' => $date,
                'jobs' => $group->values()->all(),
            ])
            ->values()
            ->all();
    }

    public function displayTimezone(): string
    {
        return (string) config('clipflow.timezone');
    }

    public function cancelReasonPreview(PublicationJob $job): string
    {
        return Str::limit((string) $job->last_error, 200, '…');
    }
}
