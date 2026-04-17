# Routing

## URL shape

All admin URLs live under a configurable prefix (default `admin`):

```
/admin                              → Dashboard
/admin/{resource}                   → Index
/admin/{resource}/create            → Create form
/admin/{resource}/{id}/edit         → Edit form
/admin/{resource}/{id}              → update / destroy (via PUT/PATCH/DELETE)
/admin/{resource}/{id}/toggle-publish → Quick status toggle (PATCH)
```

`{resource}` is the `$name` passed to `AdminCore::resource()`. The
value is passed as a URL segment and looked up in the registry at
request time; if not registered, the request 404s.

`{id}` is resolved via the model's `getRouteKeyName()` (usually `id`,
sometimes `slug`). Override per-resource with `route_key`.

## Named routes

The package defines a handful of generic named routes:

| Name                             | Method | Path                                        |
|----------------------------------|--------|---------------------------------------------|
| `admin.spa.dashboard`            | GET    | `/admin`                                    |
| `admin.resource.index`           | GET    | `/admin/{resource}`                         |
| `admin.resource.create`          | GET    | `/admin/{resource}/create`                  |
| `admin.resource.store`           | POST   | `/admin/{resource}`                         |
| `admin.resource.edit`            | GET    | `/admin/{resource}/{id}/edit`               |
| `admin.resource.update`          | PUT    | `/admin/{resource}/{id}`                    |
| `admin.resource.destroy`         | DELETE | `/admin/{resource}/{id}`                    |
| `admin.resource.toggle-publish`  | PATCH  | `/admin/{resource}/{id}/toggle-publish`     |

All take the resource name as the first route parameter:

```php
route('admin.resource.index',   'articles');
route('admin.resource.edit',    ['articles', $article->id]);
route('admin.resource.destroy', ['articles', $article->id]);
```

## The `admin_core_route` helper

For convenience:

```php
admin_core_route('articles', 'index');
admin_core_route('articles', 'edit', $article->id);
admin_core_route('articles', 'destroy', $article->id);
```

Wraps `route("admin.resource.{$action}", array_merge([$resource], $params))`.

## Migration from per-resource named routes

If your code still references old-style named routes like
`route('admin.articles.index')`, they **no longer exist** after migrating
to the generic controller. Replace with:

- `route('admin.resource.index', 'articles')`, or
- `admin_core_route('articles', 'index')`, or
- the raw URL `/admin/articles`

For Blade sidebars and partials that loop resources:

```blade
{{-- Before --}}
<a href="{{ route('admin.' . $slug . '.index') }}">…</a>

{{-- After --}}
<a href="{{ route('admin.resource.index', $slug) }}">…</a>
{{-- or just: --}}
<a href="{{ url('/admin/' . $slug) }}">…</a>
```

## Middleware

Configured in `config/admin-core.php`:

```php
'middleware' => ['auth', 'verified'],
```

Applied to **all** admin routes (dashboard + resources). To protect a
specific resource further, either:

- Wrap the resource in additional middleware by registering it in your
  own route file using `AdminCore::getResource()` and a custom
  controller (rare), or
- Check authorisation inside model observers / policies.

## Prefix

Change the URL prefix in `config/admin-core.php`:

```php
'prefix' => 'dashboard',   // mounts at /dashboard instead of /admin
```

The change takes effect on route cache rebuild:

```bash
php artisan route:clear
php artisan route:cache   # optional in prod
```

Note that other parts of the code (Blade menu links like
`url('/admin/...')`, manual controller `redirect()` calls) are **not**
adjusted automatically. Grep and replace if you change the prefix.

## Custom routes for a resource

When a single resource needs extra actions beyond the CRUD + toggle,
register them **before** the package's catch-all loads. Add them to
your own routes file and give them specific paths (not matching
`{id}`):

```php
Route::middleware(['auth','verified'])->prefix('admin')->name('admin.')->group(function () {
    // extra actions for Articles
    Route::patch('/articles/{article}/feature', [ArticleController::class, 'feature'])
        ->name('articles.feature');
});
```

Route registration order:

1. Your custom routes file
2. Package's `routes/admin.php` (loaded from service provider)

Laravel matches the first, so your `/articles/{article}/feature` wins
over `/articles/{id}` (they don't even conflict — the path is different).

But if you re-register `/articles` as a specific route, it **shadows**
the generic handler for that resource — useful when a resource has
largely custom logic but you still want it in the sidebar.

## Route model binding

The package does **not** use Laravel's route-model binding. Instead it
manually resolves the model via `findModel()`, which uses
`getRouteKeyName()` (or your `route_key` override). This is because
route parameters are generic (`{resource}`, `{id}`) and Laravel can't
infer the model class.

If you need RMB-like behavior, override the controller action and
type-hint a specific model.

## Handling conflicts with other admin routes

If another package or your app registers `GET /admin/foo`, whichever
loads first wins. Check with:

```bash
php artisan route:list --path=admin/foo
```

The package-provided `admin.*` routes always come from
`Meta\AdminCore\Http\Controllers\ResourceController`.

## Public site routes

The package only touches `/admin/*` (or whatever prefix you set). Your
public site's `web.php` routes are untouched. Running a headless admin
over an otherwise-Blade site works fine — see the Migration guide.
