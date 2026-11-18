<?php

namespace App\Models;

use App\Enums\TranscriptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transcript extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_source_id', 'language', 'model', 'full_text', 'segments', 'status', 'error_message', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'segments' => 'array',
            'status' => TranscriptStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function videoSource(): BelongsTo
    {
        return $this->belongsTo(VideoSource::class);
    }

    public function clips(): HasMany
    {
        return $this->hasMany(Clip::class);
    }
}
