<?php

namespace App\Models;

use App\Enums\IncidentEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'incident_id',
        'type',
        'message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => IncidentEventType::class,
            'created_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}
