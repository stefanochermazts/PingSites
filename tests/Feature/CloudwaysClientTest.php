<?php

namespace Tests\Feature;

use App\Services\Cloudways\CloudwaysClient;
use App\Services\Cloudways\CloudwaysException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudwaysClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMonitorSettings();
    }

    public function test_lists_servers_with_access_token(): void
    {
        Http::fake([
            'https://api.cloudways.com/api/v2/server' => Http::response($this->serversPayload()),
        ]);

        $servers = app(CloudwaysClient::class)->listServers('test-token');

        $this->assertCount(1, $servers);
        $this->assertSame('100', (string) $servers[0]['id']);
        $this->assertSame('Publimedia01', $servers[0]['label']);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.cloudways.com/api/v2/server'
                && $request->hasHeader('Authorization', 'Bearer test-token');
        });
    }

    public function test_builds_server_options_from_api(): void
    {
        Http::fake([
            'https://api.cloudways.com/api/v2/server' => Http::response($this->serversPayload()),
        ]);

        $options = app(CloudwaysClient::class)->serverOptions('test-token');

        $this->assertSame(['100' => 'Publimedia01 (1 app)'], $options);
    }

    public function test_returns_apps_for_selected_server(): void
    {
        Http::fake([
            'https://api.cloudways.com/api/v2/server' => Http::response($this->serversPayload()),
        ]);

        $apps = app(CloudwaysClient::class)->appsForServer('100', 'test-token');

        $this->assertCount(1, $apps);
        $this->assertSame('Sito Cliente', $apps[0]['label']);
    }

    public function test_throws_when_token_is_missing(): void
    {
        $this->expectException(CloudwaysException::class);
        $this->expectExceptionMessage('Access token Cloudways mancante');

        app(CloudwaysClient::class)->listServers();
    }

    public function test_throws_when_api_returns_error(): void
    {
        Http::fake([
            'https://api.cloudways.com/api/v2/server' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->expectException(CloudwaysException::class);

        app(CloudwaysClient::class)->listServers('bad-token');
    }

    /**
     * @return array<string, mixed>
     */
    private function serversPayload(): array
    {
        return [
            'servers' => [
                [
                    'id' => '100',
                    'label' => 'Publimedia01',
                    'apps' => [
                        [
                            'id' => '200',
                            'label' => 'Sito Cliente',
                            'cname' => 'www.example.com',
                            'app_fqdn' => 'wordpress-200-100.cloudwaysapps.com',
                        ],
                    ],
                ],
            ],
        ];
    }
}
