# Polymorphic page_blocks

Since v0.53.0 a `PageBlock` row can be attached to any Eloquent model
through `blockable_type/blockable_id`, in addition to the legacy
`page_name`-keyed binding which stays as the canonical anchor for
static pages (about, contact, etc.).

## Why

Page blocks were built for "named" pages (`home`, `about`, `library`).
But ETU needed flexible per-row content for procurements (each
procurement has its own page with whatever blocks fit), and the same
shape recurs for programs, news, school pages — any sub-entity that
deserves a block stack of its own.

## How

### 1. Owner model

Add the trait to whichever Eloquent model owns blocks:

```php
use Meta\AdminCore\Concerns\HasContentBlocks;

class Procurement extends Model
{
    use HasContentBlocks;
}
```

That gives:

- `$model->contentBlocks` — MorphMany relation, ordered by `sort_order`.
- `$model->loadContentBlocks($publishedOnly = true)` — cached collection
  keyed by `block_key`, ready for `<x-page-blocks :blocks="…">`.
- `$model->contentBlocksPageName()` — synthetic page_name like
  `procurement-{id}` for legacy page_name-based tooling.

### 2. Block creation / linking

Two equivalent paths:

- **Through the relation** — `$procurement->contentBlocks()->create([...])`
  — `blockable_type/blockable_id` get filled automatically; you may
  also set `page_name` to the synthetic value if the existing block
  editor URL (`/admin/blocks?page=…`) should match.
- **Through the existing block editor** — operators just open
  `/admin/blocks?page=procurement-{id}` and add blocks the usual way.
  The consumer site should observe `PageBlock::saving` and back-fill
  `blockable_type/blockable_id` from the synthetic page_name (one-line
  observer).

### 3. Live preview

Register a preview-URL resolver so the admin block-editor's iframe
loads the real public page instead of the synthetic key:

```php
use Meta\AdminCore\Facades\AdminCore;

AdminCore::previewResolver('/^procurement-(\d+)$/', function ($matches) {
    $p = \App\Models\Procurement::find((int) $matches[1]);
    return $p ? '/procurements/' . $p->slug : null;
});
```

(See `AdminCore::previewResolver()`, shipped in v0.52.0.)

### 4. Rendering on the public page

The consumer Blade view loads blocks the usual way:

```blade
<x-page-blocks :blocks="$procurement->loadContentBlocks()" :page="$procurement->contentBlocksPageName()" />
```

`<x-page-blocks>` doesn't care whether the rows came from a `page_name`
query or a polymorphic owner — it just receives a keyed collection of
`PageBlock` models.

## Migration

`2026_04_28_000001_add_blockable_to_page_blocks` adds the two columns
+ a composite index `(blockable_type, blockable_id, is_active, sort_order)`.
It's idempotent (`Schema::hasColumn` guards) so consumers that already
landed these columns from a local migration won't conflict.

## Backwards compatibility

The legacy `page_name`-only binding is unchanged:
- `PageBlock::forPage('about')` and `PageBlock::getPageBlocks('about')`
  still work the same way.
- Existing rows have `blockable_type/_id = NULL`.
- New polymorphic rows can also keep a `page_name` (the synthetic
  `procurement-{id}` key) — both bindings coexist on the same row.
