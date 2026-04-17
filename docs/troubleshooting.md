# Troubleshooting

Real errors from real migrations, with their fixes.

## Installation / boot

### `Class "Meta\AdminCore\Facades\AdminCore" not found`

Package autoload didn't pick up the facade. Fix:

```bash
composer dump-autoload
php artisan config:clear
```

If still broken, check `composer.json` → `repositories` actually
points at a reachable location, then `composer update meta/admin-core -W`.

### `Route [admin.resource.index] not defined.`

The package's `routes/admin.php` didn't load. Verify:

```bash
php artisan route:list | grep admin/{resource}
```

If empty:

- Is `Meta\AdminCore\AdminCoreServiceProvider` listed in the package's
  `composer.json` under `extra.laravel.providers`? (It is in our
  package — consumer apps don't need to register it manually thanks to
  Laravel package discovery.)
- Is package discovery disabled in the consumer's composer.json (`"dont-discover": ["*"]`)? If yes, register the provider manually in `bootstrap/providers.php`.
- Did you run `composer dump-autoload` after `composer require`?

### `[admin-core] Inertia page not found: Resource/Index`

The Vue entry's page-glob didn't resolve the core page. Check:

1. `sitePages` and `corePages` globs in `admin-spa.js`.
2. The `corePages` path relative to `resources/js/admin-spa.js` — it
   should be `../../vendor/meta/admin-core/resources/js/pages/**/*.vue`.
3. That `vendor/meta/admin-core/resources/js/pages/Resource/Index.vue`
   exists on disk (path repo symlink unbroken).
4. `preserveSymlinks: true` is in `vite.config.js`.

### "Vue received a Component which was made a reactive object"

Vue was installed twice. Usually caused by Vite resolving both the
consumer app's Vue and the package's symlinked Vue. Fix:

```js
// vite.config.js
resolve: {
    preserveSymlinks: true,
    dedupe: ['vue'],
}
```

## Runtime

### `Resource [foo] not registered`

Request hit `/admin/foo` but no `AdminCore::resource('foo', ...)` exists
in any provider. Check:

```bash
php artisan tinker
>>> app(Meta\AdminCore\AdminCore::class)->getResources()->keys();
```

If the expected name isn't in the list, the provider isn't being booted
or the `resource()` call was skipped (e.g. conditionally inside an `if`
block).

### `Undefined property: App\Models\Foo::$image`

Model doesn't have the column named in `image_field`. Either:

- Add the column: `$table->string('image')->nullable();`
- Or remove `image_field` from the resource config.
- Or rename to match the existing column.

### `SQLSTATE[42S22]: Column not found: title`

The generic controller tried to write to a physical column named `title`
on a table that doesn't have it, because the field was listed in
`translatable`. The mirroring logic is guarded by `Schema::hasColumn()`,
so this should only happen if the schema cache is stale:

```bash
php artisan cache:clear
php artisan route:clear
# In dev, also: php artisan cache:clear twice — once for app, once for schema cache
```

### Fillable errors — `Add [slug] to fillable property`

The model's `$fillable` doesn't include the attribute column. Add it:

```php
protected $fillable = ['slug', 'is_published', 'published_at', ...];
```

Or use `$guarded = []` during development.

### `Method saveTranslations() does not exist`

The model doesn't implement `saveTranslations()`. Either:

- Install Spatie Translatable and use its trait.
- Write your own trait (see [fields](fields.md)).
- Remove translatable fields from the resource config.

### Date inputs empty on edit

Carbon serializes as ISO-8601, but `<input type="datetime-local">`
expects `Y-m-d\TH:i`. The controller's `presentForm()` should handle
this for attributes typed as `date` or `datetime-local`. If it's still
empty, verify:

- The attribute's `type` is `'date'` or `'datetime-local'` (not `text`).
- The model casts the column: `protected $casts = ['published_at' => 'datetime']`.

### Image preview doesn't show

Steps to check:

1. `php artisan storage:link` — does `public/storage` exist and point to
   `storage/app/public`?
2. In DB, is the `image` column path relative (`articles/abc.jpg`) or
   absolute (`/storage/articles/abc.jpg`)? The package expects **relative**;
   the `_url` key adds the prefix.
3. If you wrote a custom `media_url()` helper, does it return a
   correctly-prefixed URL?
4. Browser console — 404 on the URL? Storage link broken.

### `Action '' not defined`

A `PATCH /admin/resource/{id}/` call with an empty action. Happens if a
Vue form submits with `_method: 'patch'` but the URL is missing the
segment. Check the Vue form:

```js
form.post(`/admin/${resource}/${item.id}`, { forceFormData: true });
// not:
form.patch(`/admin/${resource}/${item.id}/`);
```

### "MethodNotAllowedHttpException" on save

Vue used POST/PUT/DELETE, but Laravel expected the spoofed `_method`
field. Inertia's `useForm` handles this if you add `_method: 'put'` in
the payload and call `form.post(...)`:

```js
const form = useForm({ _method: 'put', ...initial });
form.post(editUrl);
```

## Frontend

### Changes to package Vue files not picked up

Vite caches aggressively on symlinked paths.

```bash
rm -rf node_modules/.vite
npm run dev
```

And ensure `preserveSymlinks: true`.

### Tailwind classes in package components don't render

The package's Vue uses raw Tailwind classes. If the consumer's Tailwind
config excludes `vendor/**`, classes won't be generated. Fix:

```js
// tailwind.config.js  (v3)
content: [
    './resources/**/*.{js,vue,blade.php}',
    './vendor/meta/admin-core/resources/**/*.vue',
],
```

Tailwind v4 users: same idea via `@source` directive in CSS.

### FontAwesome icons show as empty square

The CSS for FontAwesome is imported inside the package's `admin-spa.js`:

```js
import '@fortawesome/fontawesome-free/css/all.min.css';
```

If your build strips the import (aggressive tree-shaking), icons won't
load. Add it manually in your entry.

## Composer

### `meta/admin-core ^0.1 requires ...` with a wall of conflicts

The path repo declared a version that doesn't match the required
constraint. In the path repo config:

```json
"options": {
    "symlink": true,
    "canonical": false,
    "versions": { "meta/admin-core": "0.3.0" }
}
```

Match `versions` to your current tag; re-run `composer update`.

### `Failed to clone ... Permission denied`

Trying to `composer require meta/admin-core` from the **private** GitHub
URL without auth. The repo is public — double-check the URL:

```
https://github.com/sirserik/meta-admin-core.git
```

## Tests

### Package tests fail because Laravel isn't bootstrapped

The `AdminCoreRegistryTest` runs plain PHPUnit — no Laravel, no
container, just pure object testing. If you add a test that needs
Laravel, require `orchestra/testbench` and change the test base class.

```bash
composer require --dev orchestra/testbench:^10.0
```

Then extend `Orchestra\Testbench\TestCase` in those tests.

## Asking for help

If you hit a problem not covered here:

1. Check `php artisan route:list | grep admin` — is the route there?
2. Check `AdminCore::getResources()` via Tinker — is the resource
   registered?
3. Look at the browser's network tab — what's the actual HTTP response?
4. Open an issue on the package repo with:
   - Laravel version, PHP version, package version
   - Relevant `AdminCore::resource(...)` config (scrubbed of secrets)
   - The exact error + stack trace
