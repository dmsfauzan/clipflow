<?php

namespace App\Livewire;

use Livewire\Component;

abstract class AuthenticatedComponent extends Component
{
    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }
}
