<?php

namespace App\Services;

use App\Enums\ClipStatus;
use App\Enums\PublicationStatus;
use App\Exceptions\AuthorizationException;
use App\Jobs\RenderClip;
use App\Models\Clip;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClipRerenderService
{
    public function __construct(private readonly SchedulingService $scheduling) {}

    public function queue(Clip $clip): void
    {
        DB::transaction(function () use ($clip): void {
            $locked = Clip::query()->whereKey($clip->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [ClipStatus::Review, ClipStatus::Failed], true)) {
                throw new AuthorizationException('Hanya klip berstatus review atau gagal yang dapat dirender ulang.');
            }

            $locked->update([
                'status' => ClipStatus::Rendering,
                'failure_message' => null,
                'render_started_at' => null,
                'rendered_at' => null,
            ]);
        });

        RenderClip::dispatch($clip->fresh());
    }

    /**
     * Ubah pengaturan yang memengaruhi video (timestamp, ukuran foreground, blur, subtitle).
     * Perubahan membatalkan approval dan jadwal lama, lalu menjadwalkan render ulang.
     *
     * @param  array<string, mixed>  $settings
     */
    public function updateSettings(Clip $clip, User $actor, array $settings): Clip
    {
        $updated = DB::transaction(function () use ($clip, $actor, $settings): Clip {
            $locked = Clip::query()->whereKey($clip->id)->lockForUpdate()->firstOrFail();

            if ($locked->user_id !== $actor->id) {
                throw new AuthorizationException('Anda tidak memiliki akses ke klip ini.');
            }

            $job = $locked->publicationJob;

            if ($job !== null && in_array($job->status, [PublicationStatus::Uploading, PublicationStatus::NeedsReconciliation], true)) {
                throw new AuthorizationException('Klip sedang diunggah atau menunggu pemeriksaan; perubahan tidak dapat diterapkan.');
            }

            if ($job !== null && $job->status === PublicationStatus::Scheduled) {
                $this->scheduling->cancel($job, $actor, 'Pengaturan render klip diubah.');
                $locked->refresh();
                $job = $locked->publicationJob;
            }

            if (! in_array($locked->status, [ClipStatus::Review, ClipStatus::Approved, ClipStatus::Failed], true)) {
                throw new AuthorizationException('Klip pada status ini tidak dapat diubah. Batalkan jadwal atau tunggu proses selesai.');
            }

            $approval = $locked->currentApproval;

            if ($approval !== null) {
                $approval->update([
                    'is_current' => false,
                    'revoked_at' => now(),
                    'revoke_reason' => 'Pengaturan render klip diubah; approval lama dicabut.',
                ]);
            }

            $locked->fill($settings);
            $locked->status = ClipStatus::Rendering;
            $locked->failure_message = null;
            $locked->render_started_at = null;
            $locked->rendered_at = null;
            $locked->rendered_path = null;
            $locked->media_hash = null;
            $locked->save();

            return $locked->fresh();
        });

        RenderClip::dispatch($updated);

        return $updated;
    }
}
