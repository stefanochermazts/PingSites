<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class SsrfGuard
{
    private const BLOCKED_HOSTS = [
        'localhost',
        'localhost.localdomain',
        '0.0.0.0',
    ];

    public function validateUrl(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('URL non valido.');
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Sono consentiti solo URL HTTP e HTTPS.');
        }

        $host = strtolower($parts['host']);

        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            throw new InvalidArgumentException('Host non consentito.');
        }

        $this->validateHost($host);
    }

    public function validateRedirectUrl(string $url): void
    {
        $this->validateUrl($url);
    }

    private function validateHost(string $host): void
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->validateIp($host);

            return;
        }

        $records = Cache::remember(
            'ssrf:dns:'.$host,
            300,
            function () use ($host): array {
                $result = @dns_get_record($host, DNS_A + DNS_AAAA);

                return $result === false ? [] : $result;
            },
        );

        if ($records === []) {
            return;
        }

        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $this->validateIp($record['ip']);
            }

            if (isset($record['ipv6'])) {
                $this->validateIp($record['ipv6']);
            }
        }
    }

    private function validateIp(string $ip): void
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new InvalidArgumentException('Indirizzo IP non consentito.');
        }

        if (str_starts_with($ip, '169.254.')) {
            throw new InvalidArgumentException('Indirizzo metadata cloud non consentito.');
        }

        if ($ip === '::1' || str_starts_with($ip, 'fe80:') || str_starts_with($ip, 'fc') || str_starts_with($ip, 'fd')) {
            throw new InvalidArgumentException('Indirizzo IPv6 locale non consentito.');
        }
    }

    /**
     * @param  callable(string): void  $onRedirect
     */
    public function createHttpClient(int $timeout, bool $followRedirects, bool $verifySsl, string $userAgent, callable $onRedirect): PendingRequest
    {
        $maxRedirects = $followRedirects ? 5 : 0;

        return Http::withOptions([
            'allow_redirects' => $followRedirects ? [
                'max' => $maxRedirects,
                'track_redirects' => true,
                'on_redirect' => function ($request, $response, $uri) use ($onRedirect): void {
                    $onRedirect((string) $uri);
                },
            ] : false,
            'verify' => $verifySsl,
            'timeout' => $timeout,
            'connect_timeout' => min($timeout, 5),
            'curl' => [
                CURLOPT_FORBID_REUSE => true,
                CURLOPT_FRESH_CONNECT => true,
            ],
        ])->withHeaders([
            'User-Agent' => $userAgent,
            'Accept' => '*/*',
            'Connection' => 'close',
        ]);
    }
}
