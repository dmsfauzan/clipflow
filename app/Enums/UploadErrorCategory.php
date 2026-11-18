<?php

namespace App\Enums;

enum UploadErrorCategory: string
{
    case Quota = 'quota';
    case Auth = 'auth';
    case Validation = 'validation';
    case Network = 'network';
    case Uncertain = 'uncertain';
    case Conflict = 'conflict';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Quota => 'Kuota API habis',
            self::Auth => 'Token/izin bermasalah',
            self::Validation => 'Data ditolak YouTube',
            self::Network => 'Jaringan',
            self::Uncertain => 'Status upload belum pasti',
            self::Conflict => 'Konflik publikasi',
            self::Unknown => 'Tidak diketahui',
        };
    }

    public function isSafeToRetry(): bool
    {
        return in_array($this, [self::Network, self::Unknown], true);
    }
}
