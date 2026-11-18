<?php

namespace App\Services;

use App\Models\PublicationJob;
use App\Models\VideoSource;

class ClipflowNotifier
{
    public function __construct(private readonly TelegramNotifier $telegram) {}

    public function configured(): bool
    {
        return $this->telegram->configured();
    }

    public function clipsReady(VideoSource $source, int $reviewCount, int $failedCount = 0): void
    {
        $message = sprintf(
            "🎬 <b>%d klip siap review</b>\nSumber: %s\n\n%s",
            $reviewCount,
            e((string) $source->original_name),
            e((string) route('review.index')),
        );

        if ($failedCount > 0) {
            $message .= sprintf("\n⚠️ %d klip gagal dirender.", $failedCount);
        }

        $this->telegram->send($message);
    }

    public function sourceFailed(VideoSource $source, string $reason): void
    {
        $this->telegram->send(sprintf(
            "❌ <b>Pipeline gagal</b>\nSumber: %s\nTahap: %s\nAlasan: %s\n\n%s",
            e((string) $source->original_name),
            e((string) ($source->processing_stage ?? '-')),
            e(mb_substr($reason, 0, 500)),
            e((string) route('sources.show', $source)),
        ));
    }

    public function uploadSucceeded(PublicationJob $job): void
    {
        $this->telegram->send(sprintf(
            "✅ <b>YouTube menerima video</b>\n%s\nVideo ID: %s\n\nStatus \"uploaded\" bukan jaminan video sudah publik.",
            e((string) $job->title),
            e((string) ($job->youtube_video_id ?? '-')),
        ));
    }

    public function uploadNeedsReview(PublicationJob $job, string $reason): void
    {
        $this->telegram->send(sprintf(
            "⚠️ <b>Upload perlu pemeriksaan</b>\n%s\nAlasan: %s\n\nJangan unggah ulang sebelum memeriksa YouTube.\n%s",
            e((string) $job->title),
            e(mb_substr($reason, 0, 400)),
            e((string) route('schedule.index')),
        ));
    }

    public function uploadFailed(PublicationJob $job, string $reason): void
    {
        $this->telegram->send(sprintf(
            "❌ <b>Upload gagal</b>\n%s\nAlasan: %s\n\n%s",
            e((string) $job->title),
            e(mb_substr($reason, 0, 400)),
            e((string) route('schedule.index')),
        ));
    }
}
