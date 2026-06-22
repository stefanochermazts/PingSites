<?php

namespace App\Models;

use App\Enums\ErrorType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Check extends Model
{
    protected $fillable = [
        'monitor_id',
        'success',
        'http_code',
        'response_time_ms',
        'error_type',
        'error_message',
        'is_manual',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'is_manual' => 'boolean',
            'checked_at' => 'datetime',
            'error_type' => ErrorType::class,
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
