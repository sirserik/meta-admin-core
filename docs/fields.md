# Translatable fields

Fields listed in `translatable` are stored in a separate `translations`
table so each field has a value per locale (`ru`, `kk`, `en`). In the
form they get rendered in the **main area** with a locale-tabs strip
above, switching the visible input instance.

## Configuration

```php
AdminCore::resource('articles', [
    // ...
    'translatable' => ['title', 'excerpt', 'content'],
    'fields' => [
        ['name' => 'title',   'type' => 'text',     'label' => 'Заголовок', 'required' => true],
        ['name' => 'excerpt', 'type' => 'textarea', 'label' => 'Краткое описание'],
        ['name' => 'content', 'type' => 'editor',   'label' => 'Содержимое'],
    ],
]);
```

Each `fields[]` entry must reference a name that appears in
`translatable`. Names not in `translatable` are silently ignored in the
main area — they should go under `attributes` instead.

## Field entry keys

| Key        | Type    | Purpose                                               |
|------------|---------|-------------------------------------------------------|
| `name`     | string  | Column/translation key — required.                    |
| `type`     | string  | `text` \| `textarea` \| `editor`. Default: `text`.    |
| `label`    | string  | Input label shown above the field.                    |
| `required` | bool    | Adds the red asterisk + server-side rule on `ru` locale. |

## Types

### `text`

Single-line `<input type="text">`.

### `textarea`

Multi-line `<textarea>`, 4 rows.

### `editor`

Rich-text editor — Tiptap/ProseMirror. Toolbar: bold, italic,
underline, H1-H3, bullet/ordered lists, link, image, undo/redo.
Produces safe HTML.

Image uploads inside the editor POST multipart/form-data to
`config('admin-core.upload_url')` (defaults to `/admin/upload/image`).
The consumer app must implement this route — see [images](images.md).

## Model requirements

The model needs two methods: `translate($field, $locale)` and
`saveTranslations($locale, $payload)`.

### Option A: Use Spatie's package

```bash
composer require spatie/laravel-translatable
```

```php
use Spatie\Translatable\HasTranslations;

class Article extends Model
{
    use HasTranslations;
    protected array $translatable = ['title', 'excerpt', 'content'];
}
```

Spatie stores translations as a JSON column on the row itself. The
package's `presentForm` / `saveTranslations` logic works against any
model with the two methods.

### Option B: Your own `translations` table

A separate table keyed by `translatable_type`, `translatable_id`, `locale`,
`field`, `value`.

Skeleton trait:

```php
trait Translatable
{
    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function translate(string $field, ?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        return $this->translations
            ->where('field', $field)
            ->where('locale', $locale)
            ->first()
            ?->value;
    }

    public function saveTranslations(string $locale, array $payload): void
    {
        foreach ($payload as $field => $value) {
            $this->translations()->updateOrCreate(
                ['field' => $field, 'locale' => $locale],
                ['value' => $value]
            );
        }
    }
}
```

Both projects in this repo (ETU + etec) use this pattern.

## Physical-column mirroring

If the model's own table **also has** a column named the same as a
translatable field, the package writes the primary-locale (`ru`) value
to it after saving translations. This exists to make public-site SQL
simpler:

```php
// Model has both a translations row AND a `title` column on articles table
$article->title;                                // 'Заголовок'  (physical column, ru)
$article->translate('title', 'kk');             // 'Тақырып'   (translations table)

// Query by physical column still works without a JOIN
Article::where('title', 'like', 'Нов%')->get();
```

If the column doesn't exist, mirroring is silently skipped — `Schema::hasColumn()`
guards the write.

## Locale list

Defined in `config/admin-core.php`:

```php
'locales' => ['ru', 'kk', 'en'],
```

The first locale is treated as **primary**:

- `required` fields validate primary-locale input only
- Mirrored physical columns use the primary locale
- Search matches against primary locale
- List view title column uses primary locale

Other locales are always nullable.

## Validation

For each translatable field × locale the controller adds:

```
{field}.{ru} → required|string  (if field is required)
{field}.{kk} → nullable|string
{field}.{en} → nullable|string
```

Validation errors surface in the Vue form under the field label per
locale tab.

## Rendering in the list

The default `Resource/Index.vue` shows the title by reading either the
translated `title` or translated `name` (whichever is in `translatable`),
falling back to the plain column. See `ResourceController::presentRow()`.
