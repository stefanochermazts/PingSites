<?php

namespace App\Models;

use App\Enums\ErrorType;
use App\Enums\IncidentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    protected $fillable = [
        'monitor_id',
        'opened_at',
        'closed_at',
        'status',
        'initial_cause',
        'last_error_type',
        'failed_checks_count',
        'duration_seconds',
        'last_positive_at',
        'public_visible',
        'public_message',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'status' => IncidentStatus::class,
            'last_error_type' => ErrorType::class,
            'last_positive_at' => 'datetime',
            'public_visible' => 'boolean',
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(IncidentEvent::class)->orderBy('created_at');
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function isOpen(): bool
    {
        return $this->status === IncidentStatus::Open;
    }

    public function publicMessage(): string
    {
        if ($this->public_message) {
            return $this->public_message;
        }

        return 'Problema rilevato alle '.$this->opened_at->format('H:i').'. Sono in corso verifiche.';
    }
}
