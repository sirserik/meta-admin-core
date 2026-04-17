# Images

Set `image_field` on a resource to enable uploads for a single image
column. The package handles input rendering, storage, URL generation,
preview, replacement and cleanup.

```php
AdminCore::resource('schools', [
    // ...
    'image_field' => 'image',   // column name on the `schools` table
]);
```

## What you get

- **Upload input** on the form sidebar (`<input type="file" accept="image/*">`)
- **Preview** — existing image shown, replaced on file selection
- **Remove button** — clears the path and schedules deletion
- **Storage** under `storage/app/public/{resource-name}/`
- **Public URL** via `Storage::disk('public')->url()` or `media_url()` helper
- **Delete on destroy** — file unlinked when the row is destroyed
- **Replace on update** — previous file removed when a new one is
  uploaded

## DB column

A single `string` column, nullable:

```php
Schema::table('schools', function (Blueprint $table) {
    $table->string('image')->nullable();
});
```

Stores the path **relative to `storage/app/public`**, e.g.
`schools/abc123.jpg`. **Never** store the full URL here.

## Storage setup

Laravel's default:

```bash
php artisan storage:link
```

creates `public/storage` → `storage/app/public`. Confirm by POSTing a
file through the admin form and checking that
`storage/app/public/schools/...jpg` exists and the served URL
(`/storage/schools/...jpg`) resolves.

## URL generation

When rendering a row or form, the controller adds a sibling `image_url`
key:

```php
[
    'id' => 12,
    'image' => 'schools/abc.jpg',
    'image_url' => 'http://meta.edu.kz/storage/schools/abc.jpg',
    ...
]
```

Used by the Vue page as `<img :src="row.image_url">`.

Resolution order in `ResourceController::mediaUrl()`:

1. If a `media_url()` helper is defined in the consumer app, use it.
   (Lets you swap to CloudFront/CDN, versioning, etc.)
2. Otherwise `Storage::disk('public')->url($path)`.

Example consumer helper:

```php
// app/helpers.php (loaded via composer.json "files")
if (!function_exists('media_url')) {
    function media_url(?string $path): ?string {
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;
        return config('app.url') . '/storage/' . ltrim($path, '/');
    }
}
```

## Upload flow

When the form is submitted:

```
if ($request->hasFile($imageField)) {
    $path = $request->file($imageField)->store($resourceName, 'public');
    // previous file is removed (on update)
    $m->{$imageField} = $path;
} elseif ($request->boolean('remove_' . $imageField)) {
    // explicit removal with no replacement
    Storage::disk('public')->delete($m->{$imageField});
    $m->{$imageField} = null;
}
```

The form submits as `multipart/form-data` only when `image_field` is
set — Inertia's `forceFormData` flag is set accordingly.

## Validation

The controller adds:

```
{image_field}         nullable|image|max:5120     # 5 MB
remove_{image_field}  nullable|boolean
```

`image` → accepts `jpg/jpeg/png/bmp/gif/svg/webp` (Laravel default set).
If you need HEIC or restrict further, override `validated()` in a custom
controller.

## Delete on destroy

When the row is deleted through `/admin/{resource}/{id}` DELETE, the
file is removed:

```php
if ($config['image_field'] && $m->{$config['image_field']}) {
    Storage::disk('public')->delete($m->{$config['image_field']});
}
$m->delete();
```

**Soft-deleted models** still trigger the file deletion — if you want to
keep the file until permanent deletion, override `destroy()`.

## Tiptap editor image uploads

Separate from the form's main image. The rich-text editor POSTs
multipart to `config('admin-core.upload_url')` (default
`/admin/upload/image`). The consumer app must implement this endpoint:

```php
// routes/web.php (inside admin middleware group)
Route::post('/admin/upload/image', function (Request $r) {
    $r->validate(['file' => 'required|image|max:5120']);
    $path = $r->file('file')->store('editor', 'public');
    return response()->json([
        'location' => Storage::disk('public')->url($path),
    ]);
})->name('admin.upload.image');
```

The response shape `{"location": "<url>"}` is the TinyMCE convention
and is also what the package's Tiptap wrapper expects.

## Multiple images / galleries

Out of scope for `image_field`. Options:

1. **Hand-roll a relation** (e.g. `ArticleImage`) and override the Vue
   form page to render a gallery manager.
2. **Use Spatie's medialibrary** — store each image on the model as a
   Media row, render a drag-and-drop list in Vue. See
   [custom pages](custom-pages.md).

## Deleting attached files manually

Sometimes you want to clean up orphaned files (a user deleted an image
from the DB but the file stayed). One-liner:

```php
use Illuminate\Support\Facades\Storage;

collect(Storage::disk('public')->allFiles('schools'))
    ->reject(fn ($p) => School::where('image', $p)->exists())
    ->each(fn ($p) => Storage::disk('public')->delete($p));
```

Wrap in a scheduled Artisan command if this is a regular concern.
