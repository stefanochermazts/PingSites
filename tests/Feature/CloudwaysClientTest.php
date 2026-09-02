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

    public function test_lists_security_suite_apps_across_pages(): void
    {
        Http::fake(function ($request) {
            if (! str_contains($request->url(), '/server/security/100/apps')) {
                return Http::response(['message' => 'unexpected'], 404);
            }

            $page = (int) ($request['page'] ?? 1);

            if ($page === 1) {
                return Http::response([
                    'apps' => [
                        ['id' => 111, 'mp_addon_active' => 1, 'infected' => 0],
                    ],
                    'pagination' => ['last_page' => 2],
                ]);
            }

            return Http::response([
                'apps' => [
                    ['id' => 222, 'mp_addon_active' => 1, 'infected' => 1],
                ],
                'pagination' => ['last_page' => 2],
            ]);
        });

        $apps = app(CloudwaysClient::class)->securityAppsForServer('100', 'test-token');

        $this->assertCount(2, $apps);
        $this->assertSame('111', (string) $apps[0]['id']);
        $this->assertSame('222', (string) $apps[1]['id']);

        Http::assertSentCount(2);
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
