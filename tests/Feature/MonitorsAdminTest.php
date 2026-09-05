<?php

namespace Tests\Feature;

use App\Enums\MonitorStatus;
use App\Filament\Resources\Monitors\Pages\ListMonitors;
use App\Models\Monitor;
use App\Models\StatusPage;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Support\Enums\Width;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MonitorsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMonitorSettings();
    }

    public function test_can_delete_a_monitor_from_the_list(): void
    {
        $user = User::factory()->create();
        $monitor = $this->monitor('Sito da cancellare', 'https://delete.example', MonitorStatus::Online);

        $this->actingAs($user);

        Livewire::test(ListMonitors::class)
            ->assertCanSeeTableRecords([$monitor])
            ->callAction(TestAction::make(DeleteAction::class)->table($monitor))
            ->assertNotified();

        $this->assertDatabaseMissing('monitors', ['id' => $monitor->id]);
    }

    public function test_can_search_monitors_by_name_url_and_status(): void
    {
        $user = User::factory()->create();
        $byName = $this->monitor('Porto Vecchio', 'https://porto.example', MonitorStatus::Online);
        $byUrl = $this->monitor('Altro sito', 'https://www.specificdomain.test', MonitorStatus::Online);
        $byStatus = $this->monitor('Sito giu', 'https://ko.example', MonitorStatus::Down);

        $this->actingAs($user);

        Livewire::test(ListMonitors::class)
            ->searchTable('Porto Vecchio')
            ->assertCanSeeTableRecords([$byName])
            ->assertCanNotSeeTableRecords([$byUrl, $byStatus])
            ->searchTable('specificdomain.test')
            ->assertCanSeeTableRecords([$byUrl])
            ->assertCanNotSeeTableRecords([$byName, $byStatus])
            ->searchTable('Down')
            ->assertCanSeeTableRecords([$byStatus])
            ->assertCanNotSeeTableRecords([$byName, $byUrl]);
    }

    public function test_can_filter_monitors_by_status(): void
    {
        $user = User::factory()->create();
        $online = $this->monitor('Online', 'https://ok.example', MonitorStatus::Online);
        $down = $this->monitor('Down', 'https://ko.example', MonitorStatus::Down);

        $this->actingAs($user);

        Livewire::test(ListMonitors::class)
            ->filterTable('status', MonitorStatus::Down->value)
            ->assertCanSeeTableRecords([$down])
            ->assertCanNotSeeTableRecords([$online]);
    }

    public function test_monitors_list_uses_full_content_width(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertSame(Width::Full, (new ListMonitors)->getMaxContentWidth());
    }

    private function monitor(string $name, string $url, MonitorStatus $status): Monitor
    {
        $statusPage = StatusPage::query()->where('is_default', true)->firstOrFail();

        return Monitor::query()->create([
            'name' => $name,
            'url' => $url,
            'status' => $status,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'valid_status_codes' => [200],
        ]);
    }
}
