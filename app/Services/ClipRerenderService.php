<?php

namespace App\Services;

use App\Enums\ClipStatus;
use App\Exceptions\AuthorizationException;
use App\Jobs\RenderClip;
use App\Models\Clip;
use Illuminate\Support\Facades\DB;

class ClipRerenderService
{
    public function queue(Clip $clip): void
    {
        DB::transaction(function () use ($clip): void {
            $locked = Clip::query()->whereKey($clip->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [ClipStatus::Review, ClipStatus::Failed], true)) {
                throw new AuthorizationException('Hanya klip berstatus review atau gagal yang dapat dirender ulang.');
            }

            if ($locked->transcript_id === null) {
                throw new AuthorizationException('Transkrip klip tidak tersedia sehingga render ulang tidak mungkin.');
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
}
