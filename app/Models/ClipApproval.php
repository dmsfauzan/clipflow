<?php

namespace App\Models;

use App\Enums\PublicationPrivacy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClipApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'clip_id', 'user_id', 'publishing_channel_id', 'approval_version', 'content_hash', 'privacy',
        'made_for_kids', 'scheduled_at', 'approved_at', 'revoked_at', 'revoke_reason', 'is_current',
    ];

    protected function casts(): array
    {
        return [
            'privacy' => PublicationPrivacy::class,
            'made_for_kids' => 'boolean',
            'scheduled_at' => 'datetime',
            'approved_at' => 'datetime',
            'revoked_at' => 'datetime',
            'is_current' => 'boolean',
        ];
    }

    public function clip(): BelongsTo
    {
        return $this->belongsTo(Clip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function publishingChannel(): BelongsTo
    {
        return $this->belongsTo(PublishingChannel::class);
    }

    public function isValidFor(Clip $clip): bool
    {
        return $this->is_current && $this->revoked_at === null && hash_equals($this->content_hash, $clip->contentHash());
    }
}
