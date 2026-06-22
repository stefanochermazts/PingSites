<?php

namespace App\Enums;

enum NotificationType: string
{
    case Down = 'down';
    case Recovery = 'recovery';

    public function label(): string
    {
        return match ($this) {
            self::Down => 'Down',
            self::Recovery => 'Recovery',
        };
    }
}
