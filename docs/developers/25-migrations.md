# 25. Миграции и morph-типы

Пакет автоматически загружает свои миграции через
`loadMigrationsFrom(__DIR__.'/../database/migrations')` в service
provider'е. Никакой публикации не нужно — `php artisan migrate`
подтянет.

## Шипаемые таблицы

| Миграция | Таблица | Назначение |
|---|---|---|
| `2026_04_18_000001` | `settings` | key-value site settings |
| `2026_04_18_000002` | `translations` | polymorphic i18n |
| `2026_04_18_000003` | `media` | медиатека |
| `2026_04_18_000004` | `activity_log` | аудит (если включено) |
| `2026_04_18_000005` | `contacts` | контактная запись |
| `2026_04_18_000006` | `media_legacy` | legacy import helper |
| `2026_04_18_000007` | `leads` | заявки |
| `2026_04_18_000008` | `page_blocks` | блоки страниц |
| `2026_04_18_000009` | `menu_items` | меню |
| `2026_04_19_000001` | `revisions` | версии |
| `2026_04_19_000002` | `webhooks` | outbound hooks |
| `2026_04_19_000003` | `taxonomy_terms`, `taxonomy_term_model` | теги/категории |
| `2026_04_19_000004` | `forms`, `form_submissions` | формы |

## Полиморфные типы

Пакет активно использует Laravel **morph-связи**:

- `translations.translatable_type` → любая Translatable-модель.
- `revisions.revisionable_type` → любая Revisionable-модель.
- `taxonomy_term_model.taxable_type` → любая Taxable-модель.
- Webhook **не полиморфен** (просто строковое имя события).

Что в колонку `*_type` попадает по умолчанию — FQCN модели:
`App\Models\Article`, `Meta\AdminCore\Models\PageBlock` и т.д.

## Проблема: две модели с одним таблицей

Если у consumer-приложения есть `App\Models\MenuItem` с тем же `$table
= 'menu_items'`, что и у пакетного `Meta\AdminCore\Models\MenuItem`:

- Записи в БД одни и те же (одна таблица).
- Но Translations пишутся с **разным** `translatable_type`:
  - `App\Models\MenuItem` (когда редактируется через public code).
  - `Meta\AdminCore\Models\MenuItem` (когда через админку).

Результат — переводы теряются: админка не видит то, что пишет public
code, и наоборот.

### Решение A: morph map (глобальный алиас)

В `AppServiceProvider::boot()`:

```php
use Illuminate\Database\Eloquent\Relations\Relation;

Relation::enforceMorphMap([
    'menu_item'  => \Meta\AdminCore\Models\MenuItem::class,
    'page_block' => \Meta\AdminCore\Models\PageBlock::class,
]);
```

После этого Laravel будет писать `translatable_type = 'menu_item'`
вместо FQCN. Обе модели должны вернуть тот же alias — override
`getMorphClass()`:

```php
class App\Models\MenuItem extends Model {
    public function getMorphClass(): string {
        return 'menu_item';
    }
}

// И в пакетной уже так сделано через morph map — Laravel сам резолвит.
```

**Минус**: запрос `App\Models\MenuItem::morphMany(...)` работает, но
ты должен ввести morph map консистентно везде.

### Решение B: прямой override `getMorphClass()`

Быстрее — заставить локальную модель вернуть FQCN пакетной:

```php
namespace App\Models;

class MenuItem extends Model
{
    public function getMorphClass(): string
    {
        return \Meta\AdminCore\Models\MenuItem::class;
    }
}
```

Теперь `public function translations()` в App\Models\MenuItem будет
использовать `translatable_type = 'Meta\AdminCore\Models\MenuItem'` —
совпадёт с админкой.

Мы использовали этот подход в etec.edu.kz.

### Миграция исторических данных

Если данные уже писались с «неправильным» типом — напиши миграцию:

```php
return new class extends Migration {
    public function up(): void
    {
        DB::table('translations')
            ->where('translatable_type', 'App\\Models\\MenuItem')
            ->update(['translatable_type' => \Meta\AdminCore\Models\MenuItem::class]);

        DB::table('translations')
            ->where('translatable_type', 'App\\Models\\PageBlock')
            ->update(['translatable_type' => \Meta\AdminCore\Models\PageBlock::class]);
    }

    public function down(): void
    {
        // reverse — опционально
    }
};
```

## Именование миграций

Пакетные идут с датой `2026_04_18_XXXXXX` / `2026_04_19_XXXXXX` —
поставь свои **позже** этих, чтобы они запускались после. Иначе твоя
миграция может сломаться от того, что таблица пакета ещё не создана.

## Добавить колонку к пакетной таблице

Пакет использует `Schema::create()`. Чтобы добавить что-то в `page_blocks`
(например, `legacy_id`) — создай миграцию в `database/migrations/` своего
приложения:

```php
Schema::table('page_blocks', function (Blueprint $t) {
    $t->string('legacy_id', 50)->nullable()->index();
});
```

Пакет не ломает эти колонки — они просто не участвуют в CRUD.

## Убрать пакетную таблицу

Если ты не используешь, например, `activity_log`:

```php
// Твоя миграция
Schema::dropIfExists('activity_log');
```

Но тогда админский экран `/admin/activity` будет 500'ить. Либо убери
пункт меню, либо верни таблицу пустой.

## Rollback пакетных миграций

`php artisan migrate:rollback` — откатывает последние. Если среди них
пакетные — они откатятся. Бекап обязателен.

## Переносимость между БД

Пакет поддерживает SQLite, MySQL, PostgreSQL:
- Все колонки через стандартные Laravel-типы (`string`, `text`, `json`,
  `timestamp`).
- JSON-поля — native JSON на MySQL 5.7+/Postgres, TEXT на SQLite.
- FOREIGN KEY — `onDelete('cascade')` поддерживается везде (в SQLite
  включается через `PRAGMA foreign_keys=ON`).

## Сид пакетных таблиц

Пакет не ship'ит сидов — консьюмер пишет свои:

```php
// database/seeders/AdminSetupSeeder.php

use Meta\AdminCore\Models\MenuItem;
use Meta\AdminCore\Models\PageBlock;
use Meta\AdminCore\Models\TaxonomyTerm;

public function run(): void
{
    // Root menu
    $about = MenuItem::firstOrCreate(
        ['slug' => 'about', 'parent_id' => null],
        ['is_published' => true, 'menu_order' => 1],
    );
    $about->saveTranslations('ru', ['title' => 'О нас', 'url' => '/about']);

    // Стартовые теги
    TaxonomyTerm::firstOrCreate(
        ['type' => 'category', 'slug' => 'news'],
        ['label' => 'Новости', 'sort_order' => 1],
    );

    // Стартовый hero блок
    PageBlock::firstOrCreate(
        ['page_name' => 'home', 'block_key' => 'hero_main'],
        [
            'block_type' => 'hero',
            'title' => 'Добро пожаловать',
            'is_active' => true,
            'status' => 'published',
        ],
    );
}
```

## Следующее

→ [26. Artisan-команды](./26-artisan-commands.md)
