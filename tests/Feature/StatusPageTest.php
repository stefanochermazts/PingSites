<?php

namespace Tests\Feature;

use App\Enums\MonitorStatus;
use App\Models\Check;
use App\Models\Monitor;
use App\Models\StatusPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StatusPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMonitorSettings();
    }

    private function defaultStatusPage(): StatusPage
    {
        return StatusPage::query()->where('is_default', true)->firstOrFail();
    }

    public function test_status_page_redirects_to_default_slug(): void
    {
        $default = $this->defaultStatusPage();

        $this->get('/status')
            ->assertRedirect(route('status.show', $default));
    }

    public function test_status_page_is_publicly_accessible(): void
    {
        $default = $this->defaultStatusPage();

        $response = $this->get(route('status.show', $default));

        $response->assertOk();
        $response->assertSee('Devisia Status');
    }

    public function test_status_page_lists_monitors_for_selected_page_only(): void
    {
        $default = $this->defaultStatusPage();

        $clientPage = StatusPage::query()->create([
            'name' => 'Clienti',
            'title' => 'Client Status',
            'slug' => 'clienti',
            'is_default' => false,
        ]);

        Monitor::query()->create([
            'name' => 'Sito A',
            'url' => 'https://example.com',
            'status' => MonitorStatus::Online,
            'published' => true,
            'status_page_id' => $default->id,
            'public_name' => 'Sito A',
            'valid_status_codes' => [200],
        ]);

        Monitor::query()->create([
            'name' => 'Sito B',
            'url' => 'https://example.org',
            'status' => MonitorStatus::Online,
            'published' => true,
            'status_page_id' => $clientPage->id,
            'public_name' => 'Sito B',
            'valid_status_codes' => [200],
        ]);

        Cache::flush();

        $this->get(route('status.show', $default))
            ->assertOk()
            ->assertSee('Sito A')
            ->assertDontSee('Sito B');

        $this->get(route('status.show', $clientPage))
            ->assertOk()
            ->assertSee('Sito B')
            ->assertDontSee('Sito A');
    }

    public function test_monitor_detail_page_shows_recent_checks(): void
    {
        $statusPage = $this->defaultStatusPage();

        $monitor = Monitor::query()->create([
            'name' => 'Sito A',
            'url' => 'https://example.com',
            'status' => MonitorStatus::Online,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'public_name' => 'Sito A',
            'valid_status_codes' => [200],
            'last_checked_at' => now(),
            'last_response_time_ms' => 120,
        ]);

        Check::query()->create([
            'monitor_id' => $monitor->id,
            'success' => true,
            'http_code' => 200,
            'response_time_ms' => 120,
            'checked_at' => now()->subMinute(),
        ]);

        Cache::flush();

        $response = $this->get(route('status.monitor', [$statusPage, $monitor]));

        $response->assertOk();
        $response->assertSee('Sito A');
        $response->assertSee('Tempi di risposta');
        $response->assertSee('120 ms');
        $response->assertDontSee('dns_error');
    }

    public function test_monitor_detail_displays_check_time_in_app_timezone(): void
    {
        config(['app.timezone' => 'Europe/Rome']);

        $statusPage = $this->defaultStatusPage();

        $monitor = Monitor::query()->create([
            'name' => 'Sito A',
            'url' => 'https://example.com',
            'status' => MonitorStatus::Online,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'public_name' => 'Sito A',
            'valid_status_codes' => [200],
        ]);

        Check::query()->create([
            'monitor_id' => $monitor->id,
            'success' => true,
            'http_code' => 200,
            'response_time_ms' => 120,
            'checked_at' => Carbon::parse('2026-06-29 10:43:03', 'Europe/Rome'),
        ]);

        Cache::flush();

        $this->get(route('status.monitor', [$statusPage, $monitor]))
            ->assertOk()
            ->assertSee('29/06/2026 10:43:03');
    }

    public function test_monitor_on_other_status_page_returns_not_found(): void
    {
        $default = $this->defaultStatusPage();

        $clientPage = StatusPage::query()->create([
            'name' => 'Clienti',
            'title' => 'Client Status',
            'slug' => 'clienti',
            'is_default' => false,
        ]);

        $monitor = Monitor::query()->create([
            'name' => 'Sito B',
            'url' => 'https://example.org',
            'status' => MonitorStatus::Online,
            'published' => true,
            'status_page_id' => $clientPage->id,
            'valid_status_codes' => [200],
        ]);

        $this->get(route('status.monitor', [$default, $monitor]))->assertNotFound();
    }

    public function test_unpublished_monitor_detail_returns_not_found(): void
    {
        $statusPage = $this->defaultStatusPage();

        $monitor = Monitor::query()->create([
            'name' => 'Interno',
            'url' => 'https://example.com',
            'status' => MonitorStatus::Online,
            'published' => false,
            'valid_status_codes' => [200],
        ]);

        $this->get(route('status.monitor', [$statusPage, $monitor]))->assertNotFound();
    }

    public function test_admin_panel_requires_authentication(): void
    {
        $this->get('/admin/monitors')->assertRedirect('/admin/login');
    }

    public function test_authenticated_user_can_access_monitors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/monitors')
            ->assertOk();
    }
}
