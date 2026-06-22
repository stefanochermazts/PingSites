<?php

namespace App\Enums;

enum NotificationLogStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Inviata',
            self::Failed => 'Fallita',
        };
    }
}
