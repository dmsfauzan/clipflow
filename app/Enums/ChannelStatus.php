<?php

namespace App\Enums;

enum ChannelStatus: string
{
    case Connected = 'connected';
    case Disconnected = 'disconnected';

    public function label(): string
    {
        return match ($this) {
            self::Connected => 'Terhubung',
            self::Disconnected => 'Belum terhubung',
        };
    }
}
