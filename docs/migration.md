# Migrating from legacy Spa controllers

If you already have a Laravel admin built with per-resource Inertia
controllers — e.g. `App\Http\Controllers\Admin\Spa\ArticleController extends
BaseCrudController` — migrating to declarative resources is mostly
mechanical and fully incremental.

The two real-world migrations in this org (ETU + etec) each dropped
~6000 LOC by switching. This is a reproducible recipe.

## Mental model

**Before:**
```
Controller class (~100 lines)       +  Routes (7 lines)  +  Vue pages (Index.vue + Form.vue)
   per resource                        per resource          per resource
```

**After:**
```
AdminCore::resource('name', [...config])   +   generic routes   +   generic pages
```

Specialised controllers (workflow, hierarchical trees, tabbed settings)
**stay as they are** — you migrate only the resources that are
plain-vanilla CRUD.

## Checklist per resource

1. Write an `AdminCore::resource('name', [...])` entry in
   `AppServiceProvider` with the resource's translatable + attribute config.
2. Remove the resource's explicit routes from `routes/web.php` (or
   `admin-spa.php`, etc).
3. Delete the `App\Http\Controllers\Admin\Spa\{Name}Controller.php` file.
4. Delete the `resources/js/admin-spa/pages/{Name}/{Index,Form}.vue`
   files.
5. If anything references the old named routes
   (`route('admin.{name}.index')`), replace with
   `route('admin.resource.index', '{name}')`, `admin_core_route()`, or
   plain URLs.
6. Remove the duplicate `AdminCore::menuItem()` entry if you were
   listing the resource in the sidebar manually (the `resource()` call
   auto-populates).

Commit, move to the next resource.

## Detailed example — migrating `Articles`

### Before: legacy controller

```php
// app/Http/Controllers/Admin/Spa/ArticleController.php (100+ lines)
class ArticleController extends BaseCrudController
{
    protected function modelClass(): string   { return Article::class; }
    protected function resourceName(): string { return 'article'; }
    protected function resourcePlural(): string { return 'articles'; }
    protected function pageComponent(): string { return 'Articles'; }
    protected function imageField(): ?string  { return 'featured_image'; }
    // ...
    protected function translatableFields(): array
    {
        return ['title', 'excerpt', 'content'];
    }
    protected function plainFields(): array
    {
        return ['slug', 'is_published', 'is_featured', 'published_at'];
    }
    protected function fieldDefinitions(): array
    {
        return [
            ['name' => 'title',   'type' => 'text', 'label' => 'Заголовок', 'required' => true],
            // ...
        ];
    }
}
```

```php
// routes/web.php
Route::get(   '/admin/articles',                [ArticleController::class, 'index'])->name('admin.articles.index');
Route::get(   '/admin/articles/create',         [ArticleController::class, 'create'])->name('admin.articles.create');
Route::post(  '/admin/articles',                [ArticleController::class, 'store'])->name('admin.articles.store');
Route::get(   '/admin/articles/{article}/edit', [ArticleController::class, 'edit'])->name('admin.articles.edit');
Route::put(   '/admin/articles/{article}',      [ArticleController::class, 'update'])->name('admin.articles.update');
Route::delete('/admin/articles/{article}',      [ArticleController::class, 'destroy'])->name('admin.articles.destroy');
Route::patch( '/admin/articles/{article}/toggle-publish', [ArticleController::class, 'togglePublish'])->name('admin.articles.toggle-publish');
```

```
resources/js/admin-spa/pages/Articles/
├── Index.vue   (~100 lines)
└── Form.vue    (~200 lines)
```

### After: declarative

```php
// app/Providers/AppServiceProvider.php
AdminCore::resource('articles', [
    'model'        => Article::class,
    'label'        => 'Статьи',
    'menu'         => 'Контент',
    'icon'         => 'fa-newspaper',
    'image_field'  => 'featured_image',
    'translatable' => ['title', 'excerpt', 'content'],
    'fields' => [
        ['name' => 'title',   'type' => 'text',     'label' => 'Заголовок', 'required' => true],
        ['name' => 'excerpt', 'type' => 'textarea', 'label' => 'Краткое описание'],
        ['name' => 'content', 'type' => 'editor',   'label' => 'Содержимое'],
    ],
    'attributes' => [
        ['name' => 'slug',         'type' => 'text',           'label' => 'Slug'],
        ['name' => 'is_published', 'type' => 'boolean',        'label' => 'Опубликована'],
        ['name' => 'is_featured',  'type' => 'boolean',        'label' => 'Рекомендуемая'],
        ['name' => 'published_at', 'type' => 'datetime-local', 'label' => 'Дата публикации'],
    ],
]);
```

**Deleted:** controller (100 lines), 7 routes, Index.vue (100), Form.vue
(200) = ~400 lines gone. One resource.

## Translating the config

Legacy controller method → new config key:

| Legacy method           | Config key               |
|-------------------------|--------------------------|
| `modelClass()`          | `model`                  |
| `resourcePlural()`      | the `$name` arg          |
| `pageComponent()`       | `page`                   |
| `imageField()`          | `image_field`            |
| `translatableFields()`  | `translatable`           |
| `plainFields()`         | `attributes[*].name` (typed) |
| `fieldDefinitions()`    | `fields`                 |
| `orderBy()`             | `order_by`               |
| `titleForIndex()`       | `label`                  |
| `perPage()`             | `per_page`               |

## Typing up the `plain` array

The legacy `plainFields()` returned a flat list of column names; the
package expects **typed attributes**. Start with `type: text` and refine:

```php
// Legacy
protected function plainFields(): array
{
    return ['slug', 'is_published', 'published_at', 'category'];
}

// New
'attributes' => [
    ['name' => 'slug',         'type' => 'text'],
    ['name' => 'is_published', 'type' => 'boolean'],
    ['name' => 'published_at', 'type' => 'datetime-local'],
    ['name' => 'category',     'type' => 'text'],
],
```

Infer types from the column definitions (booleans, timestamps, FKs, etc.).

## Named-route references

If your legacy code calls `route('admin.articles.index')`, these names
will disappear. Replace in order of frequency:

### In controllers / service classes

```php
// Before
return redirect()->route('admin.articles.index');

// After — option 1: helper
return redirect(admin_core_route('articles', 'index'));

// After — option 2: generic name
return redirect()->route('admin.resource.index', 'articles');

// After — option 3: URL
return redirect('/admin/articles');
```

### In Blade views

```blade
{{-- Before --}}
<a href="{{ route('admin.articles.index') }}">…</a>

{{-- After --}}
<a href="{{ url('/admin/articles') }}">…</a>
```

Grep everywhere for `admin\.(articles|programs|schools|...)\.(index|create|edit|store|update|destroy)` and replace. Some repos carry these in
~20 files; ~10 minutes of find/replace.

## Dead legacy Blade views

If you had legacy Blade admin pages that used these named routes but are
no longer rendered (replaced by the Inertia SPA), leave them as-is only
if they're truly unreachable. If they're reachable via a menu link or
controller, replace the `route()` calls with `url()` or delete the views
entirely.

## `AdminCore::menuItem` duplicates

If your legacy sidebar config was in an `AdminCore::menuItem` call,
remove it — `AdminCore::resource()` auto-adds a sidebar entry. Leaving
the old `menuItem` call behind produces two identical links.

## Validate the route list

After removing explicit routes, run:

```bash
php artisan route:clear
php artisan route:list --path=admin | grep {resource}
```

You should only see the generic `admin.resource.*` entries plus any
extra actions that genuinely need custom handling.

## Keep specialised controllers

Not every admin screen should go through the generic API. In both ETU
and etec the following stayed as custom controllers:

| Resource            | Why it stays specialised                          |
|---------------------|---------------------------------------------------|
| `Leads`             | Read-only + status workflow + filters             |
| `RectorQuestions`   | Q&A reply flow                                    |
| `Menu`              | Hierarchical tree editor                          |
| `PageBlocks`        | Visual page builder with 60+ block types          |
| `Settings`          | Tabbed key/value list                             |
| `SiteSettings`      | Multiple sub-forms on one page                    |
| `Activity`          | Read-only log viewer                              |
| `Cache`, `Backup`, `Theme` | Imperative actions, not DB CRUD          |
| `Media`             | File/folder UI                                     |
| `Users`             | Role assignment                                    |

Rule of thumb: if >20% of the controller's code isn't plain CRUD, keep
it specialised.

## Data migration

**No data changes are required.** The package reads and writes the same
tables and the same translations table as the legacy controllers —
the only change is where the request dispatch happens.

Double-check by creating/updating a row through the new admin and
inspecting the DB: values land in the same columns and translation rows
as before.

## Rollback

If something breaks mid-migration:

```bash
git revert HEAD
```

The package is purely additive — removing the resource registration
gives you back the legacy state, provided you haven't deleted the old
controller + routes + Vue pages yet. Commit incrementally per resource
to make this easy.

## Case studies

See the git history of the ETU (`sirserik/ETU`, branch
`laravel-integration`) and etec (`sirserik/etc-cms`, branch
`feature/sync-from-meta`) repos. Each resource migration is a single
commit.
