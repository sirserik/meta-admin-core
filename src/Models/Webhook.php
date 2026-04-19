<?php

namespace Meta\AdminCore\Models;

use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    protected $table = 'webhooks';

    protected $fillable = [
        'label',
        'url',
        'events',
        'secret',
        'is_active',
        'last_fired_at',
    ];

    protected $casts = [
        'events'        => 'array',
        'is_active'     => 'boolean',
        'last_fired_at' => 'datetime',
    ];

    public function listensTo(string $event): bool
    {
        return $this->is_active && in_array($event, (array) $this->events, true);
    }
}
