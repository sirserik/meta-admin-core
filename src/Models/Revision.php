<?php

namespace Meta\AdminCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Snapshot of a revisionable model at a point in time.
 *
 * Immutable by intent — revisions are never edited after creation,
 * only created and (optionally) deleted in bulk when pruning. The
 * actual snapshot payload lives in `data` as the full attribute
 * array captured right before an `update()` was applied.
 */
class Revision extends Model
{
    protected $table = 'revisions';

    public $timestamps = false;

    protected $fillable = [
        'revisionable_type',
        'revisionable_id',
        'user_id',
        'data',
        'note',
    ];

    protected $casts = [
        'data'       => 'array',
        'created_at' => 'datetime',
    ];

    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }
}
