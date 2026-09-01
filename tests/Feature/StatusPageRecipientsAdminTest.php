<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings;
use App\Filament\Resources\StatusPages\Pages\CreateStatusPage;
use App\Filament\Resources\StatusPages\Pages\EditStatusPage;
use App\Models\StatusPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StatusPageRecipientsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMonitorSettings();
    }

    public function test_status_page_form_saves_recipients(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(CreateStatusPage::class)
            ->fillForm([
                'name' => 'Clienti',
                'title' => 'Status Clienti',
                'slug' => 'clienti',
                'is_default' => false,
                'alert_recipients' => 'clienti@example.com, ops@example.com',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('status_pages', [
            'slug' => 'clienti',
            'alert_recipients' => 'clienti@example.com, ops@example.com',
        ]);
    }

    public function test_status_page_form_requires_recipients(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(CreateStatusPage::class)
            ->fillForm([
                'name' => 'Clienti',
                'title' => 'Status Clienti',
                'slug' => 'clienti',
                'alert_recipients' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['alert_recipients']);
    }

    public function test_status_page_edit_updates_recipients(): void
    {
        $user = User::factory()->create();
        $statusPage = StatusPage::query()->where('is_default', true)->firstOrFail();

        $this->actingAs($user);

        Livewire::test(EditStatusPage::class, ['record' => $statusPage->slug])
            ->fillForm([
                'alert_recipients' => 'nuovo@example.com',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('nuovo@example.com', $statusPage->fresh()->alert_recipients);
    }

    public function test_status_pages_index_shows_recipients_column(): void
    {
        $user = User::factory()->create();
        $statusPage = StatusPage::query()->where('is_default', true)->firstOrFail();
        $statusPage->update(['alert_recipients' => 'lista@example.com']);

        $this->actingAs($user)
            ->get('/admin/status-pages')
            ->assertOk()
            ->assertSee('Destinatari')
            ->assertSee('lista@example.com');
    }

    public function test_global_settings_no_longer_edit_recipients(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(Settings::getUrl())
            ->assertOk()
            ->assertSee('I destinatari delle notifiche si configurano su ciascuna status page. Mittente e nome restano globali.')
            ->assertDontSee('Destinatari (separati da virgola)');
    }
}
