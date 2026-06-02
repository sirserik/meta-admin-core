<?php

namespace Meta\AdminCore\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * URL redirect rule (from_url → to_url, status_code). Applied at runtime by
 * the HandleRedirects middleware. `hits` is optional (added by a later
 * migration) and counts how often the rule fired.
 */
class Redirect extends Model
{
    protected $fillable = ['from_url', 'to_url', 'status_code', 'is_active', 'hits'];

    protected $casts = [
        'is_active'   => 'boolean',
        'status_code' => 'integer',
        'hits'        => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
