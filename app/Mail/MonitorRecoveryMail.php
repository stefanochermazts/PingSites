<?php

namespace App\Mail;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MonitorRecoveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Monitor $monitor,
        public Incident $incident,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Monitor] Sito tornato online: '.$this->monitor->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.monitor-recovery',
            with: [
                'monitor' => $this->monitor,
                'incident' => $this->incident,
                'adminUrl' => url('/admin/incidents/'.$this->incident->id),
            ],
        );
    }
}
