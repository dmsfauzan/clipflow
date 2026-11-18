<?php

namespace App\Enums;

enum TranscriptStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Processing => 'Sedang berjalan',
            self::Completed => 'Selesai',
            self::Failed => 'Gagal',
        };
    }
}
