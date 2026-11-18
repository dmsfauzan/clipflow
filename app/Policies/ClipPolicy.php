<?php

namespace App\Policies;

use App\Models\Clip;
use App\Models\User;

class ClipPolicy
{
    public function view(User $user, Clip $clip): bool
    {
        return $user->id === $clip->user_id;
    }

    public function update(User $user, Clip $clip): bool
    {
        return $user->id === $clip->user_id;
    }

    public function approve(User $user, Clip $clip): bool
    {
        return $user->id === $clip->user_id;
    }

    public function reject(User $user, Clip $clip): bool
    {
        return $user->id === $clip->user_id;
    }

    public function download(User $user, Clip $clip): bool
    {
        return $user->id === $clip->user_id;
    }
}
