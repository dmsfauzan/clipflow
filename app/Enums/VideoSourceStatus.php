<?php

namespace App\Enums;

enum VideoSourceStatus: string
{
    case Queued = 'queued';
    case Downloading = 'downloading';
    case Uploaded = 'uploaded';
    case Probing = 'probing';
    case Transcribing = 'transcribing';
    case Selecting = 'selecting';
    case Rendering = 'rendering';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Menunggu diunduh',
            self::Downloading => 'Mengunduh dari link',
            self::Uploaded => 'Menunggu diproses',
            self::Probing => 'Memeriksa media',
            self::Transcribing => 'Transkripsi',
            self::Selecting => 'Seleksi klip AI',
            self::Rendering => 'Merender klip',
            self::Ready => 'Selesai',
            self::Failed => 'Gagal',
        };
    }

    public function isProcessing(): bool
    {
        return in_array($this, [
            self::Queued,
            self::Downloading,
            self::Uploaded,
            self::Probing,
            self::Transcribing,
            self::Selecting,
            self::Rendering,
        ], true);
    }
}
