<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FailedJob extends Model
{
    protected $table = 'failed_jobs';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
        ];
    }

    public function getPayloadDataAttribute(): array
    {
        $payload = $this->attributes['payload'] ?? null;

        if (! is_string($payload)) {
            return [];
        }

        return json_decode($payload, true) ?? [];
    }

    public function getJobNameAttribute(): string
    {
        $data = $this->payload_data;

        $name = $data['displayName']
            ?? $data['data']['commandName']
            ?? $data['job']
            ?? 'Job sconosciuto';

        return class_basename($name);
    }

    public function getMonitorIdAttribute(): ?int
    {
        $command = $this->payload_data['data']['command'] ?? null;

        if (! is_string($command)) {
            return null;
        }

        if (preg_match('/s:9:"monitorId";i:(\d+)/', $command, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    public function getExceptionSummaryAttribute(): string
    {
        $firstLine = strtok($this->exception ?? '', "\n");

        return Str::limit($firstLine ?: '-', 160);
    }
}
