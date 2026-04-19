<?php

namespace Meta\AdminCore\Concerns;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scheduled publishing mixin.
 *
 * Adds two timestamp columns to the table (via a helper on the
 * migration side): `publish_at` — when the row becomes visible to
 * the public, and `unpublish_at` — when it disappears again.
 *
 * The `admin-core:apply-schedule` console command flips `status`
 * between 'draft' and 'published' whenever the current time crosses
 * one of the timestamps. Until the scheduler runs, editors can still
 * toggle status manually — `publish_at` just pre-sets the intent.
 *
 * Models opt in:
 *
 *   class Article extends Model {
 *       use \Meta\AdminCore\Concerns\Publishable;
 *   }
 *
 * …and register themselves with the package so the scheduler finds
 * them (in a service provider `boot()`):
 *
 *   AdminCore::schedulable(\App\Models\Article::class);
 *
 * Columns expected on the model's table:
 *   - status         string   'draft'|'published' (default 'draft')
 *   - publish_at     datetime nullable — auto-flip to 'published' when reached
 *   - unpublish_at   datetime nullable — auto-flip to 'draft' when reached
 */
trait Publishable
{
    public function initializePublishable(): void
    {
        $this->casts = array_merge($this->casts ?? [], [
            'publish_at'   => 'datetime',
            'unpublish_at' => 'datetime',
        ]);
    }

    public function isPublished(): bool
    {
        return ($this->status ?? null) === 'published';
    }

    public function isScheduled(): bool
    {
        return $this->publish_at !== null && $this->publish_at->isFuture();
    }

    public function willUnpublish(): bool
    {
        return $this->unpublish_at !== null && $this->unpublish_at->isFuture();
    }

    /** Rows currently visible to the public. */
    public function scopePublished(Builder $q): Builder
    {
        $now = Carbon::now();
        return $q->where('status', 'published')
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('publish_at')->orWhere('publish_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('unpublish_at')->orWhere('unpublish_at', '>', $now);
            });
    }

    /** Rows scheduled to become public in the future. */
    public function scopeScheduled(Builder $q): Builder
    {
        return $q->where('status', 'draft')
            ->whereNotNull('publish_at')
            ->where('publish_at', '>', Carbon::now());
    }

    /** Rows whose `publish_at` has passed but status is still 'draft'. */
    public function scopeDuePublish(Builder $q): Builder
    {
        return $q->where('status', 'draft')
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', Carbon::now());
    }

    /** Rows whose `unpublish_at` has passed but status is still 'published'. */
    public function scopeDueUnpublish(Builder $q): Builder
    {
        return $q->where('status', 'published')
            ->whereNotNull('unpublish_at')
            ->where('unpublish_at', '<=', Carbon::now());
    }
}
