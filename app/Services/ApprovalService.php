<?php

namespace App\Services;

use App\Enums\ClipStatus;
use App\Enums\PublicationPrivacy;
use App\Enums\PublicationStatus;
use App\Exceptions\AuthorizationException;
use App\Models\Clip;
use App\Models\ClipApproval;
use App\Models\PublishingChannel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ApprovalService
{
    public function __construct(
        private readonly SchedulingService $scheduling,
        private readonly ActivityLogger $activity,
    ) {}

    public function approve(
        Clip $clip,
        User $actor,
        PublishingChannel $channel,
        PublicationPrivacy|string $privacy,
        bool $madeForKids,
        ?\DateTimeInterface $requestedSchedule = null,
    ): ClipApproval {
        $privacy = $privacy instanceof PublicationPrivacy ? $privacy : PublicationPrivacy::from($privacy);

        return DB::transaction(function () use ($clip, $actor, $channel, $privacy, $madeForKids, $requestedSchedule): ClipApproval {
            $lockedClip = Clip::query()->whereKey($clip->id)->lockForUpdate()->firstOrFail();

            if ($lockedClip->user_id !== $actor->id || $channel->user_id !== $actor->id) {
                throw new AuthorizationException('Anda tidak memiliki akses ke klip atau channel ini.');
            }

            $activeApproval = $lockedClip->currentApproval;

            if ($activeApproval !== null) {
                $sameSettings = $activeApproval->publishing_channel_id === $channel->id
                    && $activeApproval->privacy === $privacy
                    && $activeApproval->made_for_kids === $madeForKids
                    && hash_equals($activeApproval->content_hash, $lockedClip->contentHash());

                if ($sameSettings) {
                    return $activeApproval;
                }

                if ($lockedClip->status !== ClipStatus::Review) {
                    throw new AuthorizationException('Klip sudah dijadwalkan atau diproses. Batalkan status aktif sebelum mengubah approval.');
                }

                $activeApproval->update([
                    'is_current' => false,
                    'revoked_at' => now(),
                    'revoke_reason' => 'Pengaturan publikasi berubah; approval lama dicabut.',
                ]);
            }

            if ($lockedClip->status !== ClipStatus::Review) {
                throw new AuthorizationException('Hanya klip yang sudah dirender dan menunggu review yang dapat disetujui.');
            }

            if (! $lockedClip->rendered_path || ! $lockedClip->media_hash) {
                throw new AuthorizationException('Klip belum memiliki hasil render yang lengkap.');
            }

            if (! $channel->isConnected()) {
                throw new AuthorizationException('Channel YouTube tujuan belum terhubung.');
            }

            $version = (int) $lockedClip->approvals()->max('approval_version') + 1;
            $approval = $lockedClip->approvals()->create([
                'user_id' => $actor->id,
                'publishing_channel_id' => $channel->id,
                'approval_version' => $version,
                'content_hash' => $lockedClip->contentHash(),
                'privacy' => $privacy,
                'made_for_kids' => $madeForKids,
                'approved_at' => now(),
                'is_current' => true,
            ]);

            $lockedClip->update(['status' => ClipStatus::Approved]);

            $this->activity->log(
                'clip.approved',
                sprintf('Klip "%s" disetujui untuk channel %s.', $lockedClip->title, $channel->name),
                $lockedClip,
                ['approval_id' => $approval->id, 'privacy' => $privacy],
                $actor,
            );

            if ($requestedSchedule !== null) {
                $approval->update(['scheduled_at' => $requestedSchedule]);
                $this->scheduling->schedule($lockedClip, $approval, $requestedSchedule, $actor);
            }

            return $approval->fresh(['publishingChannel']);
        });
    }

    public function revoke(Clip $clip, User $actor, string $reason): void
    {
        DB::transaction(function () use ($clip, $actor, $reason): void {
            $lockedClip = Clip::query()->whereKey($clip->id)->lockForUpdate()->firstOrFail();
            $approval = $lockedClip->currentApproval;

            if ($lockedClip->user_id !== $actor->id) {
                throw new AuthorizationException('Anda tidak memiliki akses ke klip ini.');
            }

            if ($approval === null) {
                return;
            }

            $job = $lockedClip->publicationJob;

            if ($job !== null && $job->status->isActive()) {
                throw new AuthorizationException('Batalkan jadwal atau selesaikan upload sebelum mencabut approval.');
            }

            $approval->update([
                'is_current' => false,
                'revoked_at' => now(),
                'revoke_reason' => $reason,
            ]);
            $lockedClip->update(['status' => ClipStatus::Review]);

            $this->activity->log(
                'clip.approval_revoked',
                sprintf('Approval klip "%s" dibatalkan dan perlu review ulang.', $lockedClip->title),
                $lockedClip,
                ['reason' => $reason],
                $actor,
            );
        });
    }

    public function updateContent(Clip $clip, User $actor, string $title, ?string $caption): Clip
    {
        return DB::transaction(function () use ($clip, $actor, $title, $caption): Clip {
            $lockedClip = Clip::query()->whereKey($clip->id)->lockForUpdate()->firstOrFail();

            if ($lockedClip->user_id !== $actor->id) {
                throw new AuthorizationException('Anda tidak memiliki akses ke klip ini.');
            }

            $job = $lockedClip->publicationJob;

            if ($job !== null && in_array($job->status, [PublicationStatus::Uploading, PublicationStatus::NeedsReconciliation], true)) {
                throw new AuthorizationException('Konten tidak dapat diubah saat upload berjalan atau menunggu pemeriksaan.');
            }

            if ($job !== null && $job->status === PublicationStatus::Scheduled) {
                $this->scheduling->cancel($job, $actor, 'Konten diubah setelah approval.');
                $lockedClip->refresh();
            }

            $changed = $lockedClip->title !== $title || $lockedClip->caption !== $caption;
            $lockedClip->update(['title' => $title, 'caption' => $caption]);

            if ($changed && $lockedClip->currentApproval !== null) {
                $this->revoke($lockedClip, $actor, 'Judul atau caption diubah setelah approval.');
            }

            return $lockedClip->fresh();
        });
    }

    public function reject(Clip $clip, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($clip, $actor, $reason): void {
            $lockedClip = Clip::query()->whereKey($clip->id)->lockForUpdate()->firstOrFail();

            if ($lockedClip->user_id !== $actor->id) {
                throw new AuthorizationException('Anda tidak memiliki akses ke klip ini.');
            }

            if ($lockedClip->status !== ClipStatus::Review) {
                throw new AuthorizationException('Hanya klip yang menunggu review yang dapat ditolak.');
            }

            $lockedClip->update([
                'status' => ClipStatus::Rejected,
                'rejected_at' => now(),
                'reject_reason' => $reason,
            ]);

            $approval = $lockedClip->currentApproval;

            if ($approval !== null) {
                $approval->update([
                    'is_current' => false,
                    'revoked_at' => now(),
                    'revoke_reason' => 'Klip ditolak.',
                ]);
            }

            $this->activity->log('clip.rejected', sprintf('Klip "%s" ditolak.', $lockedClip->title), $lockedClip, [
                'reason' => $reason,
            ], $actor);
        });
    }

    public function restoreToReview(Clip $clip, User $actor): void
    {
        DB::transaction(function () use ($clip, $actor): void {
            $lockedClip = Clip::query()->whereKey($clip->id)->lockForUpdate()->firstOrFail();

            if ($lockedClip->user_id !== $actor->id) {
                throw new AuthorizationException('Anda tidak memiliki akses ke klip ini.');
            }

            if ($lockedClip->status !== ClipStatus::Rejected) {
                throw new AuthorizationException('Hanya klip yang ditolak yang dapat dikembalikan ke review.');
            }

            $lockedClip->update([
                'status' => ClipStatus::Review,
                'rejected_at' => null,
                'reject_reason' => null,
            ]);

            $this->activity->log('clip.restored', sprintf('Klip "%s" dikembalikan ke review.', $lockedClip->title), $lockedClip, [], $actor);
        });
    }

    public function assertValidForUpload(ClipApproval $approval, Clip $clip, PublishingChannel $channel): void
    {
        if ($clip->status !== ClipStatus::Approved) {
            throw new AuthorizationException('Klip belum disetujui.');
        }

        if ($approval->publishing_channel_id !== $channel->id || ! $approval->isValidFor($clip)) {
            throw new AuthorizationException('Approval tidak cocok dengan versi konten atau channel tujuan.');
        }

        if (! $clip->rendered_path || ! is_file(Storage::disk('local')->path($clip->rendered_path))) {
            throw new AuthorizationException('File hasil render tidak tersedia.');
        }
    }
}
