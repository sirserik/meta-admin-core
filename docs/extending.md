# Extending the core

Hooks for when you need to go beyond config-driven resources.

## Reading the registry

`AdminCore::getResources()`, `getResource()`, `hasResource()` let your
own code introspect what's registered.

```php
use Meta\AdminCore\Facades\AdminCore;

AdminCore::getResources();                      // Collection of all resources
AdminCore::getResource('articles');             // array|null
AdminCore::hasResource('articles');             // bool
```

Example uses:

```php
// In a route-list artisan command:
foreach (AdminCore::getResources() as $name => $r) {
    $this->line("/admin/{$name} — {$r['label']}");
}

// In a policy:
public function update(User $u, Model $m): bool {
    $resource = collect(AdminCore::getResources())
        ->first(fn ($r) => $r['model'] === $m::class);
    return $resource && $u->can('edit '.$resource['name']);
}
```

## Subclassing the controller

`ResourceController` is plain — every action and helper is `protected`
or `public`, so you can override anything. Register routes manually:

```php
// routes/admin.php (your own file, loaded before the package)
use App\Http\Controllers\Admin\ArticleResourceController;

Route::middleware(['auth','verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get(   '/articles',                 [ArticleResourceController::class, 'index'])->name('articles.index');
    Route::post(  '/articles',                 [ArticleResourceController::class, 'store'])->name('articles.store');
    Route::get(   '/articles/{id}/edit',       [ArticleResourceController::class, 'edit'])->name('articles.edit');
    Route::put(   '/articles/{id}',            [ArticleResourceController::class, 'update'])->name('articles.update');
    Route::delete('/articles/{id}',            [ArticleResourceController::class, 'destroy'])->name('articles.destroy');
});
```

Your controller:

```php
use Meta\AdminCore\Http\Controllers\ResourceController;

class ArticleResourceController extends ResourceController
{
    public function index(Request $request): Response
    {
        return parent::index($request, 'articles');
        // …or customise before/after the parent call
    }

    protected function validated(Request $request, array $config, ?Model $existing = null): array
    {
        $data = parent::validated($request, $config, $existing);
        // extra rule: unique slug per locale
        return $data;
    }
}
```

This approach keeps the generic plumbing and just tweaks specific hooks.

## Hook points

Worthwhile methods to override (all `protected` on `ResourceController`):

| Method                                     | Purpose                                         |
|--------------------------------------------|-------------------------------------------------|
| `applySearch($query, $term, $config)`      | Change the search query (multi-column, facets…) |
| `validated($request, $config, $existing)`  | Add/remove validation rules                     |
| `ruleForAttribute($attribute)`             | Change per-attribute rule generation            |
| `fill($m, $data, $request, $config, $existing)` | Change how values are written to the model |
| `saveTranslations($m, $data, $config)`     | Change how translations are persisted           |
| `presentRow($m, $config)`                  | Change fields sent to the list view            |
| `presentForm($m, $config)`                 | Change fields sent to the edit form             |
| `mediaUrl($path)`                          | CDN URLs, signed URLs, etc.                     |
| `findModel($config, $id)`                  | Custom scope (e.g. restrict to user's tenant)   |

## Model observers (the recommended way for side-effects)

If a generic CRUD action should trigger a side-effect, prefer Eloquent
observers over overriding the controller:

```php
// app/Observers/ArticleObserver.php
class ArticleObserver
{
    public function saved(Article $a): void
    {
        cache()->forget('articles:featured');
        SearchIndex::sync($a);
    }
}

// app/Providers/AppServiceProvider.php
Article::observe(ArticleObserver::class);
```

The generic controller fires the normal model events, so observers run.

## Adding a new attribute type

Attribute types are dispatched in two places:

1. **Frontend** — `SimpleField.vue` switches on `type` to pick the
   HTML input.
2. **Backend** — `ResourceController::ruleForAttribute()` picks the
   validation rule.

Adding a new type means:

1. Add a branch in `SimpleField.vue`'s template.
2. Add a branch in the `match` inside `ruleForAttribute()`.
3. Optionally handle display in `presentForm()` (format, e.g., dates).

Since both places live in the package, fork and send a PR rather than
patching locally. Alternative: use a generic `text` type with `help:`
explaining the expected format, and validate in a model observer.

## Replacing the Vue layout

Pass a different `AdminLayout` to `bootAdminCore`:

```js
import MyLayout from './MyCustomLayout.vue';
bootAdminCore({ sitePages, corePages, AdminLayout: MyLayout });
```

Your layout receives `navigation`, `brand`, `auth`, `flash`, `locale` as
Inertia shared props. Render whatever sidebar you want. The package's
`AdminLayout.vue` is a useful reference.

## Adding a dashboard widget

`AdminCore::dashboardStat()` accepts a callable returning
`['label', 'value', 'icon', ...]`. For richer widgets (charts, tables),
override the Dashboard Vue page:

```js
// resources/js/admin-spa/pages/Dashboard.vue — site page wins over core
```

The default `DashboardController::index()` passes `['stats' => AdminCore::dashboardStats()]`.
For other props, register a custom `/admin` route and Vue page.

## Multi-tenant filtering

When every row belongs to a tenant (school, organization), filter all
queries automatically. Three options:

### 1. Model global scope

```php
// Cleanest, tenant-aware at ORM level.
Article::addGlobalScope('tenant', fn ($q) => $q->where('tenant_id', auth()->user()->tenant_id));
```

The generic controller's `$model::query()` picks up the scope.

### 2. Override `findModel()`

For fine-grained control per action:

```php
protected function findModel(array $config, string $id): Model
{
    return parent::findModel($config, $id)
        ->where('tenant_id', auth()->user()->tenant_id)
        ->firstOrFail();
}
```

### 3. Custom middleware + query decorator

Route-level: set `tenant_id` on the current connection / query builder
before the controller action runs. Requires custom plumbing; usually
only needed for extreme scale.

## Programmatic resource registration

Registering resources via config is fine for static lists. For dynamic
cases (plugins, multi-tenant with per-tenant resources), register from
a service provider's `boot()`:

```php
public function boot(): void
{
    $modules = Module::enabled()->get();   // DB-driven module config
    foreach ($modules as $m) {
        AdminCore::resource($m->name, json_decode($m->config, true));
    }
}
```

Just keep in mind the registry is a per-process singleton — registrations
live for the lifetime of the worker.

## Event broadcasting

No events fired by the package. If you need "article was edited in the
admin", listen on the Eloquent `updated` event from a model observer.
