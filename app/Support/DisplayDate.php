<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class DisplayDate
{
    public static function parse(string|\DateTimeInterface|null $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->timezone(config('app.timezone'));
        }

        return Carbon::parse($value, 'UTC')->timezone(config('app.timezone'));
    }

    public static function format(string|\DateTimeInterface|null $value, string $format = 'd/m/Y H:i:s'): ?string
    {
        return self::parse($value)?->format($format);
    }

    public static function utcIsoFromModel(Model $model, string $column): ?string
    {
        $value = $model->getRawOriginal($column);

        if ($value === null) {
            return null;
        }

        return Carbon::parse($value, 'UTC')->toIso8601String();
    }
}
