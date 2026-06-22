<?php

namespace App\Models;

use App\Services\StatusPageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MaintenanceWindow extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => StatusPageService::forgetCache());
        static::deleted(fn () => StatusPageService::forgetCache());
    }

    protected $fillable = [
        'title',
        'starts_at',
        'ends_at',
        'public_visible',
        'public_message',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'public_visible' => 'boolean',
        ];
    }

    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class);
    }

    public function isActive(): bool
    {
        return now()->between($this->starts_at, $this->ends_at);
    }

    public function isUpcoming(): bool
    {
        return $this->starts_at->isFuture();
    }
}
