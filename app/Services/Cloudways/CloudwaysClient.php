<?php

namespace App\Services\Cloudways;

use App\Settings\CloudwaysSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

class CloudwaysClient
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listServers(?string $accessToken = null): array
    {
        $token = $this->resolveToken($accessToken);
        $payload = $this->get('/server', $token);
        $servers = $payload['servers'] ?? null;

        if (! is_array($servers)) {
            throw new CloudwaysException('Risposta Cloudways /server senza elenco servers.');
        }

        /** @var list<array<string, mixed>> $servers */
        return array_values($servers);
    }

    /**
     * @return array<string, string>
     */
    public function serverOptions(?string $accessToken = null): array
    {
        $options = [];

        foreach ($this->cachedServers($accessToken) as $server) {
            $id = CloudwaysAppUrl::stringValue($server['id'] ?? null);
            if ($id === '') {
                continue;
            }

            $label = CloudwaysAppUrl::stringValue($server['label'] ?? null) ?: $id;
            $apps = is_array($server['apps'] ?? null) ? $server['apps'] : [];
            $appCount = count($apps);

            $options[$id] = $appCount > 0 ? "{$label} ({$appCount} app)" : $label;
        }

        return $options;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function appsForServer(string $serverId, ?string $accessToken = null): array
    {
        foreach ($this->listServers($accessToken) as $server) {
            if (CloudwaysAppUrl::stringValue($server['id'] ?? null) !== $serverId) {
                continue;
            }

            return $this->appsFromServer($server);
        }

        throw new CloudwaysException("Server Cloudways \"{$serverId}\" non trovato.");
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function appIndex(?string $accessToken = null): array
    {
        $index = [];

        foreach ($this->listServers($accessToken) as $server) {
            $serverId = CloudwaysAppUrl::stringValue($server['id'] ?? null);

            foreach ($this->appsFromServer($server) as $app) {
                $appId = CloudwaysAppUrl::stringValue($app['id'] ?? null);
                if ($serverId === '' || $appId === '') {
                    continue;
                }

                $index[$serverId.':'.$appId] = $app;
            }
        }

        return $index;
    }

    public function configuredToken(): ?string
    {
        try {
            $settingsToken = app(CloudwaysSettings::class)->access_token;
            if (is_string($settingsToken) && $settingsToken !== '') {
                return $settingsToken;
            }
        } catch (MissingSettings) {
            // Settings not seeded yet; fall back to env/config.
        }

        $configToken = config('cloudways.access_token');

        return is_string($configToken) && $configToken !== '' ? $configToken : null;
    }

    public function resolveToken(?string $accessToken = null): string
    {
        if (is_string($accessToken) && $accessToken !== '') {
            return $accessToken;
        }

        $stored = $this->configuredToken();
        if ($stored !== null) {
            return $stored;
        }

        throw new CloudwaysException('Access token Cloudways mancante. Configuralo nelle impostazioni o nel .env.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function securityAppsForServer(string $serverId, ?string $accessToken = null): array
    {
        $token = $this->resolveToken($accessToken);
        $apps = [];
        $page = 1;
        $maxPages = 200;

        do {
            $payload = $this->get('/server/security/'.rawurlencode($serverId).'/apps', $token, ['page' => $page]);

            if (! array_key_exists('apps', $payload) || ! is_array($payload['apps'])) {
                throw new CloudwaysException('Risposta Security Suite senza elenco apps.');
            }

            foreach ($payload['apps'] as $app) {
                if (is_array($app)) {
                    $apps[] = $app;
                }
            }

            $pagination = $payload['pagination'] ?? [];
            $lastPage = is_array($pagination) ? (int) ($pagination['last_page'] ?? $page) : $page;
            $page++;
        } while ($page <= $lastPage && $page <= $maxPages);

        if ($page <= $lastPage) {
            throw new CloudwaysException("Paginazione Security Suite oltre il limite di {$maxPages} pagine.");
        }

        return $apps;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cachedServers(?string $accessToken): array
    {
        $token = $this->resolveToken($accessToken);
        $cacheKey = 'cloudways:servers:'.hash('sha256', $token);

        /** @var list<array<string, mixed>> $servers */
        $servers = Cache::remember($cacheKey, 60, fn (): array => $this->listServers($token));

        return $servers;
    }

    /**
     * @param  array<string, mixed>  $server
     * @return list<array<string, mixed>>
     */
    private function appsFromServer(array $server): array
    {
        $serverId = CloudwaysAppUrl::stringValue($server['id'] ?? null);

        if (! is_array($server['apps'] ?? null)) {
            throw new CloudwaysException(
                'Il server Cloudways "'.($serverId !== '' ? $serverId : '?').'" non contiene l\'elenco delle applicazioni.'
            );
        }

        /** @var list<array<string, mixed>> $apps */
        $apps = $server['apps'];

        return array_values($apps);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, string $token, array $query = []): array
    {
        $response = Http::timeout(30)
            ->acceptJson()
            ->withToken($token)
            ->get(rtrim((string) config('cloudways.base_url'), '/').$path, $query);

        if (! $response->successful()) {
            throw new CloudwaysException(
                'Chiamata Cloudways fallita (HTTP '.$response->status().') su '.$path
            );
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new CloudwaysException('Risposta Cloudways non JSON.');
        }

        return $payload;
    }
}
