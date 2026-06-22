<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
