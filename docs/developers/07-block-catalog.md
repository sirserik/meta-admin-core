# 07. Каталог блоков и DTO

`BlockCatalog` — источник истины для конструктора страниц. Он знает:

1. **Какие страницы** могут содержать блоки (`pagesGrouped()`).
2. **Какие типы блоков** существуют (`blockTypesFlat()`, `blockTypesGrouped()`).
3. **Какие схемы данных** у каждого типа (`blockSchema($key)`).

Пакет даёт дефолтную реализацию (`DefaultBlockCatalog`), consumer может
подменить её своим (`EtecBlockCatalog extends DefaultBlockCatalog`).

## Интерфейс

```php
namespace Meta\AdminCore\Contracts;

interface BlockCatalog
{
    public function pagesGrouped(): array;
    public function pageLabel(string $slug): string;
    public function pagesFlat(): array;
    public function blockTypesGrouped(): array;
    public function blockTypesFlat(): array;
    public function blockType(string $key): ?array;
    public function blockSchema(string $key): ?array;
}
```

## Переопределение в consumer-проекте

В `boot()` твоего провайдера:

```php
if (interface_exists(\Meta\AdminCore\Contracts\BlockCatalog::class)
    && class_exists(\App\Support\EtecBlockCatalog::class)) {
    $this->app->singleton(
        \Meta\AdminCore\Contracts\BlockCatalog::class,
        \App\Support\EtecBlockCatalog::class,
    );
}
```

Важно: `interface_exists`, а не `class_exists` — BlockCatalog это
интерфейс. Делай в `boot()`, не `register()` — пакет сам биндит дефолт
в `register()`, твой `singleton()` в `boot()` перезаписывает.

## Пример consumer-реализации

`App\Support\EtecBlockCatalog`:

```php
namespace App\Support;

use App\Models\Page;
use Illuminate\Support\Facades\Schema;
use Meta\AdminCore\Support\DefaultBlockCatalog;

class EtecBlockCatalog extends DefaultBlockCatalog
{
    public const PAGES = [
        'Главные' => [
            'home'         => 'Главная страница',
            'about'        => 'О колледже',
            'contacts'     => 'Контакты',
        ],
        'Образование' => [
            'programs'     => 'Программы',
            'schools-main' => 'Школы (главная)',
        ],
        // …
    ];

    protected function enabledPageSlugs(): ?array
    {
        if (!Schema::hasTable('pages')) return null;

        // Показываем только страницы со status='published'.
        return Page::where('status', 'published')
            ->pluck('page_key')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }
}
```

Дефолтный `pagesGrouped()` в пакете сам использует `enabledPageSlugs()`
для фильтрации `static::PAGES` — тебе только одна функция и одна
константа.

## `enabledPageSlugs()` — хук фильтрации

Добавлен в v0.29. Три варианта:

```php
// null (default) — показать всё
protected function enabledPageSlugs(): ?array { return null; }

// Whitelist — показать только эти
protected function enabledPageSlugs(): ?array {
    return ['home', 'about', 'contacts'];
}

// Динамика — например, из БД
protected function enabledPageSlugs(): ?array {
    return Page::where('status', 'published')->pluck('slug')->all();
}
```

Рендеринг в админке:

- Дропдаун «Страница» на форме блока показывает только разрешённые.
- На списке блоков фильтр «Страница» тоже усекается.

## Структура `PAGES`

```php
public const PAGES = [
    'Главные' => [              // человеческое название группы
        'home'     => 'Главная', // slug => label
        'about'    => 'О нас',
    ],
    'Студентам' => [
        'students' => 'Студентам',
        'library'  => 'Библиотека',
    ],
];
```

Группы (секции) — свободный текст, их можно добавлять сколько нужно.
Slugs — **уникальны** в пределах каталога.

## Структура `BLOCK_TYPES`

```php
public const BLOCK_TYPES = [
    'hero' => [
        'label'       => 'Главный баннер',
        'description' => 'Большой верхний баннер страницы',
        'icon'        => 'fa-flag',
        'category'    => 'Hero',
        'preview'     => '▇▇▇▇▇',
    ],
    'stats' => [
        'label'       => 'Статистика',
        'description' => 'Блок с числовыми показателями',
        'icon'        => 'fa-chart-bar',
        'category'    => 'Данные',
        'preview'     => '123',
    ],
    // …
];
```

- `category` — группирует в пикере типов.
- `preview` — ASCII-превью в списке (отображается в пикере).
- `icon` — FontAwesome-класс.

## Расширение набора типов

### Добавить свой тип

```php
// В EtecBlockCatalog:

public const BLOCK_TYPES = parent::BLOCK_TYPES + [
    'course_card' => [
        'label'       => 'Карточка курса',
        'description' => 'Курс с ценой и кнопкой записи',
        'icon'        => 'fa-graduation-cap',
        'category'    => 'ETEC',
        'preview'     => '🎓',
    ],
];
```

### Зарегистрировать блок-DTO

Тип в каталоге даёт имя для UI, но рендеринг на сайте — через DTO.
Зарегистрируй DTO в `register()` провайдера:

```php
use Meta\AdminCore\Content\BlockTypeRegistry;

public function register(): void
{
    BlockTypeRegistry::register('course_card', \App\Content\Blocks\CourseCardBlock::class);
}
```

Где `CourseCardBlock` наследует `PresentedBlock`:

```php
namespace App\Content\Blocks;

use Meta\AdminCore\Content\PresentedBlock;

class CourseCardBlock extends PresentedBlock
{
    public function price(): ?string
    {
        return $this->data['price'] ?? null;
    }

    public function buttonUrl(): ?string
    {
        return $this->data['button_url'] ?? null;
    }
}
```

Тогда в Blade:

```blade
@foreach ($page->blocks as $block)
    @if ($block->block_type === 'course_card')
        <div class="course-card">
            <h3>{{ $block->title }}</h3>
            <p>{{ $block->content }}</p>
            <strong>{{ $block->price() }} ₸</strong>
            <a href="{{ $block->buttonUrl() }}">Записаться</a>
        </div>
    @endif
@endforeach
```

Hide details: при `foreach` каждая итерация даёт объект DTO нужного
класса (решает `BlockTypeRegistry`).

## Схемы данных

`blockSchema($key)` возвращает описание структуры `data`-поля, которое
Vue-редактор использует для визуального заполнения:

```php
public function blockSchema(string $key): ?array
{
    return match ($key) {
        'stats' => [
            'items' => [
                'type' => 'repeater',
                'fields' => [
                    ['name' => 'number', 'type' => 'text'],
                    ['name' => 'label',  'type' => 'text', 'translatable' => true],
                    ['name' => 'icon',   'type' => 'icon'],
                ],
            ],
        ],
        'links' => [
            'items' => [
                'type' => 'repeater',
                'fields' => [
                    ['name' => 'url',         'type' => 'url'],
                    ['name' => 'title',       'type' => 'text', 'translatable' => true],
                    ['name' => 'description', 'type' => 'textarea', 'translatable' => true],
                    ['name' => 'icon',        'type' => 'icon'],
                ],
            ],
        ],
        default => null,
    };
}
```

Если схемы нет (`null`), редактор `data` рендерится в виде сырого
JSON-textarea — для разработчиков и экспериментов.

Встроенные схемы идут в `DefaultBlockCatalog::blockSchema()` для
основных типов.

## Fallback

Если `BlockCatalog` вообще не переопределён, пакет использует
`DefaultBlockCatalog` со всеми страницами META University и всеми
типами блоков. Для нового сайта — **сразу переопредели**, иначе
редакторы увидят в дропдауне чужие `sdg-goal-no-poverty` и прочее.

## Следующее

→ [08. Работа с `PageBlock`](./08-page-builder.md)
