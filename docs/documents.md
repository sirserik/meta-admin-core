# Document attachments (v1.9)

Attach downloadable files to **any** model via one polymorphic `documents`
table — replacing per-parent copies (ArticleDocument / NewsDocument …).

## Setup

```bash
php artisan migrate   # creates the documents table
```

```php
// the model that owns files
use Meta\AdminCore\Concerns\HasDocuments;

class Article extends Model {
    use HasDocuments;   // → $article->documents (ordered)
}
```

Allow it to receive uploads:

```php
// config/admin-core.php
'documents' => [
    'dir'        => 'documents',
    'max_kb'     => 51200,
    'attachable' => [App\Models\Article::class, App\Models\News::class],
],
```

## Endpoints

Admin (behind admin auth):

```
POST   {prefix}/documents              store (documentable_type/id + file + title)
POST   {prefix}/documents/reorder      reorder ({order: [id,…]})
PUT    {prefix}/documents/{document}   update (title/description/locale/sort_order)
DELETE {prefix}/documents/{document}   delete (also removes the file)
```

Public:

```
GET /documents/{document}/download     → attachment download
GET /documents/{document}/view         → forced attachment (never inline)
```

## Security

- Files are **always served as attachments** with `nosniff` and a
  `default-src 'none'` CSP — a payload smuggled into a tolerated mime (HTML,
  SVG, XML…) can't execute under the app origin/session.
- Uploads are restricted to `Document::getSupportedFileTypes()` and the
  `attachable` allowlist.
- **Anonymous access gate:** admins (per `admin-core.admin_roles`) always
  read. For everyone else, if the parent implements
  `Meta\AdminCore\Contracts\PubliclyVisible`, its files are served only while
  `isPubliclyVisible()` is true — so files on a draft/unpublished parent
  aren't exposed by id enumeration. Parents that don't implement it are
  treated as public.

```php
class Article extends Model implements \Meta\AdminCore\Contracts\PubliclyVisible {
    use \Meta\AdminCore\Concerns\HasDocuments;
    public function isPubliclyVisible(): bool {
        return $this->is_published && (! $this->published_at || $this->published_at->lte(now()));
    }
}
```
