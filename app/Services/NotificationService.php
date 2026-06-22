<?php

namespace App\Services;

use App\Enums\IncidentEventType;
use App\Enums\NotificationLogStatus;
use App\Enums\NotificationType;
use App\Mail\MonitorDownMail;
use App\Mail\MonitorRecoveryMail;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationLog;
use App\Settings\MonitorSettings;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationService
{
    public function __construct(
        private readonly MonitorSettings $settings,
        private readonly IncidentManager $incidentManager,
    ) {}

    public function sendDownNotification(int $monitorId, int $incidentId): void
    {
        $monitor = Monitor::query()->findOrFail($monitorId);
        $incident = Incident::query()->findOrFail($incidentId);

        $recipients = $this->recipients();

        foreach ($recipients as $recipient) {
            $subject = '[Monitor] Sito non disponibile: '.$monitor->name;

            try {
                Mail::to($recipient)->send(new MonitorDownMail($monitor, $incident));

                $this->logNotification($monitor, $incident, NotificationType::Down, $recipient, $subject, NotificationLogStatus::Sent);
                $this->incidentManager->recordNotificationEvent($incident, IncidentEventType::DownEmailSent, "Email down inviata a {$recipient}");
            } catch (Throwable $exception) {
                $this->logNotification(
                    $monitor,
                    $incident,
                    NotificationType::Down,
                    $recipient,
                    $subject,
                    NotificationLogStatus::Failed,
                    $exception->getMessage(),
                );
                $this->incidentManager->recordNotificationEvent(
                    $incident,
                    IncidentEventType::DownEmailFailed,
                    "Email down fallita per {$recipient}: {$exception->getMessage()}",
                );
            }
        }
    }

    public function sendRecoveryNotification(int $monitorId, int $incidentId): void
    {
        $monitor = Monitor::query()->findOrFail($monitorId);
        $incident = Incident::query()->findOrFail($incidentId);

        $recipients = $this->recipients();

        foreach ($recipients as $recipient) {
            $subject = '[Monitor] Sito tornato online: '.$monitor->name;

            try {
                Mail::to($recipient)->send(new MonitorRecoveryMail($monitor, $incident));

                $this->logNotification($monitor, $incident, NotificationType::Recovery, $recipient, $subject, NotificationLogStatus::Sent);
                $this->incidentManager->recordNotificationEvent($incident, IncidentEventType::RecoveryEmailSent, "Email recovery inviata a {$recipient}");
            } catch (Throwable $exception) {
                $this->logNotification(
                    $monitor,
                    $incident,
                    NotificationType::Recovery,
                    $recipient,
                    $subject,
                    NotificationLogStatus::Failed,
                    $exception->getMessage(),
                );
                $this->incidentManager->recordNotificationEvent(
                    $incident,
                    IncidentEventType::RecoveryEmailFailed,
                    "Email recovery fallita per {$recipient}: {$exception->getMessage()}",
                );
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function recipients(): array
    {
        return collect(explode(',', $this->settings->alert_recipients))
            ->map(fn (string $email) => trim($email))
            ->filter()
            ->values()
            ->all();
    }

    private function logNotification(
        Monitor $monitor,
        Incident $incident,
        NotificationType $type,
        string $toEmail,
        string $subject,
        NotificationLogStatus $status,
        ?string $error = null,
    ): void {
        NotificationLog::query()->create([
            'monitor_id' => $monitor->id,
            'incident_id' => $incident->id,
            'type' => $type,
            'to_email' => $toEmail,
            'subject' => $subject,
            'status' => $status,
            'error' => $error,
            'sent_at' => $status === NotificationLogStatus::Sent ? now() : null,
        ]);
    }
}
