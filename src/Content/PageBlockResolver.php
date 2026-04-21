<?php

namespace Meta\AdminCore\Content;

use ArrayAccess;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

/**
 * Fluent query builder over `page_blocks` that returns augmented
 * `PresentedBlock` instances instead of raw models. Designed to be the
 * single entry point templates use when they need to read block content.
 *
 *   $blocks = PageBlockResolver::forPage('sdg-resources')->get();
 *   $blocks->get('main_links')->title       // "Ресурсы и документы"
 *   $blocks->get('main_links')->links       // translatable items resolved
 *
 *   // Force a specific locale (e.g. for an /en/ route variant)
 *   PageBlockResolver::forPage('home')->locale('en')->get();
 *
 *   // Include drafts (admin preview)
 *   PageBlockResolver::forPage('home')->withDrafts()->get();
 *
 *   // Grab a single block
 *   $hero = PageBlockResolver::forPage('home')->block('hero');
 *
 * Also supports array-access and iteration directly on the resolver — the
 * first such read materializes the underlying collection, so
 * `$blocks['hero']` or `@foreach ($blocks as $b)` just work after
 * `page_blocks($page)` without an explicit `->get()` call.
 *
 * Backwards compatible: falls back to the consumer's
 * `App\Models\PageBlock` if present, else uses the package model. Eager-
 * loads translations so inner `translate()` calls don't fan out to N+1.
 */
class PageBlockResolver implements ArrayAccess, IteratorAggregate
{
    protected string $page;
    protected string $locale;
    protected bool $includeDrafts = false;
    /** @var array<int, string> */
    protected array $onlyKeys = [];
    /** @var array<int, string> */
    protected array $onlyTypes = [];
    /** @var Collection<string, PresentedBlock>|null */
    protected ?Collection $resolved = null;

    private function __construct() {}

    public static function forPage(string $page): self
    {
        $r = new self;
        $r->page   = $page;
        $r->locale = app()->getLocale();
        return $r;
    }

    public function locale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function withDrafts(): self
    {
        $this->includeDrafts = true;
        return $this;
    }

    /** Filter the result set to a list of block_keys. */
    public function only(string|array $keys): self
    {
        $this->onlyKeys = is_array($keys) ? $keys : [$keys];
        return $this;
    }

    /** Filter the result set to a list of block_types. */
    public function types(string|array $types): self
    {
        $this->onlyTypes = is_array($types) ? $types : [$types];
        return $this;
    }

    /**
     * Fetch the augmented block collection, keyed by block_key.
     *
     * @return Collection<string, PresentedBlock>
     */
    public function get(): Collection
    {
        if ($this->resolved !== null && $this->onlyKeys === [] && $this->onlyTypes === []) {
            return $this->resolved;
        }

        $modelClass = $this->blockModel();
        $query = $modelClass::query()
            ->where('page_name', $this->page)
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('id');

        if (!$this->includeDrafts) $query->where('status', 'published');
        if ($this->onlyKeys)       $query->whereIn('block_key', $this->onlyKeys);
        if ($this->onlyTypes)      $query->whereIn('block_type', $this->onlyTypes);

        $collection = $query->get()
            ->map(fn ($b) => self::present($b, $this->locale))
            ->keyBy(fn (PresentedBlock $b) => $b->key);

        if ($this->onlyKeys === [] && $this->onlyTypes === []) {
            $this->resolved = $collection;
        }
        return $collection;
    }

    /** Shortcut: resolve a single block by key, or null if missing. */
    public function block(string $key): ?PresentedBlock
    {
        if ($this->resolved !== null) {
            return $this->resolved->get($key);
        }

        $clone = clone $this;
        $clone->onlyKeys = [$key];
        return $clone->get()->first();
    }

    /* --- ArrayAccess + IteratorAggregate: let templates treat the resolver
       itself as the materialized collection without calling ->get() first.
       Mutating operations are a no-op: blocks are read-only at render time. */

    public function offsetExists(mixed $offset): bool
    {
        return $this->get()->has($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get()->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void {}
    public function offsetUnset(mixed $offset): void {}

    public function getIterator(): Traversable
    {
        return $this->get()->getIterator();
    }

    /**
     * Consumer sites may have their own App\Models\PageBlock with extra
     * observers/scopes — prefer it over the package model. The two share
     * the same `page_blocks` table, so either works for reads.
     */
    protected function blockModel(): string
    {
        if (class_exists('App\\Models\\PageBlock')) return 'App\\Models\\PageBlock';
        return \Meta\AdminCore\Models\PageBlock::class;
    }

    /**
     * Instantiate the right PresentedBlock variant for the record's
     * block_type — typed subclass if registered, generic otherwise.
     */
    public static function present(object $block, string $locale): PresentedBlock
    {
        $class = BlockTypeRegistry::classFor((string) ($block->block_type ?? ''));
        return new $class($block, $locale);
    }
}
