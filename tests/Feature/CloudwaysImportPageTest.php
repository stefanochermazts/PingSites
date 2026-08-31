<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloudwaysImportPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitors_page_shows_cloudways_import_action(): void
    {
        $this->seedMonitorSettings();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/monitors')
            ->assertOk()
            ->assertSee('Importa da Cloudways');
    }
}
