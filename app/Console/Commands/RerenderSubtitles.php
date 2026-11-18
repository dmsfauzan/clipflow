<?php

namespace App\Console\Commands;

use App\Enums\ClipStatus;
use App\Models\Clip;
use App\Services\ClipRerenderService;
use Illuminate\Console\Command;

class RerenderSubtitles extends Command
{
    protected $signature = 'clipflow:rerender-subtitles
        {--source= : ID sumber video tertentu}
        {--status=review : Status klip yang dirender ulang (review|failed)}
        {--limit=100 : Maksimum klip yang dijadwalkan}
        {--dry-run : Tampilkan tanpa menjadwalkan}';

    protected $description = 'Render ulang klip dengan gaya subtitle terbaru';

    public function handle(ClipRerenderService $rerender): int
    {
        $status = ClipStatus::tryFrom((string) $this->option('status'));

        if ($status === null || ! in_array($status, [ClipStatus::Review, ClipStatus::Failed], true)) {
            $this->error('Status harus review atau failed.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));

        $query = Clip::query()
            ->where('status', $status)
            ->whereNotNull('transcript_id')
            ->whereHas('videoSource', fn ($q) => $q->where('is_demo', false))
            ->when($this->option('source'), fn ($q, $source) => $q->where('video_source_id', (int) $source))
            ->orderBy('id')
            ->limit($limit);

        $clips = $query->get();
        $queued = 0;

        foreach ($clips as $clip) {
            if ($this->option('dry-run')) {
                $this->line(sprintf('[dry-run] klip #%d %s', $clip->id, $clip->title));

                continue;
            }

            try {
                $rerender->queue($clip);
                $queued++;
            } catch (\Throwable $exception) {
                $this->warn(sprintf('Klip #%d dilewati: %s', $clip->id, $exception->getMessage()));
            }
        }

        $this->info($this->option('dry-run')
            ? sprintf('%d klip akan dirender ulang.', $clips->count())
            : sprintf('%d klip dijadwalkan render ulang.', $queued));

        return self::SUCCESS;
    }
}
