<?php

namespace App\Models;

use App\Enums\UploadAttemptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'publication_job_id', 'attempt_number', 'status', 'error_category', 'http_status',
        'error_message', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => UploadAttemptStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function publicationJob(): BelongsTo
    {
        return $this->belongsTo(PublicationJob::class);
    }
}
