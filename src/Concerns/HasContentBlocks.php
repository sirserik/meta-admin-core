<?php

namespace Meta\AdminCore\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Meta\AdminCore\Models\PageBlock;

/**
 * Lets a model carry a flexible stack of `page_blocks` rows attached
 * polymorphically — any block_type the consumer site supports.
 *
 *   class Procurement extends Model
 *   {
 *       use HasContentBlocks;
 *   }
 *
 *   $procurement->contentBlocks   // MorphMany — eloquent relation
 *   $procurement->blocks()->get() // ordered by sort_order
 *   PageBlock::getBlocksFor($procurement)   // cached, keyed by block_key
 *
 * The trait is the "owner side" of the polymorph; `PageBlock::blockable()`
 * is the "block side". `page_name` is also auto-stamped on save with a
 * synthetic key (`{morphAlias}-{id}`) so the existing block-editor URL
 * (/admin/blocks?page=procurement-{id}) keeps working.
 */
trait HasContentBlocks
{
    public function contentBlocks(): MorphMany
    {
        return $this->morphMany(PageBlock::class, 'blockable')->orderBy('sort_order');
    }

    /**
     * Convenience accessor — list of PageBlock models keyed by block_key,
     * ready to feed into <x-page-blocks :blocks="…">.
     */
    public function loadContentBlocks(bool $publishedOnly = true)
    {
        return PageBlock::getBlocksFor($this, $publishedOnly);
    }

    /**
     * The synthetic page_name this owner uses for legacy page_name-based
     * tools (e.g. admin block-editor's `?page=` filter, the existing
     * `<x-page-blocks page="…">` slot). Default: `{snake-class}-{id}`.
     * Override per-model when a different scheme is wanted.
     */
    public function contentBlocksPageName(): string
    {
        $alias = $this->getMorphClass();
        // App\Models\Procurement → procurement; "procurements" alias → procurement
        if (class_exists($alias)) {
            $alias = strtolower(class_basename($alias));
        }
        // Singularise simple cases (procurements → procurement) — gracefully.
        if (str_ends_with($alias, 's') && !str_ends_with($alias, 'ss')) {
            $alias = substr($alias, 0, -1);
        }
        return $alias . '-' . $this->getKey();
    }
}
