<?php

namespace App\Console\Commands;

use App\Enums\PublicationStatus;
use App\Jobs\UploadPublication;
use App\Models\PublicationJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RetryPublication extends Command
{
    protected $signature = 'clipflow:retry-publication {id : ID publikasi} {--force : Lewati konfirmasi untuk error yang tidak pasti aman}';

    protected $description = 'Coba ulang upload yang gagal, setelah semua pemeriksaan keamanan lolos';

    public function handle(): int
    {
        $job = PublicationJob::query()
            ->with(['clip', 'approval', 'publishingChannel'])
            ->find((int) $this->argument('id'));

        if (! $job) {
            $this->error('Publikasi tidak ditemukan.');

            return self::FAILURE;
        }

        if ($job->status === PublicationStatus::NeedsReconciliation) {
            $this->error('Status belum pasti. Gunakan `php artisan clipflow:reconcile-uploads` dan verifikasi manual dulu.');

            return self::FAILURE;
        }

        if ($job->status !== PublicationStatus::Failed) {
            $this->error('Hanya publikasi berstatus gagal yang dapat diulang.');

            return self::FAILURE;
        }

        $channel = $job->publishingChannel;

        if (! $channel || ! $channel->isConnected()) {
            $this->error('Channel YouTube belum terhubung.');

            return self::FAILURE;
        }

        if (! $job->approval->isValidFor($job->clip)) {
            $this->error('Approval tidak valid untuk konten ini. Minta review ulang terlebih dahulu.');

            return self::FAILURE;
        }

        $path = Storage::disk('local')->path((string) $job->clip->rendered_path);

        if (! is_file($path)) {
            $this->error('File hasil render tidak tersedia.');

            return self::FAILURE;
        }

        $safe = $job->error_category !== null && in_array($job->error_category, ['network', 'unknown'], true);

        if (! $safe && $this->option('force') !== true && ! $this->confirm('Error terakhir mungkin tidak aman diulang. Lanjutkan?')) {
            return self::SUCCESS;
        }

        $job->update([
            'status' => PublicationStatus::Scheduled,
            'error_category' => null,
            'last_error' => 'Dijadwalkan ulang manual oleh operator.',
        ]);

        UploadPublication::dispatch($job);
        $this->info('Upload dijadwalkan ulang.');

        return self::SUCCESS;
    }
}
