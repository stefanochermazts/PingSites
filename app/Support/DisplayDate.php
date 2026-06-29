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

        return Carbon::parse($value)->timezone(config('app.timezone'));
    }

    public static function format(string|\DateTimeInterface|null $value, string $format = 'd/m/Y H:i:s'): ?string
    {
        return self::parse($value)?->format($format);
    }

    public static function isoFromModel(Model $model, string $column): ?string
    {
        $value = $model->getAttribute($column);

        if ($value === null) {
            return null;
        }

        return self::parse($value)?->toIso8601String();
    }
}
