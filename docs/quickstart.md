# Quickstart: first resource

After [Installation](installation.md), let's register a simple **Articles**
resource. No controller, no Vue page — just one call in a service
provider.

## The model

```php
// app/Models/Article.php
use App\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use Translatable;

    protected $translatableFields = ['title', 'excerpt', 'content'];

    protected $fillable = [
        'slug', 'featured_image', 'category',
        'is_published', 'is_featured', 'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_featured'  => 'boolean',
        'published_at' => 'datetime',
    ];
}
```

The `Translatable` trait (implemented per-site or from Spatie) routes
`title`, `excerpt`, `content` to a `translations` table keyed by model
type + id + locale + field. See [translatable fields](fields.md) for
details.

## The migration

```php
Schema::create('articles', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();
    $table->string('featured_image')->nullable();
    $table->string('category')->nullable();
    $table->boolean('is_published')->default(false);
    $table->boolean('is_featured')->default(false);
    $table->timestamp('published_at')->nullable();
    $table->timestamps();
});
```

The `title`, `excerpt`, `content` columns do **not** live on `articles` —
they're in `translations`. Optional: add them as physical columns too and
the package will mirror the `ru` locale there for public-site queries.

## Register the resource

```php
// app/Providers/AppServiceProvider.php
use App\Models\Article;
use Meta\AdminCore\Facades\AdminCore;

public function boot(): void
{
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
            ['name' => 'slug',         'type' => 'text',           'label' => 'Slug', 'placeholder' => 'автогенерация'],
            ['name' => 'category',     'type' => 'text',           'label' => 'Категория'],
            ['name' => 'is_published', 'type' => 'boolean',        'label' => 'Опубликована'],
            ['name' => 'is_featured',  'type' => 'boolean',        'label' => 'Рекомендуемая'],
            ['name' => 'published_at', 'type' => 'datetime-local', 'label' => 'Дата публикации'],
        ],
    ]);
}
```

That's it. Reload the admin and you'll see:

- **Sidebar:** a new `Статьи` link under `Контент`
- **`/admin/articles`:** paginated list with search
- **`/admin/articles/create`:** form with `Название / Краткое описание / Содержимое` (Tiptap) on tabs `RU / KK / EN`, sidebar with the scalar attributes and image upload
- **`/admin/articles/{id}/edit`:** same form pre-filled
- **Delete / toggle publish buttons**

Server-side, routes dispatched through `ResourceController` handle CRUD,
validation, image upload, translations saving and the toggle-publish
action.

## Add a dashboard stat

```php
AdminCore::dashboardStat(fn () => [
    'label' => 'Статей',
    'value' => \App\Models\Article::count(),
    'icon'  => 'fa-newspaper',
]);
```

Shown as a card on `/admin`.

## Add a sidebar link (non-resource)

For specialised screens that aren't simple CRUD:

```php
AdminCore::menuItem(
    label: 'Бэкапы',
    href:  '/admin/backup',
    icon:  'fa-database',
    menu:  'Система',
    order: 77,
);
```

These appear in the same sidebar alongside resources.

## Next steps

- [Full Resource API reference](resources.md) — every config key
- [Attribute types](attributes.md) — what `select`, `color`, etc. actually do
- [Images](images.md) — how uploads, URLs and deletion work
- [Dynamic FK selects](select-options.md) — if you have a `school_id`
  column and want a dropdown of schools
