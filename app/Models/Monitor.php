<?php

namespace App\Models;

use App\Enums\ErrorType;
use App\Enums\MonitorStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Monitor extends Model
{
    protected $fillable = [
        'name',
        'url',
        'status',
        'is_active',
        'check_frequency',
        'timeout',
        'valid_status_codes',
        'follow_redirects',
        'verify_ssl',
        'keyword',
        'failure_threshold',
        'recovery_threshold',
        'published',
        'public_name',
        'internal_notes',
        'consecutive_failures',
        'consecutive_successes',
        'last_checked_at',
        'next_check_at',
        'last_http_code',
        'last_response_time_ms',
        'last_error_type',
    ];

    protected function casts(): array
    {
        return [
            'status' => MonitorStatus::class,
            'is_active' => 'boolean',
            'valid_status_codes' => 'array',
            'follow_redirects' => 'boolean',
            'verify_ssl' => 'boolean',
            'published' => 'boolean',
            'last_checked_at' => 'datetime',
            'next_check_at' => 'datetime',
            'last_error_type' => ErrorType::class,
        ];
    }

    public function checks(): HasMany
    {
        return $this->hasMany(Check::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function openIncident(): HasOne
    {
        return $this->hasOne(Incident::class)->where('status', 'open');
    }

    public function maintenanceWindows(): BelongsToMany
    {
        return $this->belongsToMany(MaintenanceWindow::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function isPaused(): bool
    {
        return ! $this->is_active || $this->status === MonitorStatus::Paused;
    }

    public function displayPublicName(): string
    {
        return $this->public_name ?: $this->name;
    }

    public function scheduleNextCheck(?int $offsetSeconds = null): void
    {
        $frequencySeconds = $this->check_frequency * 60;

        if ($offsetSeconds !== null) {
            $this->next_check_at = now()->addSeconds($offsetSeconds);
        } else {
            $this->next_check_at = now()->addSeconds($frequencySeconds);
        }
    }
}
