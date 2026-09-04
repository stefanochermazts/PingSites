<?php

namespace Tests\Feature;

use App\Enums\ErrorType;
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

    public function test_status_page_can_filter_monitors_by_status(): void
    {
        $statusPage = $this->defaultStatusPage();

        Monitor::query()->create([
            'name' => 'Sito Online',
            'url' => 'https://example.com',
            'status' => MonitorStatus::Online,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'public_name' => 'Sito Online',
            'valid_status_codes' => [200],
        ]);

        Monitor::query()->create([
            'name' => 'Sito Down',
            'url' => 'https://example.org',
            'status' => MonitorStatus::Down,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'public_name' => 'Sito Down',
            'valid_status_codes' => [200],
        ]);

        Cache::flush();

        $this->get(route('status.show', $statusPage))
            ->assertOk()
            ->assertSee('Sito Online')
            ->assertSee('Sito Down')
            ->assertSee('Tutti')
            ->assertSee('Operativo')
            ->assertSee('Problemi rilevati');

        $this->get(route('status.show', ['statusPage' => $statusPage, 'status' => 'down']))
            ->assertOk()
            ->assertSee('Sito Down')
            ->assertDontSee('Sito Online');

        $this->get(route('status.show', ['statusPage' => $statusPage, 'status' => 'operational']))
            ->assertOk()
            ->assertSee('Sito Online')
            ->assertDontSee('Sito Down');
    }

    public function test_status_page_ignores_invalid_status_filter(): void
    {
        $statusPage = $this->defaultStatusPage();

        Monitor::query()->create([
            'name' => 'Sito Online',
            'url' => 'https://example.com',
            'status' => MonitorStatus::Online,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'public_name' => 'Sito Online',
            'valid_status_codes' => [200],
        ]);

        Cache::flush();

        $this->get(route('status.show', ['statusPage' => $statusPage, 'status' => 'not-a-status']))
            ->assertOk()
            ->assertSee('Sito Online');

        $this->get('/status/'.$statusPage->slug.'?status[]=down')
            ->assertOk()
            ->assertSee('Sito Online');
    }

    public function test_status_page_filters_published_and_unpublished_urls_with_status(): void
    {
        $statusPage = $this->defaultStatusPage();

        Monitor::query()->create([
            'name' => 'Dominio Custom Down',
            'url' => 'https://www.cliente.it',
            'status' => MonitorStatus::Down,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'public_name' => 'Dominio Custom Down',
            'valid_status_codes' => [200],
        ]);

        Monitor::query()->create([
            'name' => 'Cloudways Down',
            'url' => 'https://wordpress-1-2.cloudwaysapps.com',
            'status' => MonitorStatus::Down,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'public_name' => 'Cloudways Down',
            'valid_status_codes' => [200],
        ]);

        Monitor::query()->create([
            'name' => 'Dominio Custom Online',
            'url' => 'https://www.ok.it',
            'status' => MonitorStatus::Online,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'public_name' => 'Dominio Custom Online',
            'valid_status_codes' => [200],
        ]);

        Cache::flush();

        $this->get(route('status.show', ['statusPage' => $statusPage, 'pubblicazione' => 'non-pubblicati']))
            ->assertOk()
            ->assertSee('Cloudways Down')
            ->assertDontSee('Dominio Custom Down')
            ->assertDontSee('Dominio Custom Online');

        $this->get(route('status.show', [
            'statusPage' => $statusPage,
            'status' => 'down',
            'pubblicazione' => 'pubblicati',
        ]))
            ->assertOk()
            ->assertSee('Dominio Custom Down')
            ->assertDontSee('Cloudways Down')
            ->assertDontSee('Dominio Custom Online');
    }

    public function test_status_page_shows_monitor_url_and_error_detail(): void
    {
        $statusPage = $this->defaultStatusPage();

        Monitor::query()->create([
            'name' => 'Sito Down',
            'url' => 'https://down.example.com',
            'status' => MonitorStatus::Down,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'public_name' => 'Sito Down',
            'valid_status_codes' => [200],
            'last_http_code' => 503,
            'last_error_type' => ErrorType::Http5xx,
        ]);

        Monitor::query()->create([
            'name' => 'Sito Timeout',
            'url' => 'https://timeout.example.com',
            'status' => MonitorStatus::Down,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'public_name' => 'Sito Timeout',
            'valid_status_codes' => [200],
            'last_error_type' => ErrorType::Timeout,
        ]);

        Cache::flush();

        $this->get(route('status.show', $statusPage))
            ->assertOk()
            ->assertSee('https://down.example.com')
            ->assertSee('https://timeout.example.com')
            ->assertSee('href="https://down.example.com"', false)
            ->assertSee('href="https://timeout.example.com"', false)
            ->assertSee('HTTP 503')
            ->assertSee('Timeout')
            ->assertDontSee('http_5xx')
            ->assertDontSee('dns_error');
    }

    public function test_publimedia_status_page_shows_infection_column(): void
    {
        $publimedia = StatusPage::query()->create([
            'name' => 'Publimedia',
            'title' => 'Publimedia Status',
            'slug' => 'publimedia',
            'is_default' => false,
        ]);

        Monitor::query()->create([
            'name' => 'Sito Cliente',
            'url' => 'https://cliente.example',
            'status' => MonitorStatus::Online,
            'published' => true,
            'status_page_id' => $publimedia->id,
            'public_name' => 'Sito Cliente',
            'valid_status_codes' => [200],
            'is_infected' => true,
            'infection_checked_at' => now(),
        ]);

        Cache::flush();

        $this->get(route('status.show', $publimedia))
            ->assertOk()
            ->assertSee('Infezione')
            ->assertSee('Infetto')
            ->assertSee('Sito Cliente');
    }

    public function test_publimedia_status_page_can_filter_infected_monitors(): void
    {
        $publimedia = StatusPage::query()->create([
            'name' => 'Publimedia',
            'title' => 'Publimedia Status',
            'slug' => 'publimedia',
            'is_default' => false,
        ]);

        Monitor::query()->create([
            'name' => 'Sito Infetto',
            'url' => 'https://infected.example',
            'status' => MonitorStatus::Online,
            'published' => true,
            'status_page_id' => $publimedia->id,
            'public_name' => 'Sito Infetto',
            'valid_status_codes' => [200],
            'is_infected' => true,
            'infection_checked_at' => now(),
        ]);

        Monitor::query()->create([
            'name' => 'Sito Pulito',
            'url' => 'https://clean.example',
            'status' => MonitorStatus::Online,
            'published' => true,
            'status_page_id' => $publimedia->id,
            'public_name' => 'Sito Pulito',
            'valid_status_codes' => [200],
            'is_infected' => false,
            'infection_checked_at' => now(),
        ]);

        Cache::flush();

        $this->get(route('status.show', $publimedia))
            ->assertOk()
            ->assertSee('Sito Infetto')
            ->assertSee('Sito Pulito')
            ->assertSee('href="'.route('status.show', ['statusPage' => $publimedia, 'status' => 'infected']).'"', false);

        $this->get(route('status.show', ['statusPage' => $publimedia, 'status' => 'infected']))
            ->assertOk()
            ->assertSee('Sito Infetto')
            ->assertDontSee('Sito Pulito');
    }

    public function test_other_status_pages_do_not_show_infection_column(): void
    {
        $statusPage = $this->defaultStatusPage();

        Monitor::query()->create([
            'name' => 'Sito A',
            'url' => 'https://example.com',
            'status' => MonitorStatus::Online,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'public_name' => 'Sito A',
            'valid_status_codes' => [200],
            'is_infected' => true,
            'infection_checked_at' => now(),
        ]);

        Cache::flush();

        $this->get(route('status.show', $statusPage))
            ->assertOk()
            ->assertSee('Sito A')
            ->assertDontSee('Infezione')
            ->assertDontSee('Infetto');
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
        $response->assertSee('https://example.com');
        $response->assertSee('href="https://example.com"', false);
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

    public function test_status_page_sorts_numeric_columns_ascending_and_descending(): void
    {
        $statusPage = $this->defaultStatusPage();

        $slow = Monitor::query()->create([
            'name' => 'Sito Lento',
            'url' => 'https://lento.example',
            'status' => MonitorStatus::Online,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'public_name' => 'Sito Lento',
            'valid_status_codes' => [200],
            'last_checked_at' => now()->subHours(3),
            'last_response_time_ms' => 900,
        ]);
        $fast = Monitor::query()->create([
            'name' => 'Sito Veloce',
            'url' => 'https://veloce.example',
            'status' => MonitorStatus::Online,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'public_name' => 'Sito Veloce',
            'valid_status_codes' => [200],
            'last_checked_at' => now()->subMinute(),
            'last_response_time_ms' => 80,
        ]);

        Check::query()->create([
            'monitor_id' => $slow->id,
            'success' => false,
            'http_code' => 500,
            'response_time_ms' => 900,
            'checked_at' => now()->subMinutes(2),
        ]);
        Check::query()->create([
            'monitor_id' => $fast->id,
            'success' => true,
            'http_code' => 200,
            'response_time_ms' => 80,
            'checked_at' => now()->subMinute(),
        ]);

        Cache::flush();

        $this->get(route('status.show', $statusPage))
            ->assertOk()
            ->assertSee('ordina=risposta', false)
            ->assertSee('ordina=controllo', false)
            ->assertSee('ordina=disponibilita', false);

        $this->get(route('status.show', [
            'statusPage' => $statusPage,
            'ordina' => 'risposta',
            'dir' => 'desc',
        ]))
            ->assertOk()
            ->assertSeeInOrder(['Sito Lento', 'Sito Veloce'])
            ->assertSee('ordina=risposta', false)
            ->assertSee('dir=asc', false);

        $this->get(route('status.show', [
            'statusPage' => $statusPage,
            'ordina' => 'risposta',
            'dir' => 'asc',
        ]))
            ->assertOk()
            ->assertSeeInOrder(['Sito Veloce', 'Sito Lento']);

        $this->get(route('status.show', [
            'statusPage' => $statusPage,
            'ordina' => 'controllo',
            'dir' => 'asc',
        ]))
            ->assertOk()
            ->assertSeeInOrder(['Sito Lento', 'Sito Veloce']);

        $this->get(route('status.show', [
            'statusPage' => $statusPage,
            'ordina' => 'disponibilita',
            'dir' => 'asc',
        ]))
            ->assertOk()
            ->assertSeeInOrder(['Sito Lento', 'Sito Veloce']);
    }

    public function test_status_page_ignores_invalid_sort_and_keeps_filters(): void
    {
        $statusPage = $this->defaultStatusPage();

        Monitor::query()->create([
            'name' => 'Sito Online',
            'url' => 'https://ok.example',
            'status' => MonitorStatus::Online,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'public_name' => 'Sito Online',
            'valid_status_codes' => [200],
            'last_response_time_ms' => 50,
        ]);
        Monitor::query()->create([
            'name' => 'Sito Down',
            'url' => 'https://ko.example',
            'status' => MonitorStatus::Down,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'public_name' => 'Sito Down',
            'valid_status_codes' => [200],
            'last_response_time_ms' => 800,
        ]);

        Cache::flush();

        $this->get(route('status.show', [
            'statusPage' => $statusPage,
            'ordina' => 'nome',
            'dir' => 'sideways',
        ]))
            ->assertOk()
            ->assertSee('Sito Down')
            ->assertSee('Sito Online');

        $this->get(route('status.show', [
            'statusPage' => $statusPage,
            'status' => 'down',
            'ordina' => 'risposta',
            'dir' => 'desc',
        ]))
            ->assertOk()
            ->assertSee('Sito Down')
            ->assertDontSee('Sito Online')
            ->assertSee('status=down', false)
            ->assertSee('ordina=risposta', false);
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
