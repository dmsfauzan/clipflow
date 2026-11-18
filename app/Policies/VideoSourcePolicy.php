<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VideoSource;

class VideoSourcePolicy
{
    public function view(User $user, VideoSource $videoSource): bool
    {
        return $user->id === $videoSource->user_id;
    }

    public function update(User $user, VideoSource $videoSource): bool
    {
        return $user->id === $videoSource->user_id;
    }

    public function delete(User $user, VideoSource $videoSource): bool
    {
        return $user->id === $videoSource->user_id;
    }
}
