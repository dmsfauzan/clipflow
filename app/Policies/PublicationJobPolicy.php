<?php

namespace App\Policies;

use App\Models\PublicationJob;
use App\Models\User;

class PublicationJobPolicy
{
    public function view(User $user, PublicationJob $job): bool
    {
        return $user->id === $job->user_id;
    }

    public function cancel(User $user, PublicationJob $job): bool
    {
        return $user->id === $job->user_id;
    }
}
