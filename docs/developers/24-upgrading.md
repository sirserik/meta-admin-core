# 24. Обновление пакета

Рабочий flow обновления `meta/admin-core` в consumer-приложении.

## Минорные и патч-релизы (0.x.y)

В рамках `^0.43` — SEMVER строго не соблюдается, но breaking-changes
идут в минорах (0.43 → 0.44). Patch-релизы (0.43.0 → 0.43.1) всегда
безопасны.

```bash
composer update meta/admin-core
php artisan migrate --force
npm run build
```

## Мажорные (0.x → 0.y)

Прочитай CHANGELOG между версиями — там описаны breaking changes.

Типичные шаги:

### 1. Забампить constraint

```json
// composer.json
"meta/admin-core": "^0.44"
```

### 2. Обновить

```bash
composer clearcache
composer update meta/admin-core --no-interaction
```

Если на proд — уточни наличие GitHub-токена для приватного репозитория
(см. [29. Траблшутинг](./29-troubleshooting.md)).

### 3. Запустить миграции

```bash
php artisan migrate --force
```

В большинстве релизов миграции идемпотентные — новые создаются, старые
не трогаются. Если в релизе есть миграция данных — в CHANGELOG будет
пометка «требует run one-off command».

### 4. Пересобрать фронт

```bash
npm run build
```

Инвайды (файлы `admin-spa-*.js`) нужно закоммитить в repo, если consumer
использует commit-based deploy (Plesk Git).

### 5. Сбросить кэш

```bash
php artisan cache:clear
php artisan view:clear
php artisan config:clear
php artisan route:clear
```

Пакет в `CacheService` сам дёргает `Cache::flush()` при некоторых
операциях, но после обновления безопаснее пройтись руками.

### 6. Запушить

```bash
git add composer.json composer.lock public/build
git commit -m "bump meta/admin-core to v0.44"
git push
```

Plesk pull → `composer install` → миграции через post-install-cmd.

## Откат (downgrade)

Если что-то сломалось:

```bash
composer require meta/admin-core:0.43.1
php artisan migrate:rollback
# Откат миграций опасен — читай CHANGELOG что именно откатывается
```

Безопаснее — **восстанови БД из бэкапа** (`/admin/backup`) и откати
composer.

## Deploy-path при приватном репо

На проде composer должен иметь доступ к
`github.com/sirserik/meta-admin-core`. Варианты:

### GitHub Personal Access Token

```bash
composer config --global --auth github-oauth.github.com ghp_xxx
```

Или создай `auth.json` в корне проекта (add to `.gitignore`):

```json
{
    "github-oauth": {
        "github.com": "ghp_xxx"
    }
}
```

### Public + HTTPS

Если репо публичный, composer работает без auth, но упирается в
60 requests/hour лимит. Решения:
- Всё равно добавить токен (5000 r/h).
- `"config": { "github-protocols": ["https"] }` в `composer.json`
  (на всякий, чтобы не свернул на SSH).

### SSH deploy key

Сложнее — создай SSH-ключ на проде, добавь public half в GitHub Deploy
Keys, а в composer.json укажи `"url": "git@github.com:..."`. Работает
без токенов, но нужно setup `known_hosts`.

## CI/CD

На GitHub Actions:

```yaml
- name: Composer install
  env:
      COMPOSER_AUTH: '{"github-oauth":{"github.com":"${{ secrets.GH_TOKEN }}"}}'
  run: composer install --no-dev --prefer-dist
```

## Изменения, требующие action в consumer

### Новые traits

Если пакет добавил trait (`Publishable`, `Revisionable`, `Webhookable`,
`Taxable`) — они **не активируются автоматически**. Твой код
продолжает работать. Чтобы начать использовать — добавь `use …` на
нужную модель.

### Изменение сигнатуры метода

Если в CHANGELOG написано «BREAKING: `foo($a, $b)` → `foo($a, $b, $c)`»
— найди все вызовы в consumer-приложении (IDE → find references) и
поправь.

### Миграция полиморфных типов

Иногда нужна (как при переезде с `App\Models\MenuItem` → `Meta\…\MenuItem`).
Такие миграции пишутся в consumer-приложении:

```php
DB::table('translations')
    ->where('translatable_type', 'App\\Models\\MenuItem')
    ->update(['translatable_type' => \Meta\AdminCore\Models\MenuItem::class]);
```

CHANGELOG подскажет, нужно ли.

## Проверка работоспособности после обновления

Быстрый smoke-test:

```bash
php artisan admin-core:apply-schedule --dry-run
php artisan admin-core:export --out=/tmp/test-export.zip
unzip -l /tmp/test-export.zip
```

И UI-проверка:
- Открой `/admin` — дашборд грузится.
- Открой `/admin/blocks` — список виден.
- Открой один блок → нажми «Сохранить» → ошибок нет.
- `/admin/menu` → меню грузится.
- `/admin/taxonomies`, `/admin/webhooks`, `/admin/forms` — экраны
  работают.

## Следующее

→ [25. Миграции и morph-типы](./25-migrations.md)
