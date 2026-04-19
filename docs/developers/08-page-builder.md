# 08. Работа с `PageBlock`

`PageBlock` — центральная Eloquent-модель пакета: одна строка = один блок
на странице. Публичная часть сайта читает их пачкой, типизирует через
DTO, рендерит обычным Blade.

## Модель `PageBlock`

```php
Meta\AdminCore\Models\PageBlock

Columns:
 - id
 - page_name   (string, 100)   — slug страницы
 - block_key   (string, 100)   — уникальный в пределах страницы
 - block_type  (string, 100)   — 'hero', 'content', 'stats', …
 - title       (text)          — основной заголовок (primary locale)
 - subtitle    (text)          — подзаголовок
 - content     (longtext)      — HTML
 - data        (json)          — структурированные данные
 - settings    (json)          — опции рендеринга
 - is_active   (boolean)
 - status      (enum: draft|published|archived)
 - published_at (timestamp)
 - publish_at  (timestamp, nullable)
 - unpublish_at (timestamp, nullable)
 - sort_order  (int)
 - timestamps

Traits:
 - Publishable   — scopePublished/scheduled/duePublish
 - Revisionable  — автоснэпшот на updating
 - Translatable  — переводы через полиморфную таблицу
 - Webhookable   — page_blocks.created/updated/deleted
```

## Публичный запрос: все блоки страницы

```php
$blocks = PageBlock::published()
    ->forPage('home')
    ->active()
    ->orderBy('sort_order')
    ->get();
```

- `published()` — `status='published'` + учёт `publish_at` / `unpublish_at`.
- `forPage('home')` — `page_name='home'`.
- `active()` — `is_active=true`.

## Типизация блоков: PresentedBlock

Сырой `PageBlock` содержит `data` как array. Чтобы удобно обращаться к
полям, пакет оборачивает каждый блок в DTO:

```php
use Meta\AdminCore\Content\BlockTypeRegistry;

$blocks = PageBlock::published()->forPage('home')->active()->orderBy('sort_order')->get();
$presented = $blocks->map(fn ($b) => BlockTypeRegistry::present($b));
```

Или хелпером:

```php
$presented = $blocks->map(fn ($b) => presented_block($b));
```

## В Blade — прямой рендер

Рекомендованный паттерн для публичной страницы:

```blade
{{-- resources/views/pages/dynamic.blade.php --}}
@php
    use Meta\AdminCore\Models\PageBlock;
    use Meta\AdminCore\Content\BlockTypeRegistry;

    $blocks = PageBlock::published()
        ->forPage($pageSlug)
        ->active()
        ->orderBy('sort_order')
        ->get()
        ->map(fn ($b) => BlockTypeRegistry::present($b));
@endphp

@foreach ($blocks as $block)
    @include("blocks.{$block->block_type}", ['block' => $block])
@endforeach
```

И `resources/views/blocks/hero.blade.php`:

```blade
<section class="hero"
         style="background-image: url({{ $block->data['background'] ?? '' }});">
    <div class="container">
        <h1>{{ $block->title }}</h1>
        <p>{{ $block->subtitle }}</p>
        @foreach ($block->buttons() as $btn)
            <a href="{{ $btn['url'] }}" class="btn">{{ $btn['text'] }}</a>
        @endforeach
    </div>
</section>
```

## Типизированные DTO

Встроенные DTO пакета:

### `HeroBlock`

```php
$block->buttons(): array       // [{text, url}, …]
$block->backgroundImage(): ?string
$block->slides(): array        // для каруселей
```

### `LinksBlock`

```php
$block->items(): array         // [{url, title, description, icon}, …]
```

### `StatsBlock`

```php
$block->stats(): array         // [{number, label, icon}, …]
```

Все три наследуют `PresentedBlock` и сохраняют доступ к базовым полям
(`$block->title`, `$block->subtitle`, `$block->content`, `$block->data`,
`$block->settings`).

## Свои DTO

```php
namespace App\Content\Blocks;

use Meta\AdminCore\Content\PresentedBlock;

class ProgramCardBlock extends PresentedBlock
{
    public function tuition(): ?int
    {
        return $this->data['tuition'] ?? null;
    }

    public function duration(): ?string
    {
        return $this->data['duration'] ?? null;
    }

    public function scholarships(): array
    {
        return $this->data['scholarships'] ?? [];
    }
}
```

Регистрация:

```php
use Meta\AdminCore\Content\BlockTypeRegistry;

BlockTypeRegistry::register('program_card', \App\Content\Blocks\ProgramCardBlock::class);
```

Теперь:

```blade
@foreach ($block->scholarships() as $s)
    <span class="badge">{{ $s }}</span>
@endforeach
```

## Локализация

`PresentedBlock` автоматически подставляет перевод для текущей локали:

```blade
{{ $block->title }}     {{-- auto: translate('title', app()->getLocale()) --}}
```

Если перевод пуст — fallback по цепочке `kk → ru → en → raw`.

Доступ к конкретной локали:

```blade
{{ $block->translate('title', 'kk') }}
```

## Программное создание блока

```php
use Meta\AdminCore\Models\PageBlock;

$block = PageBlock::create([
    'page_name'  => 'home',
    'block_key'  => 'hero_main',
    'block_type' => 'hero',
    'title'      => 'Добро пожаловать',      // primary locale
    'subtitle'   => 'Лучшее образование',
    'data'       => [
        'background' => '/media/hero-bg.webp',
        'buttons'    => [
            ['text' => 'Поступить', 'url' => '/admission'],
        ],
    ],
    'is_active'  => true,
    'status'     => 'published',
    'sort_order' => 0,
]);

// Переводы
$block->saveTranslations('kk', [
    'title' => 'Қош келдіңіз',
    'subtitle' => 'Үздік білім',
]);
$block->saveTranslations('en', [
    'title' => 'Welcome',
    'subtitle' => 'Best education',
]);
```

## Удаление

```php
$block->delete();
```

Webhook-событие `page_blocks.deleted` уйдёт автоматически.
Ревизия не сохраняется при delete — только при `update()`.

## Кэш

Пакет флэшит кэш страницы автоматически при `saved`/`deleted`:

```php
// В PageBlock::booted()
Cache::forget('page_blocks_' . $block->page_name);
Cache::flush();
```

Поэтому если публичный код кэширует `page_blocks_home`, он увидит
обновление сразу после сохранения блока.

## Сид для новой страницы

```php
// database/seeders/HomePageBlocksSeeder.php

use Meta\AdminCore\Models\PageBlock;

class HomePageBlocksSeeder extends Seeder
{
    public function run(): void
    {
        PageBlock::updateOrCreate(
            ['page_name' => 'home', 'block_key' => 'hero_main'],
            [
                'block_type' => 'hero',
                'title'      => 'Welcome',
                'data'       => ['background' => '/media/hero.webp'],
                'is_active'  => true,
                'status'     => 'published',
                'sort_order' => 0,
            ],
        );
        // … остальные блоки
    }
}
```

`updateOrCreate` по `(page_name, block_key)` — идемпотентно.

## Следующее

→ [09. `Translatable` — мультиязычность](./09-translatable.md)
