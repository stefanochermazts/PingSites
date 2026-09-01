<?php

namespace Tests\Feature;

use App\Enums\IncidentStatus;
use App\Enums\MonitorStatus;
use App\Enums\NotificationLogStatus;
use App\Enums\NotificationType;
use App\Mail\MonitorDownMail;
use App\Mail\MonitorRecoveryMail;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\StatusPage;
use App\Services\NotificationService;
use App\Settings\MonitorSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMonitorSettings();
    }

    public function test_down_notification_uses_status_page_recipients(): void
    {
        Mail::fake();

        [$monitor, $incident] = $this->monitorWithIncident(
            recipients: 'alpha@example.com, beta@example.com',
        );

        app(NotificationService::class)->sendDownNotification($monitor->id, $incident->id);

        Mail::assertSent(MonitorDownMail::class, 2);
        Mail::assertSent(MonitorDownMail::class, fn (MonitorDownMail $mail) => $mail->hasTo('alpha@example.com'));
        Mail::assertSent(MonitorDownMail::class, fn (MonitorDownMail $mail) => $mail->hasTo('beta@example.com'));
        Mail::assertNotSent(MonitorDownMail::class, fn (MonitorDownMail $mail) => $mail->hasTo('admin@example.com'));

        $this->assertDatabaseHas('notification_logs', [
            'monitor_id' => $monitor->id,
            'to_email' => 'alpha@example.com',
            'type' => NotificationType::Down->value,
            'status' => NotificationLogStatus::Sent->value,
        ]);
    }

    public function test_recovery_notification_uses_status_page_recipients(): void
    {
        Mail::fake();

        [$monitor, $incident] = $this->monitorWithIncident(
            recipients: 'ops@example.com',
        );

        app(NotificationService::class)->sendRecoveryNotification($monitor->id, $incident->id);

        Mail::assertSent(MonitorRecoveryMail::class, 1);
        Mail::assertSent(MonitorRecoveryMail::class, fn (MonitorRecoveryMail $mail) => $mail->hasTo('ops@example.com'));
        Mail::assertNotSent(MonitorRecoveryMail::class, fn (MonitorRecoveryMail $mail) => $mail->hasTo('admin@example.com'));
    }

    public function test_different_status_pages_notify_different_recipients(): void
    {
        Mail::fake();

        [$firstMonitor, $firstIncident] = $this->monitorWithIncident(
            slug: 'clienti',
            recipients: 'clienti@example.com',
        );
        [$secondMonitor, $secondIncident] = $this->monitorWithIncident(
            slug: 'interno',
            recipients: 'interno@example.com',
        );

        $service = app(NotificationService::class);
        $service->sendDownNotification($firstMonitor->id, $firstIncident->id);
        $service->sendDownNotification($secondMonitor->id, $secondIncident->id);

        Mail::assertSent(MonitorDownMail::class, 2);
        Mail::assertSent(MonitorDownMail::class, function (MonitorDownMail $mail) use ($firstMonitor) {
            return $mail->hasTo('clienti@example.com') && $mail->monitor->is($firstMonitor);
        });
        Mail::assertSent(MonitorDownMail::class, function (MonitorDownMail $mail) use ($secondMonitor) {
            return $mail->hasTo('interno@example.com') && $mail->monitor->is($secondMonitor);
        });
        Mail::assertNotSent(MonitorDownMail::class, fn (MonitorDownMail $mail) => $mail->hasTo('clienti@example.com') && $mail->monitor->is($secondMonitor));
    }

    public function test_falls_back_to_global_recipients_when_status_page_has_none(): void
    {
        Mail::fake();

        [$monitor, $incident] = $this->monitorWithIncident(recipients: null);

        app(NotificationService::class)->sendDownNotification($monitor->id, $incident->id);

        Mail::assertSent(MonitorDownMail::class, fn (MonitorDownMail $mail) => $mail->hasTo('admin@example.com'));
    }

    public function test_falls_back_to_global_recipients_when_monitor_has_no_status_page(): void
    {
        Mail::fake();

        $monitor = Monitor::query()->create([
            'name' => 'Interno',
            'url' => 'https://internal.example.com',
            'status' => MonitorStatus::Down,
            'valid_status_codes' => [200],
            'published' => false,
        ]);

        $incident = $this->createIncident($monitor);

        app(NotificationService::class)->sendDownNotification($monitor->id, $incident->id);

        Mail::assertSent(MonitorDownMail::class, fn (MonitorDownMail $mail) => $mail->hasTo('admin@example.com'));
    }

    public function test_does_not_send_when_no_recipients_are_configured(): void
    {
        Mail::fake();

        $settings = app(MonitorSettings::class);
        $settings->alert_recipients = '';
        $settings->save();

        [$monitor, $incident] = $this->monitorWithIncident(recipients: '  ,  ');

        app(NotificationService::class)->sendDownNotification($monitor->id, $incident->id);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('notification_logs', 0);
    }

    /**
     * @return array{0: Monitor, 1: Incident}
     */
    private function monitorWithIncident(?string $recipients, string $slug = 'clienti'): array
    {
        $statusPage = StatusPage::query()->create([
            'name' => $slug,
            'title' => ucfirst($slug),
            'slug' => $slug,
            'is_default' => false,
            'alert_recipients' => $recipients,
        ]);

        $monitor = Monitor::query()->create([
            'name' => 'Sito '.$slug,
            'url' => 'https://'.$slug.'.example.com',
            'status' => MonitorStatus::Down,
            'valid_status_codes' => [200],
            'published' => true,
            'status_page_id' => $statusPage->id,
        ]);

        return [$monitor, $this->createIncident($monitor)];
    }

    private function createIncident(Monitor $monitor): Incident
    {
        return $monitor->incidents()->create([
            'opened_at' => now(),
            'status' => IncidentStatus::Open,
            'initial_cause' => 'Timeout',
            'failed_checks_count' => 2,
            'public_visible' => false,
        ]);
    }
}
