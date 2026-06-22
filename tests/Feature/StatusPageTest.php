<?php

namespace Tests\Feature;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_status_page_is_publicly_accessible(): void
    {
        $response = $this->get('/status');

        $response->assertOk();
        $response->assertSee('Devisia Status');
    }

    public function test_status_page_lists_multiple_published_monitors_with_cache(): void
    {
        Monitor::query()->create([
            'name' => 'Sito A',
            'url' => 'https://example.com',
            'status' => MonitorStatus::Online,
            'published' => true,
            'public_name' => 'Sito A',
            'valid_status_codes' => [200],
        ]);

        Monitor::query()->create([
            'name' => 'Sito B',
            'url' => 'https://example.org',
            'status' => MonitorStatus::Online,
            'published' => true,
            'public_name' => 'Sito B',
            'valid_status_codes' => [200],
        ]);

        Cache::flush();

        $first = $this->get('/status');
        $first->assertOk();
        $first->assertSee('Sito A');
        $first->assertSee('Sito B');

        $second = $this->get('/status');
        $second->assertOk();
        $second->assertSee('Sito A');
        $second->assertSee('Sito B');
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
