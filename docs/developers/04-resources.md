# 04. Регистрация ресурсов — `AdminCore::resource()`

Центральная функция: превращает модель в полноценный CRUD-раздел в
админке.

## Минимальный пример

```php
use Meta\AdminCore\Facades\AdminCore;

AdminCore::resource('articles', [
    'model' => \App\Models\Article::class,
    'label' => 'Статьи',
    'fields' => [
        ['name' => 'title',   'type' => 'text',   'label' => 'Заголовок', 'required' => true],
        ['name' => 'content', 'type' => 'editor', 'label' => 'Содержимое'],
    ],
    'attributes' => [
        ['name' => 'slug',         'type' => 'text',    'label' => 'Slug'],
        ['name' => 'is_published', 'type' => 'boolean', 'label' => 'Опубликовано'],
    ],
]);
```

Результат:

- `/admin/articles` — список с таблицей, фильтрами, поиском.
- `/admin/articles/create` — форма создания.
- `/admin/articles/{id}/edit` — форма редактирования.
- `/admin/articles/{id}` (PUT/DELETE) — сохранение и удаление.
- Пункт сайдбара «Статьи» → на список.
- Быстрое действие на дашборде «+ Статья».

Без контроллеров, роутов, Blade-шаблонов — админка использует generic
`ResourceController`.

## Полный справочник опций

### `model` *(обязательный)*

FQCN модели:

```php
'model' => \App\Models\Article::class,
```

Модель должна быть обычным Eloquent. Если хочешь переводы — подключи
trait `Translatable` (или локальный аналог). Опции `Publishable`,
`Revisionable`, `Webhookable`, `Taxable` — по желанию.

### `label` *(обязательный)*

Как называется в админке. Человечное имя во множественном числе.

```php
'label' => 'Статьи',
'label' => 'Образовательные программы',
```

### `singular` *(опционально)*

Единственное число для заголовков типа «Новая статья» / «Удалить
статью?».

```php
'singular' => 'Статья',
```

Если не задано — пакет пытается угадать (плохо для русского).
Лучше указать явно.

### `menu` *(опционально)*

В какую секцию сайдбара попадает. Секция не обязана быть зарегистрирована —
создаётся на лету.

```php
'menu' => 'Контент',      // → «Контент ▸ Статьи»
'menu' => 'Образование',   // → «Образование ▸ Программы»
```

Если не указано — попадает в «Другое».

### `icon` *(опционально)*

FontAwesome-класс (без `fas`):

```php
'icon' => 'fa-newspaper',
'icon' => 'fa-graduation-cap',
```

### `order_by` *(опционально)*

Порядок сортировки списка по умолчанию:

```php
'order_by' => ['sort_order' => 'asc', 'id' => 'asc'],
'order_by' => ['created_at' => 'desc'],
```

### `translatable` *(опционально)*

Какие поля модели переводимы. Массив имён:

```php
'translatable' => ['title', 'excerpt', 'content'],
```

Эти поля рендерятся в форме с вкладками `ru / kk / en`. В списке
показывается основная локаль.

### `image_field` *(опционально)*

Если у модели есть колонка типа «путь к картинке» — укажи имя. В форме
появится uploader, в списке — миниатюра.

```php
'image_field' => 'featured_image',
```

На БД-уровне ожидается колонка `featured_image` (string). Пакет сохраняет
путь, файл кладёт в `storage/app/public/{resource}/`.

### `fields` / `attributes` *(обязательно хотя бы одно)*

Описание полей на форме.

- **`fields`** — «контентные» поля (идут в основном блоке формы).
- **`attributes`** — «метаданные» (sidebar: slug, статус, категория, даты).

Формат элемента — см. [05. Типы полей](./05-fields.md).

### `actions` *(опционально)*

Дополнительные кнопки-действия на форме:

```php
'actions' => [
    ['label' => 'Предпросмотр', 'url' => fn ($m) => route('article.show', $m), 'primary' => false],
    ['label' => 'Опубликовать', 'route' => 'articles.publish', 'method' => 'POST', 'primary' => true],
],
```

- `label` — текст кнопки.
- `url` — callable получает модель, возвращает URL.
- `route` / `method` — для неразрушающих действий.
- `primary` — true = красная, false = серая.

### `searchable` *(опционально)*

Поля, по которым работает поиск в списке (сверху таблицы):

```php
'searchable' => ['title', 'slug', 'content'],
```

Пакет делает `LIKE %q%` по этим колонкам (+ по переводам, если
подключен `translatable`).

### `filters` *(опционально)*

Выпадающие фильтры над таблицей:

```php
'filters' => [
    ['name' => 'status', 'label' => 'Статус', 'options' => [
        ['value' => 'draft',     'label' => 'Черновик'],
        ['value' => 'published', 'label' => 'Опубликовано'],
    ]],
    ['name' => 'category', 'label' => 'Категория', 'options_from' => [\App\Models\Category::class, 'id', 'name']],
],
```

`options_from` — автоматически подтянуть варианты из другой модели.

### `policies` *(опционально)*

Подключить Laravel Policy для тонких прав:

```php
'policies' => [
    'view'   => 'viewAny',
    'create' => 'create',
    'update' => 'update',
    'delete' => 'delete',
],
```

Тогда `ResourceController` будет вызывать `Gate::authorize('update', $article)`
вместо generic `articles.update` permission.

### `page` *(опционально)*

Имя папки в `resources/js/pages/` для кастомного Vue-компонента:

```php
'page' => 'Articles',  // → использует pages/Articles/Index.vue, Form.vue, Show.vue
```

По умолчанию — generic `Resource/Index.vue` и `Resource/Form.vue`.

## Примеры

### Простой ресурс

```php
AdminCore::resource('news', [
    'model' => \App\Models\News::class,
    'label' => 'Новости',
    'singular' => 'Новость',
    'menu' => 'Контент',
    'icon' => 'fa-newspaper',
    'translatable' => ['title', 'excerpt', 'content'],
    'image_field' => 'featured_image',
    'order_by' => ['published_at' => 'desc'],
    'fields' => [
        ['name' => 'title',   'type' => 'text',     'label' => 'Заголовок', 'required' => true],
        ['name' => 'excerpt', 'type' => 'textarea', 'label' => 'Краткое описание'],
        ['name' => 'content', 'type' => 'editor',   'label' => 'Содержимое'],
    ],
    'attributes' => [
        ['name' => 'slug',         'type' => 'text',    'label' => 'Slug', 'placeholder' => 'автогенерация'],
        ['name' => 'status',       'type' => 'select',  'label' => 'Статус', 'options' => [
            ['value' => 'draft',     'label' => 'Черновик'],
            ['value' => 'published', 'label' => 'Опубликовано'],
        ]],
        ['name' => 'published_at', 'type' => 'datetime', 'label' => 'Опубликовано'],
        ['name' => 'is_featured',  'type' => 'boolean',  'label' => 'В топе'],
    ],
    'filters' => [
        ['name' => 'status', 'label' => 'Статус', 'options' => [
            ['value' => 'draft',     'label' => 'Черновик'],
            ['value' => 'published', 'label' => 'Опубликовано'],
        ]],
    ],
]);
```

### Ресурс с группировкой полей

Если полей много, их можно разложить на «секции»:

```php
'fields' => [
    ['name' => 'title',   'type' => 'text',   'label' => 'Название', 'group' => 'Основное'],
    ['name' => 'excerpt', 'type' => 'textarea','label' => 'Краткое', 'group' => 'Основное'],
    ['name' => 'content', 'type' => 'editor', 'label' => 'Полный текст', 'group' => 'Содержимое'],
],
'attributes' => [
    ['name' => 'slug',        'type' => 'text',    'label' => 'Slug',         'group' => 'SEO', 'group_icon' => 'fa-magnifying-glass'],
    ['name' => 'meta_title',  'type' => 'text',    'label' => 'Meta title',   'group' => 'SEO'],
    ['name' => 'meta_desc',   'type' => 'textarea','label' => 'Meta description','group' => 'SEO'],
    ['name' => 'published_at','type' => 'datetime','label' => 'Опубликовано', 'group' => 'Публикация'],
    ['name' => 'is_featured', 'type' => 'boolean', 'label' => 'В топе',       'group' => 'Публикация'],
],
```

Форма разделится на карточки:
- Основное: title + excerpt
- Содержимое: editor
- SEO (в сайдбаре, с иконкой лупы): slug, meta_*
- Публикация (в сайдбаре): даты, фичеринг

## Порядок регистрации

Ресурсы должны регистрироваться **в `boot()`** твоего провайдера. `register()`
запускается слишком рано — фасад может быть ещё не доступен.

```php
// ✓ Правильно
public function boot(): void
{
    if (!class_exists(AdminCore::class)) return;
    AdminCore::resource('articles', [...]);
}

// ✗ Ошибка — Facade root not set
public function register(): void
{
    AdminCore::resource('articles', [...]);
}
```

## Deferred registration

Если ресурс зависит от фичи:

```php
AdminCore::whenEnabled('sdg', function ($core) {
    $core->resource('sdg-goals', [...]);
});
```

Резолвится только если `config('admin-core.features.sdg.enabled') === true`.
Подробнее в [20. Feature Modules](./20-feature-modules.md).

## Custom CRUD-контроллер

Если generic `ResourceController` не подходит (сложная бизнес-логика
на store/update), зарегистрируй ресурс обычным способом, но добавь
свой маршрут ПЕРЕД пакетными:

```php
// routes/web.php
Route::middleware(['auth','verified'])->prefix('admin')->name('admin.')
    ->group(function () {
        Route::post('/articles', [\App\Http\Controllers\Admin\ArticleController::class, 'store'])
             ->name('articles.store');
        Route::put('/articles/{id}', [\App\Http\Controllers\Admin\ArticleController::class, 'update'])
             ->name('articles.update');
    });
```

Пакет видит, что роут с таким именем уже есть, и не регистрирует свой.
Список / форма (`index`, `create`, `edit`) по-прежнему обслуживаются
пакетом; кастомной логикой ты точечно перехватываешь write-операции.

## Следующее

→ [05. Типы полей и атрибутов](./05-fields.md)
