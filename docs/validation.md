# Validation

All validation for generic-resource CRUD is derived from the resource
config — you don't hand-write `$request->validate([...])`. This page
explains the rules generated, how errors surface, and how to extend.

## Rule generation

The controller's `validated()` method assembles a single rules array at
request time, then calls `$request->validate($rules)`. Components:

### Image field

```
{image_field}         → nullable|image|max:5120
remove_{image_field}  → nullable|boolean
```

`max:5120` = 5 MB. Override by subclassing the controller (rare).

### Translatable fields × locales

For every field in `translatable`:

```
{field}.ru → required|string  (if field has `required: true` in its `fields[]` entry)
{field}.ru → nullable|string
{field}.kk → nullable|string
{field}.en → nullable|string
```

Only the primary locale (`ru`) enforces `required` — non-primary locales
are always optional.

### Plain attributes

For each entry in `attributes`, rules come from the attribute's `type`
(see [attributes](attributes.md) for the full map). The base is
`required` or `nullable` depending on the `required` flag.

Examples:

| Config                                               | Rule                     |
|------------------------------------------------------|--------------------------|
| `type=text, required=true, max=100`                  | `required\|string\|max:100`|
| `type=email`                                         | `nullable\|email\|max:255` |
| `type=url, required=true`                            | `required\|url\|max:2000`  |
| `type=select, options=[…], required=true`            | `required\|in:draft,published,archived` |
| `type=boolean`                                       | `nullable\|boolean`      |
| `type=number`                                        | `nullable\|numeric`      |
| `type=date`                                          | `nullable\|date`         |

### Unique constraints

`'unique' => true` adds:

```
unique:{table},{column}         → on create
unique:{table},{column},{id}    → on update (ignores current row)
```

Table is read from the model's `getTable()`. Column is the attribute's
`name`.

For composite uniqueness (e.g. `unique by school_id + slug`), extend
the controller or add a DB unique index and catch the QueryException.

## Error display

Validation errors flow through Laravel's standard session bag. Inertia's
`useForm` exposes them on `form.errors`:

```vue
<TranslatableField
    :errors="form.errors"
    :name="f.name" ...
/>
<SimpleField
    :errors="form.errors"
    :name="a.name" ...
/>
```

Both components render errors inline (red text below the input). Nested
translatable errors use dotted keys — `errors['title.ru']`,
`errors['title.kk']` — and are surfaced under the right locale tab.

## Custom rules

For any logic beyond what `types` give you, create a thin controller
that extends `ResourceController` and override `validated()`:

```php
use Meta\AdminCore\Http\Controllers\ResourceController;

class ArticleController extends ResourceController
{
    protected function validated(Request $request, array $config, ?Model $existing = null): array
    {
        $data = parent::validated($request, $config, $existing);

        // Additional rule: published_at must be in the future on create
        if (!$existing && $data['published_at']) {
            $request->validate(['published_at' => 'after:now']);
        }

        return $data;
    }
}
```

Then override the route manually (before the catch-all) so your
controller handles this resource specifically. Or submit a PR to the
package with a new generic feature if it's broadly useful.

## Client-side validation

Beyond the native browser check (`required`, `type=email`), there's no
client-side validation layer. The form POSTs, Laravel responds with 422
and the errors object, Inertia swaps them into `form.errors`.

Pros: single source of truth (server-side). Cons: one round-trip per
submission. Add client-side checks in the Vue form page if you want
snappier UX — see [custom pages](custom-pages.md).

## Known limitations

### 1. No conditional requires

`'required'` is static. If field A is required **only when** field B is
`X`, you need a custom controller. No conditional-rule DSL is planned.

### 2. No multi-column unique

`'unique' => true` checks a single column. Composite uniqueness → DB
index + custom error handling.

### 3. Translatable fields can't have unique constraints

Uniqueness is a property of the table row, and translatable values live
in a separate `translations` table. Enforce via model observers if you
need, e.g., "no two published articles with the same slug".

### 4. Image validation is fixed

5 MB cap, Laravel's default `image` MIME set. Override the controller
for anything else.

## Debug tip

To see the exact rules array for a resource:

```php
// In a route closure or Tinker
$config = \Meta\AdminCore\Facades\AdminCore::getResource('articles');
$controller = app(\Meta\AdminCore\Http\Controllers\ResourceController::class);
$method = new ReflectionMethod($controller, 'validated');
$method->setAccessible(true);
// $method->invoke($controller, request(), $config, null);  // requires a real Request
```

Or just throw `dd($rules)` in a local copy of `validated()` while
debugging.
