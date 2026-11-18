<?php

namespace App\Policies;

use App\Models\PublishingChannel;
use App\Models\User;

class PublishingChannelPolicy
{
    public function view(User $user, PublishingChannel $channel): bool
    {
        return $user->id === $channel->user_id;
    }

    public function update(User $user, PublishingChannel $channel): bool
    {
        return $user->id === $channel->user_id;
    }
}
