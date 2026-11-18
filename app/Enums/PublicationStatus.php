<?php

namespace App\Enums;

enum PublicationStatus: string
{
    case Scheduled = 'scheduled';
    case Uploading = 'uploading';
    case Uploaded = 'uploaded';
    case Failed = 'failed';
    case NeedsReconciliation = 'needs_reconciliation';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Terjadwal',
            self::Uploading => 'Sedang diunggah',
            self::Uploaded => 'API menerima video',
            self::Failed => 'Gagal',
            self::NeedsReconciliation => 'Perlu pemeriksaan',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Scheduled, self::Uploading, self::NeedsReconciliation], true);
    }
}
