<?php

namespace App\Models;

use App\Enums\PublicationPrivacy;
use App\Enums\PublicationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PublicationJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'clip_id', 'clip_approval_id', 'publishing_channel_id', 'user_id', 'status', 'scheduled_for',
        'slot_date', 'slot_key', 'privacy', 'made_for_kids', 'content_hash', 'title', 'caption',
        'youtube_video_id', 'resumable_session_uri', 'attempt_count', 'error_category', 'last_error',
        'started_at', 'uploaded_at', 'last_attempted_at', 'cancelled_at',
    ];

    protected $hidden = ['resumable_session_uri'];

    protected function casts(): array
    {
        return [
            'status' => PublicationStatus::class,
            'privacy' => PublicationPrivacy::class,
            'scheduled_for' => 'datetime',
            'slot_date' => 'date',
            'made_for_kids' => 'boolean',
            'resumable_session_uri' => 'encrypted',
            'started_at' => 'datetime',
            'uploaded_at' => 'datetime',
            'last_attempted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function clip(): BelongsTo
    {
        return $this->belongsTo(Clip::class);
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(ClipApproval::class, 'clip_approval_id');
    }

    public function publishingChannel(): BelongsTo
    {
        return $this->belongsTo(PublishingChannel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(UploadAttempt::class);
    }

    public function scopeDue(Builder $query, ?Carbon $now = null): Builder
    {
        return $query
            ->where('status', PublicationStatus::Scheduled)
            ->where('scheduled_for', '<=', $now ?? now());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            PublicationStatus::Scheduled,
            PublicationStatus::Uploading,
            PublicationStatus::NeedsReconciliation,
        ]);
    }

    public static function slotKey(int $channelId, Carbon $scheduledFor): string
    {
        return $channelId.'|'.$scheduledFor->copy()->timezone(config('app.timezone'))->format('YmdHi');
    }
}
