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
     * @param  array<string, mixed>  $app
     */
    public static function temporaryUrl(array $app): ?string
    {
        $fqdn = self::stringValue($app['app_fqdn'] ?? null);
        if ($fqdn === '') {
            return null;
        }

        $url = self::absoluteUrl($fqdn);

        return self::isTemporaryCloudwaysUrl($url) ? $url : null;
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

    public static function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(self::stringValue($value));

        return in_array($normalized, ['1', 'true', 'yes', 'on', 'enabled'], true);
    }

    public static function isPositiveCount(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)))) {
            return (float) $value > 0;
        }

        return self::isTruthy($value);
    }

    public static function isTemporaryCloudwaysUrl(?string $url): bool
    {
        if (! is_string($url) || $url === '') {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);

        return $host === 'cloudwaysapps.com' || str_ends_with($host, '.cloudwaysapps.com');
    }
}
