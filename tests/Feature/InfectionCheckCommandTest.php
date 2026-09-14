<?php

namespace Tests\Feature;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\StatusPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InfectionCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMonitorSettings();
        config(['cloudways.access_token' => 'test-token']);
    }

    public function test_updates_only_publimedia_monitors_from_cloudways(): void
    {
        $publimedia = $this->publimediaPage();
        $default = StatusPage::query()->where('is_default', true)->firstOrFail();

        $infected = $this->monitor($publimedia, 'Sito Infetto', 'https://evil.example', '100', '222');
        $clean = $this->monitor($publimedia, 'Sito Pulito', 'https://clean.example', '100', '111');
        $unlinked = $this->monitor($publimedia, 'Sito Senza Cloudways', 'https://orphan.example');
        $other = $this->monitor($default, 'Sito Devisia', 'https://devisia.example', '100', '333');

        Http::fake([
            'https://api.cloudways.com/api/v2/server/security/100/apps*' => Http::response([
                'apps' => [
                    ['id' => 111, 'mp_addon_active' => 0, 'infected' => 0, 'infected_db' => 0],
                    ['id' => 222, 'mp_addon_active' => 0, 'infected' => 829, 'infected_db' => 2],
                    ['id' => 333, 'mp_addon_active' => 0, 'infected' => 35, 'infected_db' => 0],
                ],
                'pagination' => ['last_page' => 1],
            ]),
        ]);

        $this->artisan('monitors:check-infections')
            ->assertSuccessful();

        $this->assertTrue($infected->fresh()->isInfected());
        $this->assertSame(829, $infected->fresh()->infection_count);
        $this->assertSame(2, $infected->fresh()->infection_db_count);
        $this->assertNotNull($infected->fresh()->infection_checked_at);
        $this->assertNotNull($infected->fresh()->infection_detected_at);
        $this->assertFalse($clean->fresh()->isInfected());
        $this->assertSame(0, $clean->fresh()->infection_count);
        $this->assertSame(0, $clean->fresh()->infection_db_count);
        $this->assertNotNull($clean->fresh()->infection_checked_at);
        $this->assertNull($clean->fresh()->infection_detected_at);
        $this->assertNull($unlinked->fresh()->isInfected());
        $this->assertNull($unlinked->fresh()->infection_checked_at);
        $this->assertNull($other->fresh()->isInfected());
        $this->assertNull($other->fresh()->infection_checked_at);

        Http::assertSentCount(1);
    }

    public function test_treats_zero_file_counts_as_clean_even_without_addon(): void
    {
        $publimedia = $this->publimediaPage();
        $monitor = $this->monitor($publimedia, 'Senza addon', 'https://off.example', '100', '444');

        Http::fake([
            'https://api.cloudways.com/api/v2/server/security/100/apps*' => Http::response([
                'apps' => [
                    ['id' => 444, 'mp_addon_active' => 0, 'infected' => 0, 'infected_db' => 0],
                ],
                'pagination' => ['last_page' => 1],
            ]),
        ]);

        $this->artisan('monitors:check-infections')
            ->assertSuccessful();

        $this->assertFalse($monitor->fresh()->isInfected());
        $this->assertNotNull($monitor->fresh()->infection_checked_at);
    }

    public function test_keeps_previous_status_when_security_suite_fails(): void
    {
        $publimedia = $this->publimediaPage();
        $monitor = $this->monitor($publimedia, 'Sito Infetto', 'https://evil.example', '100', '222', true, now()->subDay());

        Http::fake([
            'https://api.cloudways.com/api/v2/server/security/100/apps*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->artisan('monitors:check-infections')
            ->assertSuccessful();

        $this->assertTrue($monitor->fresh()->isInfected());
        $this->assertNull($monitor->fresh()->infection_checked_at);
        $this->assertNotNull($monitor->fresh()->infection_detected_at);
    }

    public function test_keeps_first_detection_and_clears_it_when_clean(): void
    {
        $publimedia = $this->publimediaPage();
        $detectedAt = now()->subDays(4);
        $monitor = $this->monitor($publimedia, 'Sito Infetto', 'https://evil.example', '100', '222', true, $detectedAt);

        Http::fake([
            'https://api.cloudways.com/api/v2/server/security/100/apps*' => Http::sequence()
                ->push([
                    'apps' => [
                        ['id' => 222, 'infected' => 12, 'infected_db' => 0],
                    ],
                    'pagination' => ['last_page' => 1],
                ])
                ->push([
                    'apps' => [
                        ['id' => 222, 'infected' => 0, 'infected_db' => 0],
                    ],
                    'pagination' => ['last_page' => 1],
                ]),
        ]);

        $this->artisan('monitors:check-infections')
            ->assertSuccessful();

        $this->assertTrue($monitor->fresh()->isInfected());
        $this->assertSame(
            $detectedAt->timezone((string) config('app.timezone'))->format('Y-m-d H:i:s'),
            $monitor->fresh()->infection_detected_at?->timezone((string) config('app.timezone'))->format('Y-m-d H:i:s'),
        );

        $this->artisan('monitors:check-infections')
            ->assertSuccessful();

        $this->assertFalse($monitor->fresh()->isInfected());
        $this->assertNull($monitor->fresh()->infection_detected_at);
    }

    private function publimediaPage(): StatusPage
    {
        return StatusPage::query()->create([
            'name' => 'Publimedia',
            'title' => 'Publimedia Status',
            'slug' => 'publimedia',
            'is_default' => false,
        ]);
    }

    private function monitor(
        StatusPage $statusPage,
        string $name,
        string $url,
        ?string $serverId = null,
        ?string $appId = null,
        ?bool $infected = null,
        ?\DateTimeInterface $detectedAt = null,
    ): Monitor {
        return Monitor::query()->create([
            'name' => $name,
            'url' => $url,
            'status' => MonitorStatus::Online,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'valid_status_codes' => [200],
            'cloudways_server_id' => $serverId,
            'cloudways_app_id' => $appId,
            'is_infected' => $infected,
            'infection_detected_at' => $detectedAt,
        ]);
    }
}
