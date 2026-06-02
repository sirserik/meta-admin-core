# Editor hygiene (v1.8)

Clean the mess rich-editor fields accumulate: paste-from-Word/Google-Docs
HTML cruft, semicolon "paragraph lists", and base64-inlined images bloating
the DB. Pure transforms live in `Meta\AdminCore\Services\EditorHygiene`
(reusable on save); three commands sweep existing content.

## Commands

```bash
php artisan admin-core:content-cleanup-gdocs        [--dry-run] [--target=all|<table>]
php artisan admin-core:content-paragraphs-to-lists  [--dry-run] [--min=2] [--target=…]
php artisan admin-core:content-extract-base64       [--dry-run] [--target=…]
```

Always start with `--dry-run` — it reports rows/bytes without writing.

## What they sweep

The table → columns map is `admin-core.editor_hygiene.targets`. Defaults to
admin-core tables; add your domain content per site:

```php
// config/admin-core.php
'editor_hygiene' => [
    'targets' => [
        'translations' => ['value'],
        'page_blocks'  => ['content', 'subtitle', 'data'],
        'revisions'    => ['data'],
        'articles'     => ['content', 'excerpt'],   // consumer adds
        'news'         => ['content', 'excerpt'],
    ],
    'extract_dir' => 'uploads/extracted',
],
```

Tables/columns that don't exist are skipped automatically.

## On-save use

```php
use Meta\AdminCore\Services\EditorHygiene;

$model->content = EditorHygiene::cleanGoogleDocs($request->input('content'));
```

- `cleanGoogleDocs(string): string` — strip guid spans, junk inline styles
  (keeps color/bg/align/weight/style/decoration that carry meaning), dir/lang/
  aria attrs, nested empty spans, empty `<p>`/`&nbsp;`.
- `paragraphsToLists(string, int $min = 2): string` — runs of `<p>…;</p>` → `<ul>`.
- `extractBase64(string $text, callable $persist): string` — `$persist($filename,$bytes)`
  returns the URL to substitute (caller owns storage), so it's storage-agnostic.

`extract-base64` writes files to the `public` disk under `extract_dir` and
substitutes `/storage/<dir>/<sha1>.<ext>` (deduped by content hash).
