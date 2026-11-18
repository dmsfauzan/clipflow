<?php

namespace App\Models;

use App\Enums\ClipStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Clip extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_source_id', 'subtitle_template_id', 'user_id', 'transcript_id', 'start_seconds', 'end_seconds', 'duration_seconds',
        'title', 'caption', 'reason', 'ai_score', 'status', 'failure_message', 'rendered_path', 'thumbnail_path',
        'srt_path', 'ass_path', 'rendered_size_bytes', 'rendered_width', 'rendered_height', 'media_hash',
        'render_started_at', 'rendered_at', 'rejected_at', 'reject_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ClipStatus::class,
            'start_seconds' => 'decimal:3',
            'end_seconds' => 'decimal:3',
            'duration_seconds' => 'decimal:3',
            'ai_score' => 'decimal:2',
            'render_started_at' => 'datetime',
            'rendered_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function videoSource(): BelongsTo
    {
        return $this->belongsTo(VideoSource::class);
    }

    public function subtitleTemplate(): BelongsTo
    {
        return $this->belongsTo(SubtitleTemplate::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ClipApproval::class);
    }

    public function currentApproval(): HasOne
    {
        return $this->hasOne(ClipApproval::class)->where('is_current', true)->whereNull('revoked_at');
    }

    public function publicationJobs(): HasMany
    {
        return $this->hasMany(PublicationJob::class);
    }

    public function publicationJob(): HasOne
    {
        return $this->hasOne(PublicationJob::class)->latestOfMany('id');
    }

    public function statusLabel(): string
    {
        return $this->status->label();
    }

    public function contentHash(): string
    {
        return hash('sha256', implode('|', [
            $this->title,
            $this->caption,
            $this->rendered_path,
            $this->media_hash,
        ]));
    }
}
