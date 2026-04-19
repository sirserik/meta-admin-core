# 18. Меню сайта

Навигация публичного сайта (header + footer) хранится в таблице
`menu_items`, редактируется через `/admin/menu`, читается из Blade.

## Схема

```
menu_items
 - id
 - parent_id      (unsigned, nullable)  — FK self
 - content_type   (string, 50)           — 'link' | 'page' | 'resource'
 - content_id     (unsigned, nullable)   — если контент привязан к записи
 - slug           (string, 255, nullable) — альтернатива URL
 - icon           (string, 100, nullable) — fa-…
 - is_published   (boolean)
 - menu_order     (int)
 - timestamps
```

Тексты (`title`, `url`) живут в полиморфной `translations` как для любой
Translatable-модели.

## Model API

```php
use Meta\AdminCore\Models\MenuItem;

MenuItem::roots()->published()->orderBy('menu_order')->get();

$item->children    // HasMany с orderBy menu_order
$item->parent      // BelongsTo
$item->translate('title');  // текущая локаль
$item->translate('url');
```

## В Blade — рендер меню

Типичный паттерн:

```blade
@php
    use Meta\AdminCore\Models\MenuItem;

    $menu = MenuItem::roots()
        ->where('is_published', true)
        ->orderBy('menu_order')
        ->with(['children' => fn ($q) => $q->where('is_published', true)])
        ->with('translations')
        ->get();
@endphp

<nav>
    <ul>
        @foreach ($menu as $item)
            <li>
                <a href="{{ $item->translate('url') ?? '#' }}">
                    {{ $item->translate('title') }}
                </a>
                @if ($item->children->isNotEmpty())
                    <ul class="submenu">
                        @foreach ($item->children as $sub)
                            <li>
                                <a href="{{ $sub->translate('url') ?? '#' }}">
                                    {{ $sub->translate('title') }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
```

## URL разный для разных локалей

`url` — **переводимое** поле. То есть для `ru` можно записать
`/ru/about`, для `kk` — `/kk/kolledzh-turaly`. Это полезно, если у тебя
разные slug'и на разных языках.

Если url одинаковый — пишешь только в русской локали, запросы на `kk` и
`en` через fallback получат тот же URL.

## Morph-type нюанс

Если у consumer-приложения есть **локальный** `App\Models\MenuItem`
(используется где-то в публичном коде), и он отличается от пакетного
`Meta\AdminCore\Models\MenuItem` — переводы будут сохранены с разными
`translatable_type`.

**Решение** — в локальной модели override `getMorphClass()`:

```php
namespace App\Models;

class MenuItem extends Model
{
    use \App\Traits\Translatable;

    public function getMorphClass(): string
    {
        return \Meta\AdminCore\Models\MenuItem::class;
    }
}
```

Переводы теперь общие. См. [25. Миграции](./25-migrations.md) про
нормализацию исторических данных.

## Consumer-специфичный MenuService

На больших сайтах часто пишут кастомный `MenuService`, который:
- Читает `MenuItem`.
- Добавляет/меняет пункты программно на основе бизнес-логики.
- Кэширует результат на запрос.

Простой пример:

```php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Meta\AdminCore\Models\MenuItem;

class MenuService
{
    public function forLocation(string $location = 'header'): array
    {
        return Cache::remember("menu.{$location}." . app()->getLocale(), 300, function () {
            return MenuItem::roots()
                ->where('is_published', true)
                ->orderBy('menu_order')
                ->with(['children' => fn ($q) =>
                    $q->where('is_published', true)->orderBy('menu_order'),
                ])
                ->get()
                ->map(fn ($item) => $this->toArray($item))
                ->all();
        });
    }

    private function toArray(MenuItem $m): array
    {
        return [
            'title'    => $m->translate('title'),
            'url'      => $m->translate('url') ?? '#',
            'icon'     => $m->icon,
            'children' => $m->children->map(fn ($c) => $this->toArray($c))->all(),
        ];
    }
}
```

И в Blade:

```blade
@foreach (app(\App\Services\MenuService::class)->forLocation('header') as $item)
    …
@endforeach
```

## Инвалидация кэша

Если кэшируешь — инвалидируй при правке меню:

```php
namespace App\Observers;

use Illuminate\Support\Facades\Cache;
use Meta\AdminCore\Models\MenuItem;

class MenuItemObserver
{
    public function saved(MenuItem $m): void { $this->bust(); }
    public function deleted(MenuItem $m): void { $this->bust(); }

    private function bust(): void
    {
        foreach (config('admin-core.locales', []) as $loc) {
            Cache::forget("menu.header.{$loc}");
            Cache::forget("menu.footer.{$loc}");
        }
    }
}

// register в AppServiceProvider
MenuItem::observe(MenuItemObserver::class);
```

## Программное создание

```php
use Meta\AdminCore\Models\MenuItem;

$root = MenuItem::create([
    'parent_id'    => null,
    'content_type' => 'link',
    'slug'         => 'about',
    'icon'         => 'fa-info-circle',
    'is_published' => true,
    'menu_order'   => 1,
]);
$root->saveTranslations('ru', ['title' => 'О колледже', 'url' => '/about']);
$root->saveTranslations('kk', ['title' => 'Колледж туралы', 'url' => '/kk/about']);

MenuItem::create([
    'parent_id'    => $root->id,
    'content_type' => 'link',
    'slug'         => 'history',
    'is_published' => true,
    'menu_order'   => 1,
])->saveTranslations('ru', ['title' => 'История', 'url' => '/about/history']);
```

## Footer vs. header — разделение

Пакетный MenuItem **не имеет location** колонки. Если хочется — добавь
её в миграции consumer-приложения:

```php
Schema::table('menu_items', function ($t) {
    $t->string('location', 30)->default('header'); // header | footer | other
});
```

И используй `where('location', 'footer')->roots()->...` для футерного
меню. Admin-форма меню тогда нуждается в поле выбора location — это
консьюмер-специфичный UI.

Альтернатива — по соглашению: корневые с `slug` начинающимся на
`footer-*` считать футером. Проще, но фрагильнее.

## Следующее

→ [19. Media и `focalCrop`](./19-media.md)
