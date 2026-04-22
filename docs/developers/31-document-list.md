# `document-list` — canonical document/link block

One block type to replace the drift of `links`, `downloadable-docs`,
`admission-documents`, `accreditation-documents`, `grid-documents`,
`download`, `grid-links` that evolved across consumer sites. Same data
shape, one admin schema, one render component, multiple layouts.

## Why this exists

The same underlying need — "render a list of attached documents, each
with an icon, title, optional description and a URL or uploaded file"
— was reinvented five or six times in prod templates before we
consolidated. Every reinvention meant re-writing:

* a JSON schema in `DefaultBlockCatalog::SCHEMAS`
* a Blade partial with the right grid / list layout and icon palette
* the translatable-field plumbing (title/description/url)

From **v0.51.0** onward, new templates use `document-list` and the
package handles all three at once.

## Admin side

Block key: **`document-list`** (listed under *CTA / Ссылки* in the
block-type picker).

Schema fields (all round-tripped by `BlockDataEditor.vue` with real UI,
no raw JSON):

| Field         | Type                    | Notes                                            |
|---------------|-------------------------|--------------------------------------------------|
| `layout`      | `select`                | `grid-2` / `grid-3` / `grid-4` / `list` / `cards` |
| `description` | `translatable_textarea` | Optional lead-in under the block heading         |
| `items[]`     | `array`                 | One row per document                             |

Each item:

| Sub-field     | Type                   | Notes                                            |
|---------------|------------------------|--------------------------------------------------|
| `icon`        | `text`                 | Font Awesome class, e.g. `fas fa-file-pdf`       |
| `color`       | `select`               | red / blue / green / gold / purple / gray / `''` |
| `title`       | `translatable`         | Display title (per-locale)                       |
| `description` | `translatable_textarea`| Optional subtitle                                |
| `url`         | `translatable_file`    | Drop a file or paste a URL — preview chip shows up automatically |

`translatable_file` gives editors both modes: paste a Google Drive URL
*or* hit the upload button for a local PDF / DOC / XLSX / ZIP. Once set,
the editor shows a preview chip with the filename, extension, size and
an *"external"* badge for remote URLs.

## Render side

```blade
@php $docs = page_block('library', 'library-rules'); @endphp

<x-admin-core::documents
    :title="$docs->title ?? null"
    :description="$docs->description ?? null"
    :items="$docs->items ?? []"
    :layout="$docs->layout ?? 'grid-3'" />
```

The component:

* accepts translatable values as either pre-resolved strings or raw
  `{ru: …, kk: …, en: …}` maps, picks the current locale with a
  graceful fallback;
* normalizes each item (default icon, target `_blank` with
  `rel="noopener noreferrer"`, skips empty rows);
* renders the right grid/flex wrapper for the requested `layout`;
* paints the icon pill in the item's color, with site-wide defaults
  pulled from the brand palette.

### Available layouts

| `layout`  | Behaviour                                            |
|-----------|------------------------------------------------------|
| `grid-2`  | 2 cols on md+                                        |
| `grid-3`  | 3 cols on lg+, 2 on md (default)                     |
| `grid-4`  | 4 cols on lg+, 2 on md                               |
| `list`    | Single column, compact rows                          |
| `cards`   | 3 cols, padded "cards" style with extra shadow       |

## Migrating from the old types

1. Leave existing `links` / `downloadable-docs` / `grid-documents`
   blocks alone — they still render. The old block types are not going
   away.
2. For new pages, pick `document-list`.
3. To migrate a legacy block, change `block_type` to `document-list`
   and adjust the top-level key (`links[]` → `items[]`, `documents[]`
   → `items[]`). Editor will pick up the right fields on next load.
4. Delete the consumer-side Blade partial and replace with
   `<x-admin-core::documents …>`.

## Known limitation

`document-list` is a **flat** list. For the "cards with their own
nested PDF sub-list" pattern (library `about` grid on meta.edu.kz),
split into one `document-list` block per card. Nested arrays inside
`item_fields` are planned for v0.52 — when that lands, a single
compound block will cover the nested case too.
