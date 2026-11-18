<?php

namespace App\Enums;

enum PublicationPrivacy: string
{
    case Private = 'private';
    case Unlisted = 'unlisted';
    case Public = 'public';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Private',
            self::Unlisted => 'Unlisted',
            self::Public => 'Public',
        };
    }
}
