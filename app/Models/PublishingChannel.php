<?php

namespace App\Models;

use App\Enums\ChannelStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublishingChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'provider', 'name', 'channel_identifier', 'channel_title', 'channel_handle', 'avatar_url',
        'access_token', 'refresh_token', 'token_type', 'token_expires_at', 'scopes', 'status', 'is_active',
        'connected_at', 'last_refreshed_at', 'last_error_at', 'last_error',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'status' => ChannelStatus::class,
            'is_active' => 'boolean',
            'token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'last_refreshed_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ClipApproval::class);
    }

    public function publicationJobs(): HasMany
    {
        return $this->hasMany(PublicationJob::class);
    }

    public function isConnected(): bool
    {
        return $this->status === ChannelStatus::Connected && $this->is_active;
    }
}
