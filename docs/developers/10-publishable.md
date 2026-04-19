# 10. Scheduled Publishing — trait `Publishable`

«Опубликовать в четверг 8:00», «Снять с публикации в воскресенье вечером».
Работает для любой модели, которая подключила trait.

## Концепция

На таблицу добавляются два поля:

- `publish_at` (timestamp, nullable) — когда блок/статью перевести из
  `draft` в `published`.
- `unpublish_at` (timestamp, nullable) — когда перевести обратно.

Команда `admin-core:apply-schedule` пробегает каждую минуту по
зарегистрированным моделям и флипает `status` там, где `publish_at <=
now()` или `unpublish_at <= now()`.

## Подключение

### 1. Trait на модель

```php
use Meta\AdminCore\Concerns\Publishable;

class Article extends Model
{
    use Publishable;
}
```

Модель должна иметь колонки:
- `status` (string, `draft` / `published`, default `draft`).
- `publish_at` (timestamp, nullable).
- `unpublish_at` (timestamp, nullable).

### 2. Миграция с хелпером

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Meta\AdminCore\Support\PublishableSchema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $t) {
            PublishableSchema::columns($t);
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $t) {
            PublishableSchema::drop($t);
        });
    }
};
```

`PublishableSchema::columns` добавляет `publish_at` + `unpublish_at` с
индексами (важно для performance, scheduler сканит по ним каждую минуту).

### 3. Регистрация у scheduler'а

В `AdminResourceServiceProvider::boot()`:

```php
use Meta\AdminCore\Facades\AdminCore;

AdminCore::schedulable(\App\Models\Article::class);
AdminCore::schedulable(\Meta\AdminCore\Models\PageBlock::class);  // уже из коробки
```

### 4. Laravel Scheduler

`routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('admin-core:apply-schedule')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
```

### 5. Системный cron

На сервере (Plesk / crontab):

```
* * * * * cd /path/to/project && php artisan schedule:run >/dev/null 2>&1
```

Без него scheduler не запустится.

## API trait'а

### Проверки

```php
$article->isPublished();    // status='published'
$article->isScheduled();    // publish_at > now()
$article->willUnpublish();  // unpublish_at > now()
```

### Query-скоупы

```php
Article::published()->get();
    // status='published'
    //   AND (publish_at IS NULL OR publish_at <= now())
    //   AND (unpublish_at IS NULL OR unpublish_at > now())

Article::scheduled()->get();
    // status='draft' AND publish_at > now()

Article::duePublish()->get();
    // status='draft' AND publish_at <= now()
    // — что ticker должен опубликовать

Article::dueUnpublish()->get();
    // status='published' AND unpublish_at <= now()
```

Используй `published()` в публичном запросе:

```php
$articles = Article::published()
    ->with('translations')
    ->latest('published_at')
    ->paginate(10);
```

## Ticker-команда

`php artisan admin-core:apply-schedule`

По умолчанию проходит всех `schedulable()`-моделей и применяет транзакции.

### Флаги

- `--dry-run` — показать, что бы сделал, но не писать.

```bash
php artisan admin-core:apply-schedule --dry-run
```

### Что печатает

```
  → publishing [App\Models\Article] #42
  → unpublishing [Meta\AdminCore\Models\PageBlock] #103
INFO  Published 2, unpublished 1.
```

## UI на форме блока / ресурса

На PageBlock форме уже есть два datetime-picker'а «Опубликовать в» /
«Снять с публикации в». Для собственных ресурсов:

```php
'attributes' => [
    ['name' => 'status', 'type' => 'select', 'label' => 'Статус', 'options' => [
        ['value' => 'draft',     'label' => 'Черновик'],
        ['value' => 'published', 'label' => 'Опубликовано'],
    ]],
    ['name' => 'publish_at',   'type' => 'datetime', 'label' => 'Опубликовать в'],
    ['name' => 'unpublish_at', 'type' => 'datetime', 'label' => 'Снять с публикации в'],
],
```

Pakage автоматически передаст их в форму и сохранит.

## Что если cron не запущен

- `publish_at` сохранится, но ничего не произойдёт.
- Блок будет вечным `draft`.

Проверь настройку cron:

```bash
# На сервере
crontab -l | grep schedule
```

Или через админку: попроси редактора создать тестовый блок с
`publish_at = now() + 1min`. Через 1–2 минуты статус должен стать
`published`.

## Пример интеграции с существующим status

Если у модели уже есть разная логика статусов (скажем, `draft / review
/ published / archived`), `Publishable` всё равно работает — она только
меняет `draft → published` и `published → draft` в точное время. Другие
состояния не трогает.

## Несколько time zones

Все timestamp'ы хранятся в UTC (Laravel default). Пользовательский
datetime-picker в админке сохраняет значение в timezone приложения
(`config('app.timezone')`). Если редакторы в разных часовых поясах —
показываем одну «каноничную» зону (обычно зона руководства / главного
офиса).

## Пагинация с учётом расписания

В публичном списке надо фильтровать:

```php
// ✓ правильно
Article::published()->paginate(10);

// ✗ неправильно — не учитывает publish_at/unpublish_at
Article::where('status', 'published')->paginate(10);
```

## Снимаешь `Publishable`? — дроп миграция

Если в какой-то момент решил, что расписание больше не нужно — откати
миграцию с `PublishableSchema::drop($t)`. Trait уберёшь с модели, и всё
чисто.

## Следующее

→ [11. `Revisionable` — ревизии](./11-revisionable.md)
