<?php

namespace App\Enums;

enum MonitorStatus: string
{
    case Unknown = 'unknown';
    case Online = 'online';
    case Down = 'down';
    case Maintenance = 'maintenance';
    case Paused = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Sconosciuto',
            self::Online => 'Online',
            self::Down => 'Down',
            self::Maintenance => 'Manutenzione',
            self::Paused => 'Sospeso',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unknown => 'gray',
            self::Online => 'success',
            self::Down => 'danger',
            self::Maintenance => 'warning',
            self::Paused => 'gray',
        };
    }
}
