<?php

namespace Tests\Feature;

use App\Actions\ImportCloudwaysAppsAction;
use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\StatusPage;
use App\Services\Cloudways\CloudwaysException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportCloudwaysAppsActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMonitorSettings();
    }

    public function test_imports_apps_with_defaults_onto_selected_status_page(): void
    {
        Http::fake([
            'https://api.cloudways.com/api/v2/server' => Http::response($this->serversPayload()),
        ]);

        $statusPage = $this->defaultStatusPage();

        $result = app(ImportCloudwaysAppsAction::class)->handle(
            serverId: '100',
            statusPageId: $statusPage->id,
            accessToken: 'test-token',
        );

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['skipped']);

        $this->assertDatabaseHas('monitors', [
            'name' => 'Sito Cliente',
            'url' => 'https://www.example.com',
            'check_frequency' => 10,
            'timeout' => 10,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'cloudways_server_id' => '100',
            'cloudways_app_id' => '200',
            'public_name' => 'Sito Cliente',
        ]);

        $monitor = Monitor::query()->where('cloudways_app_id', '200')->firstOrFail();
        $this->assertSame(MonitorStatus::Unknown, $monitor->status);
        $this->assertTrue($monitor->is_active);
        $this->assertSame([200, 301, 302], $monitor->valid_status_codes);
        $this->assertTrue($monitor->follow_redirects);
        $this->assertTrue($monitor->verify_ssl);
        $this->assertSame(2, $monitor->failure_threshold);
        $this->assertSame(2, $monitor->recovery_threshold);
        $this->assertNotNull($monitor->next_check_at);

        $fqdnMonitor = Monitor::query()->where('cloudways_app_id', '201')->firstOrFail();
        $this->assertSame('https://wordpress-201-100.cloudwaysapps.com', $fqdnMonitor->url);
    }

    public function test_skips_already_imported_apps_and_links_existing_urls(): void
    {
        Http::fake([
            'https://api.cloudways.com/api/v2/server' => Http::response($this->serversPayload()),
        ]);

        $statusPage = $this->defaultStatusPage();

        Monitor::query()->create([
            'name' => 'Sito Cliente',
            'url' => 'https://www.example.com',
            'status' => MonitorStatus::Unknown,
            'valid_status_codes' => [200],
            'status_page_id' => $statusPage->id,
        ]);

        $first = app(ImportCloudwaysAppsAction::class)->handle('100', $statusPage->id, 'test-token');
        $this->assertSame(1, $first['created']);
        $this->assertSame(1, $first['linked']);

        $second = app(ImportCloudwaysAppsAction::class)->handle('100', $statusPage->id, 'test-token');
        $this->assertSame(0, $second['created']);
        $this->assertSame(2, $second['skipped']);

        $this->assertDatabaseCount('monitors', 2);
        $this->assertDatabaseHas('monitors', [
            'url' => 'https://www.example.com',
            'cloudways_server_id' => '100',
            'cloudways_app_id' => '200',
        ]);
    }

    public function test_skips_known_apps_without_updating_url(): void
    {
        Http::fake([
            'https://api.cloudways.com/api/v2/server' => Http::response($this->serversPayload()),
        ]);

        $statusPage = $this->defaultStatusPage();

        Monitor::query()->create([
            'name' => 'Sito Cliente',
            'url' => 'https://old.example.com',
            'status' => MonitorStatus::Unknown,
            'valid_status_codes' => [200],
            'status_page_id' => $statusPage->id,
            'cloudways_server_id' => '100',
            'cloudways_app_id' => '200',
        ]);

        $result = app(ImportCloudwaysAppsAction::class)->handle('100', $statusPage->id, 'test-token');

        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, $result['created']);
        $this->assertDatabaseHas('monitors', [
            'cloudways_app_id' => '200',
            'url' => 'https://old.example.com',
        ]);
    }

    public function test_second_import_creates_only_new_apps(): void
    {
        $statusPage = $this->defaultStatusPage();

        Http::fake([
            'https://api.cloudways.com/api/v2/server' => Http::sequence()
                ->push($this->serversPayload())
                ->push($this->serversPayload([
                    [
                        'id' => '200',
                        'label' => 'Sito Cliente',
                        'cname' => 'www.example.com',
                        'app_fqdn' => 'wordpress-200-100.cloudwaysapps.com',
                    ],
                    [
                        'id' => '201',
                        'label' => 'Staging WP',
                        'app_fqdn' => 'wordpress-201-100.cloudwaysapps.com',
                    ],
                    [
                        'id' => '202',
                        'label' => 'Nuovo Sito',
                        'cname' => 'example.org',
                    ],
                ])),
        ]);

        $first = app(ImportCloudwaysAppsAction::class)->handle('100', $statusPage->id, 'test-token');
        $this->assertSame(2, $first['created']);

        $second = app(ImportCloudwaysAppsAction::class)->handle('100', $statusPage->id, 'test-token');

        $this->assertSame(1, $second['created']);
        $this->assertSame(2, $second['skipped']);
        $this->assertDatabaseCount('monitors', 3);
        $this->assertDatabaseHas('monitors', [
            'name' => 'Nuovo Sito',
            'url' => 'https://example.org',
            'cloudways_app_id' => '202',
        ]);
    }

    public function test_relinks_existing_url_to_new_cloudways_ids(): void
    {
        Http::fake([
            'https://api.cloudways.com/api/v2/server' => Http::response($this->serversPayload()),
        ]);

        $statusPage = $this->defaultStatusPage();

        Monitor::query()->create([
            'name' => 'Sito Cliente',
            'url' => 'https://www.example.com',
            'status' => MonitorStatus::Unknown,
            'valid_status_codes' => [200],
            'status_page_id' => $statusPage->id,
            'cloudways_server_id' => '50',
            'cloudways_app_id' => '90',
        ]);

        $result = app(ImportCloudwaysAppsAction::class)->handle('100', $statusPage->id, 'test-token');

        $this->assertSame(1, $result['linked']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(0, $result['failed']);
        $this->assertDatabaseHas('monitors', [
            'url' => 'https://www.example.com',
            'cloudways_server_id' => '100',
            'cloudways_app_id' => '200',
        ]);
    }

    public function test_throws_when_server_is_missing(): void
    {
        Http::fake([
            'https://api.cloudways.com/api/v2/server' => Http::response($this->serversPayload()),
        ]);

        $this->expectException(CloudwaysException::class);

        app(ImportCloudwaysAppsAction::class)->handle(
            '999',
            $this->defaultStatusPage()->id,
            'test-token',
        );
    }

    private function defaultStatusPage(): StatusPage
    {
        return StatusPage::query()->where('is_default', true)->firstOrFail();
    }

    /**
     * @param  list<array<string, mixed>>|null  $apps
     * @return array<string, mixed>
     */
    private function serversPayload(?array $apps = null): array
    {
        return [
            'servers' => [
                [
                    'id' => '100',
                    'label' => 'Publimedia01',
                    'apps' => $apps ?? [
                        [
                            'id' => '200',
                            'label' => 'Sito Cliente',
                            'cname' => 'www.example.com',
                            'app_fqdn' => 'wordpress-200-100.cloudwaysapps.com',
                        ],
                        [
                            'id' => '201',
                            'label' => 'Staging WP',
                            'app_fqdn' => 'wordpress-201-100.cloudwaysapps.com',
                        ],
                    ],
                ],
            ],
        ];
    }
}
