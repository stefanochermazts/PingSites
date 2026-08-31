<?php

namespace Tests\Feature;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\StatusPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncCloudwaysMonitorUrlsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMonitorSettings();
        config(['cloudways.access_token' => 'test-token']);
    }

    public function test_updates_monitor_url_when_cloudways_url_changed(): void
    {
        $statusPage = StatusPage::query()->where('is_default', true)->firstOrFail();

        $monitor = Monitor::query()->create([
            'name' => 'Sito Cliente',
            'url' => 'https://old.example.com',
            'status' => MonitorStatus::Unknown,
            'valid_status_codes' => [200],
            'status_page_id' => $statusPage->id,
            'cloudways_server_id' => '100',
            'cloudways_app_id' => '200',
        ]);

        Http::fake([
            'https://api.cloudways.com/api/v2/server' => Http::response([
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
            ]),
        ]);

        $this->artisan('cloudways:sync-monitor-urls')
            ->assertSuccessful()
            ->expectsOutputToContain('aggiornati: 1');

        $this->assertSame('https://www.example.com', $monitor->fresh()->url);
    }

    public function test_leaves_url_unchanged_when_cloudways_url_is_the_same(): void
    {
        $statusPage = StatusPage::query()->where('is_default', true)->firstOrFail();

        Monitor::query()->create([
            'name' => 'Sito Cliente',
            'url' => 'https://www.example.com',
            'status' => MonitorStatus::Unknown,
            'valid_status_codes' => [200],
            'status_page_id' => $statusPage->id,
            'cloudways_server_id' => '100',
            'cloudways_app_id' => '200',
        ]);

        Http::fake([
            'https://api.cloudways.com/api/v2/server' => Http::response([
                'servers' => [
                    [
                        'id' => '100',
                        'label' => 'Publimedia01',
                        'apps' => [
                            [
                                'id' => '200',
                                'label' => 'Sito Cliente',
                                'cname' => 'www.example.com',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->artisan('cloudways:sync-monitor-urls')
            ->assertSuccessful()
            ->expectsOutputToContain('invariati: 1');

        $this->assertDatabaseHas('monitors', [
            'cloudways_app_id' => '200',
            'url' => 'https://www.example.com',
        ]);
    }

    public function test_creates_new_apps_from_the_same_imported_server(): void
    {
        $statusPage = StatusPage::query()->where('is_default', true)->firstOrFail();

        Monitor::query()->create([
            'name' => 'Sito Cliente',
            'url' => 'https://www.example.com',
            'status' => MonitorStatus::Unknown,
            'valid_status_codes' => [200],
            'status_page_id' => $statusPage->id,
            'published' => true,
            'cloudways_server_id' => '100',
            'cloudways_app_id' => '200',
        ]);

        Http::fake([
            'https://api.cloudways.com/api/v2/server' => Http::response([
                'servers' => [
                    [
                        'id' => '100',
                        'label' => 'Publimedia01',
                        'apps' => [
                            [
                                'id' => '200',
                                'label' => 'Sito Cliente',
                                'cname' => 'www.example.com',
                            ],
                            [
                                'id' => '202',
                                'label' => 'Nuovo Sito',
                                'cname' => 'example.org',
                            ],
                        ],
                    ],
                    [
                        'id' => '999',
                        'label' => 'Altro Server',
                        'apps' => [
                            [
                                'id' => '1',
                                'label' => 'Altro App',
                                'cname' => 'other.example.com',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->artisan('cloudways:sync-monitor-urls')
            ->assertSuccessful()
            ->expectsOutputToContain('creati: 1');

        $this->assertDatabaseHas('monitors', [
            'name' => 'Nuovo Sito',
            'url' => 'https://example.org',
            'cloudways_server_id' => '100',
            'cloudways_app_id' => '202',
            'status_page_id' => $statusPage->id,
            'published' => true,
            'check_frequency' => 10,
            'timeout' => 10,
        ]);

        $this->assertDatabaseMissing('monitors', [
            'cloudways_server_id' => '999',
        ]);
        $this->assertDatabaseCount('monitors', 2);
    }

    public function test_skips_when_access_token_is_missing(): void
    {
        config(['cloudways.access_token' => null]);

        $this->artisan('cloudways:sync-monitor-urls')
            ->assertSuccessful()
            ->expectsOutputToContain('Access token Cloudways mancante');

        Http::assertNothingSent();
    }
}
