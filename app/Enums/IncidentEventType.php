<?php

namespace App\Enums;

enum IncidentEventType: string
{
    case Opened = 'opened';
    case CheckFailed = 'check_failed';
    case CheckSucceeded = 'check_succeeded';
    case DownEmailSent = 'down_email_sent';
    case DownEmailFailed = 'down_email_failed';
    case RecoveryEmailSent = 'recovery_email_sent';
    case RecoveryEmailFailed = 'recovery_email_failed';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Opened => 'Incidente aperto',
            self::CheckFailed => 'Controllo fallito',
            self::CheckSucceeded => 'Controllo riuscito',
            self::DownEmailSent => 'Email down inviata',
            self::DownEmailFailed => 'Email down fallita',
            self::RecoveryEmailSent => 'Email recovery inviata',
            self::RecoveryEmailFailed => 'Email recovery fallita',
            self::Closed => 'Incidente chiuso',
        };
    }
}
