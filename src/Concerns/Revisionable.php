<?php

namespace Meta\AdminCore\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Meta\AdminCore\Models\Revision;

/**
 * Auto-snapshot mixin: drops a row into the `revisions` table on every
 * `updating` event, capturing the model's attributes BEFORE the
 * incoming update is applied. Think of it as `undo` for your rows.
 *
 *   class Article extends Model {
 *       use \Meta\AdminCore\Concerns\Revisionable;
 *   }
 *
 * Opt-out per model by setting a static property:
 *
 *   protected static bool $revisionable = false;
 *
 * …or skip snapshotting an individual save with $model->withoutRevision(fn () => …).
 *
 * Retention: $model->maxRevisions = 50 (int) caps history per row,
 * keeping the newest N. Unlimited by default — consumer chooses.
 */
trait Revisionable
{
    /** Per-instance flag honoured by the booted hook. */
    protected bool $skipRevision = false;

    public static function bootRevisionable(): void
    {
        static::updating(function ($model) {
            if (!$model->shouldCreateRevision()) return;

            // Capture the pre-update state. Use getOriginal() so we
            // snapshot what's on disk right now, not the mutated
            // attributes that are about to be written.
            $payload = $model->getOriginal();

            // Drop anything the consumer flagged as hidden — they're
            // usually huge (content payloads) or sensitive (tokens).
            // Revisionable models can opt-out fields explicitly.
            if (property_exists($model, 'revisionHidden') && is_array($model->revisionHidden)) {
                foreach ($model->revisionHidden as $key) {
                    unset($payload[$key]);
                }
            }

            Revision::create([
                'revisionable_type' => $model->getMorphClass(),
                'revisionable_id'   => $model->getKey(),
                'user_id'           => Auth::id(),
                'data'              => $payload,
            ]);

            $model->pruneRevisions();
        });
    }

    protected function shouldCreateRevision(): bool
    {
        if ($this->skipRevision) return false;
        if (property_exists(static::class, 'revisionable') && static::$revisionable === false) {
            return false;
        }
        // Only snapshot if something actually changed — noop saves
        // would otherwise create churn in the revisions table.
        return $this->isDirty();
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable')->latest('created_at');
    }

    /**
     * Apply a past snapshot to the current row.
     *
     * Fills the record with the snapshot's attributes and calls save(),
     * which itself triggers another revision — so restore is undoable
     * too. Returns true on success, false if the revision belongs to a
     * different model.
     */
    public function restoreRevision(int $revisionId): bool
    {
        /** @var Revision|null $rev */
        $rev = Revision::find($revisionId);
        if (!$rev) return false;
        if ($rev->revisionable_type !== $this->getMorphClass()) return false;
        if ((int) $rev->revisionable_id !== (int) $this->getKey()) return false;

        $attrs = $rev->data ?? [];
        // Never overwrite the primary key or timestamps — they describe
        // the current row, not the snapshot's identity.
        unset($attrs[$this->getKeyName()], $attrs['created_at'], $attrs['updated_at']);
        $this->fill($attrs);
        return $this->save();
    }

    /**
     * Skip revision creation for a single save.
     *
     *   $model->withoutRevision(fn () => $model->update(['counter' => 42]));
     */
    public function withoutRevision(\Closure $fn): mixed
    {
        $prev = $this->skipRevision;
        $this->skipRevision = true;
        try { return $fn($this); } finally { $this->skipRevision = $prev; }
    }

    protected function pruneRevisions(): void
    {
        $max = (int) ($this->maxRevisions ?? 0);
        if ($max <= 0) return;

        $ids = Revision::where('revisionable_type', $this->getMorphClass())
            ->where('revisionable_id', $this->getKey())
            ->orderByDesc('created_at')
            ->pluck('id');

        if ($ids->count() <= $max) return;

        Revision::whereIn('id', $ids->slice($max))->delete();
    }
}
