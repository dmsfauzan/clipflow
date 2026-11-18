<?php

namespace App\Enums;

enum ClipStatus: string
{
    case Rendering = 'rendering';
    case Review = 'review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Scheduled = 'scheduled';
    case Uploading = 'uploading';
    case Uploaded = 'uploaded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Rendering => 'Sedang dirender',
            self::Review => 'Menunggu review',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Scheduled => 'Terjadwal',
            self::Uploading => 'Sedang diunggah',
            self::Uploaded => 'Sudah diunggah',
            self::Failed => 'Gagal',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Review, self::Approved], true);
    }
}
