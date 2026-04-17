# Resource API reference

`AdminCore::resource($name, $config)` registers a CRUD admin module
entirely via configuration. This page is the full reference for the
config array.

## Signature

```php
AdminCore::resource(string $name, array $config): self
```

- `$name` — URL-safe kebab-case slug; becomes `/admin/{name}` and the
  `route` field.
- `$config` — see keys below.

Can be chained: `AdminCore::resource(...)->resource(...)->menuItem(...)`.

## Config keys

| Key            | Type             | Default                     | Required? |
|----------------|------------------|-----------------------------|-----------|
| `model`        | FQCN             | —                           | **yes**   |
| `label`        | string           | `ucfirst($name)`            | no        |
| `menu`         | string           | `'Контент'`                 | no        |
| `icon`         | string           | `'fa-file'`                 | no        |
| `route`        | string           | same as `$name`             | no        |
| `page`         | string           | `'Resource'`                | no        |
| `order_by`     | array            | `['created_at' => 'desc']`  | no        |
| `per_page`     | int              | `15`                        | no        |
| `route_key`    | string \| `null` | model's `getRouteKeyName()` | no        |
| `image_field`  | string \| `null` | `null`                      | no        |
| `translatable` | string[]         | `[]`                        | no        |
| `fields`       | array of dicts   | `[]`                        | no        |
| `attributes`   | array of dicts   | `[]`                        | no        |

### `model` (FQCN, required)

Fully qualified Eloquent model class. `ResourceController` does:

```php
$model::query()->paginate(...)
$model::make()->getRouteKeyName()
(new $model)->getTable()
```

The model must be instantiable without args and have a DB table.

### `label` (string)

Text shown:

- As sidebar link
- As browser tab title and page header
- In success flash messages (`"{label} создан"`, etc.)

### `menu` (string)

Sidebar section grouping. Resources and `menuItem()`s with the same
`menu` value are grouped under a single header. Order within a section
is determined by `order` on `menuItem()` and defaults to `50` for
resources — see [navigation](navigation.md).

### `icon` (string)

FontAwesome 6 class name. The `fas ` prefix is prepended if missing.
So all three of these work:

```php
'icon' => 'fa-newspaper'
'icon' => 'fas fa-newspaper'
'icon' => 'fa-regular fa-newspaper'  // use regular variant
```

### `route` (string)

URL segment. Defaults to `$name`. Only set this if you want a slug that
differs from the registered name, e.g. `resource('sdg-news', ['route' => 'sdg/news'])` would mount at `/admin/sdg/news`.

### `page` (string)

Vue page folder name. With default `'Resource'`, the package renders
`Resource/Index.vue` and `Resource/Form.vue`. Set `'page' => 'Articles'`
to use `Articles/Index.vue` and `Articles/Form.vue` instead — put them
in `resources/js/admin-spa/pages/Articles/`. See
[custom Vue pages](custom-pages.md).

### `order_by` (array)

Multi-column ordering for the list view.

```php
'order_by' => ['sort_order' => 'asc', 'created_at' => 'desc']
```

Each column is added with `->orderBy(col, dir)` in the order declared.

### `per_page` (int)

Pagination page size. Default `15`. The `LengthAwarePaginator` is passed
to the Vue page with query-string preservation.

### `route_key` (string | null)

Model column used in URLs. Defaults to whatever `getRouteKeyName()`
returns on the model (usually `id`; some models override to `slug`).

```php
// News uses slug in URLs
'route_key' => 'slug'
```

Then `/admin/news/2026-summer-intake/edit` works.

### `image_field` (string | null)

Column name on the model that holds the uploaded image path (relative to
`storage/app/public`). Enables:

- **Upload input** in the form sidebar
- **Automatic storage** under `storage/app/public/{$name}/...`
- **Preview + remove** controls
- **`{field}_url`** computed and passed to the Vue page via
  `presentRow()` and `presentForm()`
- **Delete on destroy** — the file is unlinked from storage when the
  row is destroyed

If your model stores multiple images (gallery), treat that as a custom
relation and override the Vue page.

### `translatable` (string[])

List of field names whose values live in the `translations` table (one
row per model × locale × field). The model must expose a `translate()`
method and a `saveTranslations()` method — see
[translatable fields](fields.md).

### `fields` (array of dicts)

Translatable form fields rendered in the **main area** of the form,
with a locale tabs strip above them. Each entry:

```php
[
    'name'     => 'title',        // required — matches $translatable
    'type'     => 'text',         // text | textarea | editor
    'label'    => 'Заголовок',    // shown above the input
    'required' => true,           // only `ru` locale is required; other locales nullable
]
```

Full reference: [translatable fields](fields.md).

### `attributes` (array of dicts)

Plain (non-translatable) scalar fields rendered in the **sidebar**.
See [attribute types](attributes.md) for every field type.

```php
[
    'name'        => 'slug',
    'type'        => 'text',            // text|url|email|number|date|datetime-local|select|boolean|color|textarea
    'label'       => 'Slug',
    'required'    => true,
    'unique'      => true,              // adds unique:{table},{column},{id}
    'placeholder' => 'автогенерация',
    'help'        => 'Оставь пустым для автогенерации',
    'max'         => 255,               // string max length
    'options'     => [...],             // for `select`
]
```

## Dispatch

Every request to `/admin/{resource}[/{id}][/{action}]` is handled by
`Meta\AdminCore\Http\Controllers\ResourceController`. It:

1. Reads `{resource}` from the URL
2. Calls `AdminCore::getResource($resource)`
3. Aborts `404` if the resource isn't registered
4. Delegates to `index`, `create`, `edit`, `store`, `update`, `destroy`
   or `togglePublish`

Named routes live under the `admin.resource.*` prefix:

| Action         | Route name                 | Method | Path                                 |
|----------------|----------------------------|--------|--------------------------------------|
| index          | `admin.resource.index`     | GET    | `/admin/{resource}`                  |
| create         | `admin.resource.create`    | GET    | `/admin/{resource}/create`           |
| store          | `admin.resource.store`     | POST   | `/admin/{resource}`                  |
| edit           | `admin.resource.edit`      | GET    | `/admin/{resource}/{id}/edit`        |
| update         | `admin.resource.update`    | PUT    | `/admin/{resource}/{id}`             |
| destroy        | `admin.resource.destroy`   | DELETE | `/admin/{resource}/{id}`             |
| togglePublish  | `admin.resource.toggle-publish` | PATCH | `/admin/{resource}/{id}/toggle-publish` |

Use the helper to build URLs:

```php
admin_core_route('articles', 'edit', $id)  // /admin/articles/42/edit
```

## Search

The list view has a single text search box that targets:

1. **Translated `title`**, if `title` is in `translatable`
2. **Translated `name`**, if `name` is in `translatable`
3. Otherwise the plain column `name` or `title` (whichever exists)

Override `applySearch()` or provide a custom `Index.vue` if you need
multi-field or faceted search.

## Toggle publish

`PATCH /admin/{resource}/{id}/toggle-publish` flips one of:

1. `status` — cycles `draft ↔ published`
2. `is_published` — boolean flip
3. `is_active` — boolean flip

…in that priority order, based on which column exists on the table.

Exposed in the default `Resource/Index.vue` as an eye icon button.

## Minimal example

```php
AdminCore::resource('contacts', [
    'model' => \App\Models\Contact::class,
    'label' => 'Контакты',
    'menu'  => 'Система',
    'icon'  => 'fa-address-book',
    'attributes' => [
        ['name' => 'key',   'type' => 'text', 'label' => 'Ключ', 'required' => true, 'unique' => true],
        ['name' => 'value', 'type' => 'text', 'label' => 'Значение', 'required' => true],
    ],
]);
```

No translatable fields, no image, no `fields` — just a key/value table.
Works as-is.

## Full example

```php
AdminCore::resource('teachers', [
    'model'        => \App\Models\Teacher::class,
    'label'        => 'Преподаватели',
    'menu'         => 'Образование',
    'icon'         => 'fa-chalkboard-user',
    'image_field'  => 'photo',
    'order_by'     => ['order' => 'asc', 'id' => 'asc'],
    'per_page'     => 30,
    'translatable' => ['name', 'position', 'bio', 'education'],
    'fields' => [
        ['name' => 'name',      'type' => 'text',   'label' => 'ФИО', 'required' => true],
        ['name' => 'position',  'type' => 'text',   'label' => 'Должность'],
        ['name' => 'bio',       'type' => 'editor', 'label' => 'Биография'],
        ['name' => 'education', 'type' => 'editor', 'label' => 'Образование'],
    ],
    'attributes' => [
        ['name' => 'school_id', 'type' => 'select', 'label' => 'Школа', 'required' => true,
            'options' => fn () => \App\Models\School::orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])
                ->all()],
        ['name' => 'email', 'type' => 'email',  'label' => 'Email'],
        ['name' => 'phone', 'type' => 'text',   'label' => 'Телефон'],
        ['name' => 'order', 'type' => 'number', 'label' => 'Порядок'],
        ['name' => 'is_active', 'type' => 'boolean', 'label' => 'Активен'],
    ],
]);
```
