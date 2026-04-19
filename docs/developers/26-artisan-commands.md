# 26. Artisan-команды

Список консольных команд пакета.

## `admin-core:install`

```bash
php artisan admin-core:install
```

Опубликовывает config, запускает базовые миграции, проверяет окружение.
Запускается один раз при установке пакета.

## `admin-core:apply-schedule`

```bash
php artisan admin-core:apply-schedule
php artisan admin-core:apply-schedule --dry-run
```

Идемпотентный тикер scheduled publishing. Итерирует все
`AdminCore::schedulable(...)`-модели и флипает `status` на
timestamp-crossings.

**Что делает за один запуск**:
1. Для каждой модели `Publishable` — берёт `duePublish()` → `status='published'`.
2. Затем `dueUnpublish()` → `status='draft'`.
3. Печатает сводку `Published X, unpublished Y`.

**Wire-up в Laravel Scheduler**:

```php
// routes/console.php
Schedule::command('admin-core:apply-schedule')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
```

+ системный cron `* * * * * php artisan schedule:run`.

## `admin-core:export`

```bash
php artisan admin-core:export [--out=path.zip]
```

Экспортирует текущее состояние CMS в один ZIP:

- `manifest.json` — метаданные (версия, список таблиц, счётчики).
- `page_blocks.json`, `menu_items.json`, `translations.json`,
  `taxonomy_terms.json`, `taxonomy_term_model.json`, `settings.json`.
- `resource.{name}.json` — по одному на каждый `AdminCore::resource()`.

По умолчанию кладёт в `storage/app/exports/{YYYY-MM-DD-HHMM}.zip`.

### Флаги

- `--out=path.zip` — свой путь вывода.

### Пример

```
$ php artisan admin-core:export

  Exported to storage/app/exports/2026-04-19-1840.zip

  +---------------------+------+
  | Section             | Rows |
  +---------------------+------+
  | page_blocks         | 301  |
  | menu_items          | 12   |
  | translations        | 3738 |
  | settings            | 50   |
  | resource.articles   | 42   |
  | resource.news       | 87   |
  | …                   |      |
  +---------------------+------+
```

Используй ZIP-файлы для:
- **Резервных копий** перед массовыми правками.
- **Переноса** staging → prod (экспортишь в staging, импортишь в prod).
- **Сидинга** свежих инсталляций.

## `admin-core:import`

```bash
php artisan admin-core:import path.zip [--mode=merge|replace] [--dry-run]
```

Читает ZIP, созданный `admin-core:export`, и заливает содержимое в БД.

### Режимы

- **`merge`** *(default)* — upsert по primary key. Строки, которых нет в
  ZIP'е, не трогает.
- **`replace`** — truncate каждой таблицы перед upsert. **Осторожно:
  удаляет всё, чего нет в экспорте.**

### Флаги

- `--dry-run` — распарсит и покажет, что было бы сделано, но не пишет
  в БД.

### Пример

```bash
# Scenario: скопировать всё из staging на prod.
# 1. На staging
php artisan admin-core:export --out=/tmp/staging-2026-04-19.zip
# 2. Перекопировать на prod (scp / upload)
# 3. На prod — dry-run
php artisan admin-core:import /tmp/staging-2026-04-19.zip --dry-run
# 4. Если OK — применить
php artisan admin-core:import /tmp/staging-2026-04-19.zip
```

### Что ВНУТРИ import

- Транзакция.
- FK checks **отключаются** на время (SQLite `PRAGMA foreign_keys=OFF`,
  MySQL `SET FOREIGN_KEY_CHECKS=0`). Порядок таблиц в ZIP роли не играет.
- На каждую таблицу — chunk'ованный `upsert()` по `id`.

### Что не импортируется

- Пользователи (`users` table) — не часть экспорта, чтобы не затереть.
- Auth-токены.
- Активность логи.

Если они тебе нужны — бэкап БД (через `/admin/backup`) + ручной мерж.

## Планы на будущие релизы

Сейчас в core нет:
- `admin-core:prune-revisions` — одноразовая очистка старых ревизий.
- `admin-core:rebuild-sitemap` — dump sitemap в file (для CDN).
- `admin-core:fsck` — проверка консистентности (orphan translations,
  ревизии без модели и т.д.).

Если нужны — заведи issue или напиши свою команду поверх пакета.

## Свои команды

Чтобы добавить свою команду, связанную с админкой (например,
«пересчитать количество просмотров у статей по логу»):

```php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class RecalculateViewsCommand extends Command
{
    protected $signature   = 'app:recalculate-views';
    protected $description = 'Пересчитать views_count по access_log';

    public function handle(): int
    {
        // …
        return self::SUCCESS;
    }
}
```

Laravel сам подхватит. Никакой интеграции с пакетом не нужно, если
команда не дёргает `AdminCore` facade.

## Следующее

→ [27. Расширение Vue-интерфейса](./27-extending-admin-ui.md)
