<?php

namespace App\Services;

use App\Models\PublishingChannel;
use App\Models\UploadAttempt;

class YouTubeQuotaService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function dailyUnits(): int
    {
        return max(0, $this->settings->integer('publishing.quota_daily_units'));
    }

    public function uploadCost(): int
    {
        return max(1, $this->settings->integer('publishing.quota_upload_cost'));
    }

    public function reserve(): int
    {
        return max(0, $this->settings->integer('publishing.quota_reserve'));
    }

    /**
     * Perkiraan jumlah upload yang masih muat dalam kuota harian.
     */
    public function capacity(): int
    {
        $available = max(0, $this->dailyUnits() - $this->reserve());

        return (int) floor($available / $this->uploadCost());
    }

    /**
     * Estimasi konservatif unit yang sudah terpakai hari ini (berdasarkan attempt).
     */
    public function usedToday(?PublishingChannel $channel = null): int
    {
        $timezone = (string) config('clipflow.timezone');
        $start = now()->timezone($timezone)->startOfDay()->timezone(config('app.timezone'));
        $end = now()->timezone($timezone)->endOfDay()->timezone(config('app.timezone'));

        $query = UploadAttempt::query()->whereBetween('started_at', [$start, $end]);

        if ($channel !== null) {
            $query->whereHas('publicationJob', fn ($q) => $q->where('publishing_channel_id', $channel->id));
        }

        return (int) $query->count() * $this->uploadCost();
    }

    public function remaining(?PublishingChannel $channel = null): int
    {
        return max(0, $this->dailyUnits() - $this->reserve() - $this->usedToday($channel));
    }

    public function canUpload(?PublishingChannel $channel = null): bool
    {
        return $this->remaining($channel) >= $this->uploadCost();
    }

    /**
     * Batas harian efektif: mana yang lebih kecil antara konfigurasi dan kapasitas kuota.
     */
    public function effectiveDailyLimit(int $configuredLimit): int
    {
        return max(1, min(max(1, $configuredLimit), max(1, $this->capacity())));
    }

    /**
     * @return array{daily_units: int, upload_cost: int, reserve: int, capacity: int, used_today: int, remaining: int}
     */
    public function summary(?PublishingChannel $channel = null): array
    {
        return [
            'daily_units' => $this->dailyUnits(),
            'upload_cost' => $this->uploadCost(),
            'reserve' => $this->reserve(),
            'capacity' => $this->capacity(),
            'used_today' => $this->usedToday($channel),
            'remaining' => $this->remaining($channel),
        ];
    }
}
