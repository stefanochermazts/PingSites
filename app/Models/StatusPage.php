<?php

namespace App\Models;

use App\Services\StatusPageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatusPage extends Model
{
    public const INFECTION_SLUG = 'publimedia';

    protected $fillable = [
        'name',
        'title',
        'slug',
        'is_default',
        'alert_recipients',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (StatusPage $statusPage): void {
            if ($statusPage->is_default) {
                static::query()
                    ->whereKeyNot($statusPage->id)
                    ->update(['is_default' => false]);
            }

            StatusPageService::forgetAllCaches();
        });

        static::deleted(fn () => StatusPageService::forgetAllCaches());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function monitors(): HasMany
    {
        return $this->hasMany(Monitor::class);
    }

    public function publishedMonitors(): HasMany
    {
        return $this->hasMany(Monitor::class)->where('published', true);
    }

    public function publicUrl(): string
    {
        return url('/status/'.$this->slug);
    }

    public function showsInfectionStatus(): bool
    {
        return $this->slug === self::INFECTION_SLUG;
    }

    /**
     * @return array<int, string>
     */
    public function recipientEmails(): array
    {
        return self::parseRecipients($this->alert_recipients);
    }

    /**
     * @return array<int, string>
     */
    public static function parseRecipients(?string $raw): array
    {
        return collect(explode(',', (string) $raw))
            ->map(fn (string $email) => trim($email))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
