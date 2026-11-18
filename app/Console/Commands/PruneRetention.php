<?php

namespace App\Console\Commands;

use App\Enums\ClipStatus;
use App\Enums\VideoSourceStatus;
use App\Models\Clip;
use App\Models\VideoSource;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneRetention extends Command
{
    protected $signature = 'clipflow:prune {--dry-run : Tampilkan saja tanpa menghapus}';

    protected $description = 'Hapus file lama sesuai kebijakan retensi tanpa menyentuh antrean aktif';

    public function handle(SettingsService $settings): int
    {
        $sourceDays = (int) $settings->get('retention.source_days');
        $clipDays = (int) $settings->get('retention.clip_days');
        $dryRun = (bool) $this->option('dry-run');
        $deletedFiles = 0;

        if ($sourceDays > 0) {
            $cutoff = now()->subDays($sourceDays);

            $sources = VideoSource::query()
                ->where('created_at', '<=', $cutoff)
                ->whereIn('status', [VideoSourceStatus::Ready, VideoSourceStatus::Failed])
                ->whereDoesntHave('clips', function ($query): void {
                    $query->whereIn('status', [
                        ClipStatus::Rendering,
                        ClipStatus::Review,
                        ClipStatus::Approved,
                        ClipStatus::Scheduled,
                        ClipStatus::Uploading,
                        ClipStatus::Failed,
                    ]);
                })
                ->get();

            foreach ($sources as $source) {
                if ($dryRun) {
                    $this->line(sprintf('[dry-run] hapus sumber #%d %s', $source->id, $source->original_name));
                    $deletedFiles++;

                    continue;
                }

                Storage::disk($source->disk)->delete($source->path);
                $metadata = $source->metadata ?? [];
                $metadata['source_deleted_at'] = now()->toIso8601String();
                $source->update(['metadata' => $metadata]);
                $deletedFiles++;
            }
        }

        if ($clipDays > 0) {
            $cutoff = now()->subDays($clipDays);

            $clips = Clip::query()
                ->where('created_at', '<=', $cutoff)
                ->whereIn('status', [ClipStatus::Uploaded, ClipStatus::Rejected])
                ->whereDoesntHave('publicationJob', fn ($query) => $query->active())
                ->whereNotNull('rendered_path')
                ->get();

            foreach ($clips as $clip) {
                if ($dryRun) {
                    $this->line(sprintf('[dry-run] hapus hasil render klip #%d %s', $clip->id, $clip->title));
                    $deletedFiles++;

                    continue;
                }

                foreach ([$clip->rendered_path, $clip->thumbnail_path, $clip->srt_path, $clip->ass_path] as $path) {
                    if (is_string($path) && $path !== '') {
                        Storage::disk('local')->delete($path);
                    }
                }

                $clip->update([
                    'rendered_path' => null,
                    'thumbnail_path' => null,
                    'srt_path' => null,
                    'ass_path' => null,
                    'rendered_size_bytes' => null,
                ]);
                $deletedFiles++;
            }
        }

        if ($sourceDays === 0 && $clipDays === 0) {
            $this->info('Retensi nonaktif (0 hari). Tidak ada file dihapus.');
        } else {
            $this->info(sprintf('%s %d item.', $dryRun ? 'Akan menghapus' : 'Menghapus', $deletedFiles));
        }

        return self::SUCCESS;
    }
}
