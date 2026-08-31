<?php

namespace App\Services\Cloudways;

class CloudwaysAppUrl
{
    /**
     * @param  array<string, mixed>  $app
     */
    public static function fromApp(array $app): ?string
    {
        $cname = self::stringValue($app['cname'] ?? null);
        if ($cname !== '') {
            return self::absoluteUrl($cname);
        }

        $aliases = self::aliases($app['aliases'] ?? null);
        if ($aliases !== []) {
            return self::absoluteUrl($aliases[0]);
        }

        $fqdn = self::stringValue($app['app_fqdn'] ?? null);
        if ($fqdn !== '') {
            return self::absoluteUrl($fqdn);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function aliases(mixed $aliases): array
    {
        if (is_array($aliases)) {
            $items = $aliases;
        } elseif (is_string($aliases) && trim($aliases) !== '') {
            $items = preg_split('/\s*,\s*/', $aliases) ?: [];
        } else {
            return [];
        }

        $normalized = [];
        foreach ($items as $alias) {
            $alias = self::stringValue($alias);
            if ($alias !== '') {
                $normalized[] = $alias;
            }
        }

        return array_values($normalized);
    }

    public static function absoluteUrl(string $hostOrUrl): string
    {
        $value = trim($hostOrUrl);
        if ($value === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $value) !== 1) {
            $value = 'https://'.ltrim($value, '/');
        }

        return rtrim($value, '/');
    }

    public static function stringValue(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
