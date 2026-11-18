<?php

namespace App\Models;

use App\Enums\VideoSourceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VideoSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'subtitle_template_id', 'original_name', 'extension', 'mime_type', 'size_bytes', 'path', 'disk',
        'duration_seconds', 'width', 'height', 'has_video', 'has_audio', 'is_demo', 'status',
        'processing_stage', 'failure_message', 'rights_confirmed_at', 'probed_at', 'processed_at', 'metadata',
        'source_type', 'source_url', 'source_url_hash', 'import_progress', 'import_started_at',
        'import_completed_at', 'downloaded_bytes',
    ];

    protected function casts(): array
    {
        return [
            'status' => VideoSourceStatus::class,
            'duration_seconds' => 'decimal:3',
            'has_video' => 'boolean',
            'has_audio' => 'boolean',
            'is_demo' => 'boolean',
            'metadata' => 'array',
            'rights_confirmed_at' => 'datetime',
            'probed_at' => 'datetime',
            'processed_at' => 'datetime',
            'import_progress' => 'integer',
            'import_started_at' => 'datetime',
            'import_completed_at' => 'datetime',
            'downloaded_bytes' => 'integer',
        ];
    }

    public function isImport(): bool
    {
        return $this->source_type === 'url';
    }

    public function isDownloading(): bool
    {
        return in_array($this->status, [VideoSourceStatus::Queued, VideoSourceStatus::Downloading], true);
    }

    public function sourceHost(): ?string
    {
        if (! is_string($this->source_url) || $this->source_url === '') {
            return null;
        }

        $host = parse_url($this->source_url, PHP_URL_HOST);

        return is_string($host) ? $host : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transcript(): HasOne
    {
        return $this->hasOne(Transcript::class);
    }

    public function subtitleTemplate(): BelongsTo
    {
        return $this->belongsTo(SubtitleTemplate::class);
    }

    public function clips(): HasMany
    {
        return $this->hasMany(Clip::class);
    }

    public function statusLabel(): string
    {
        return $this->status->label();
    }
}
