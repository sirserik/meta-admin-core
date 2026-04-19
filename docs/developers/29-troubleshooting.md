# 29. Траблшутинг

Частые проблемы и решения.

## Установка / обновление

### <a id="github-auth"></a>`composer update` — «Could not authenticate against github.com»

Приватный репозиторий + нет auth у composer.

**Решение 1 — Personal Access Token**:
```bash
composer config --global --auth github-oauth.github.com ghp_xxx
```

**Решение 2 — `auth.json` в корне проекта** (в `.gitignore`):
```json
{ "github-oauth": { "github.com": "ghp_xxx" } }
```

### `composer update` — «Failed to execute git clone git@github.com:...»

Composer переключился на SSH, но в `known_hosts` нет github.com.

Принудительно HTTPS в `composer.json`:
```json
"config": {
    "github-protocols": ["https"]
}
```

И clearcache: `composer clearcache`.

### «Class Meta\AdminCore\Facades\AdminCore not found»

Пакет не установлен или autoload не обновлён.

```bash
composer install
composer dump-autoload
```

Проверь в `composer.json` наличие `"meta/admin-core"` в `require`.

### Миграции не найдены

```bash
php artisan migrate
# → Nothing to migrate.
```

Проверь, что `AdminCoreServiceProvider` включён. Laravel авто-discovery
должна его поднять (в `composer.json` пакета есть `extra.laravel.providers`).
Если нет — добавь в `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    Meta\AdminCore\AdminCoreServiceProvider::class,
];
```

## Runtime

### «Facade root has not been set»

Пытаешься дёргать `AdminCore::resource(...)` в `register()` сервис-
провайдера. Перенеси в `boot()`:

```php
public function boot(): void
{
    if (!class_exists(\Meta\AdminCore\Facades\AdminCore::class)) return;
    AdminCore::resource(...);
}
```

### Админка пустая / 404

- Проверь `config('admin-core.prefix')` — совпадает ли с URL.
- Проверь middleware — не блокируют ли (например, `verified` без
  email-verification не пустит).
- `php artisan route:list --path=admin` — видны ли твои ресурсы.

### Вход = `/login`, но и `/login` не открывается

Пакет не ship'ит auth-экраны — это делает Laravel Breeze / Fortify /
твой самописный. Если ты только поставил пакет и `/login` даёт 404
— установи Breeze:

```bash
composer require laravel/breeze --dev
php artisan breeze:install
npm install && npm run build
php artisan migrate
```

### `/admin/logout` возвращает 404

До v0.28 такого роута не было — добавь в `routes/web.php`:

```php
Route::middleware(['auth','verified'])->prefix('admin')->name('admin.')
    ->group(function () {
        Route::post('/logout', [LogoutController::class, 'destroy'])->name('logout');
    });
```

С v0.28+ идёт из коробки.

### После logout'а показывается главная внутри iframe админки

Inertia-возврат. Контроллер logout должен вернуть `Inertia::location('/')`
при X-Inertia header'е:

```php
if ($request->header('X-Inertia')) {
    return Inertia::location('/');
}
return redirect('/');
```

C v0.28+ это уже внутри `LogoutController` пакета.

### «Session has expired» каждые 5 минут

Настрой `SESSION_LIFETIME` в `.env` (минуты):

```env
SESSION_LIFETIME=120
```

## Переводы

### `$model->translate('title')` возвращает null

Проверь:
1. Trait `Translatable` подключён.
2. Свойство `$translatableFields` установлено.
3. Есть запись в `translations` с совпадающим `translatable_type`
   (см. [25. Morph-types](./25-migrations.md)).

```sql
SELECT * FROM translations
WHERE translatable_type = 'App\\Models\\Article'
  AND translatable_id = 42;
```

### Админка показывает «(без названия)» в меню

Типичное — `translatable_type` в БД не совпадает с тем, что ожидает
модель. Запусти миграцию:

```php
DB::table('translations')
    ->where('translatable_type', 'App\\Models\\MenuItem')
    ->update(['translatable_type' => \Meta\AdminCore\Models\MenuItem::class]);
```

## Media

### Загрузка картинки даёт ошибку

- Проверь `php.ini`: `upload_max_filesize`, `post_max_size` (обычно
  > 20M).
- Проверь права: `storage/app/public/` — writable для веб-сервера.
- Проверь symlink: `php artisan storage:link`.
- Проверь формат — SVG / GIF иногда отклоняются в зависимости от
  `intervention/image` версии.

### Изображения не отображаются после деплоя

Symlink `public/storage → storage/app/public/` слетел. Пересоздай:

```bash
php artisan storage:link
```

### Медиатека пустая, но файлы есть на диске

Записи в таблице `media` синхронизируются только через её контроллер.
Файлы, загруженные руками в `storage/` через SCP/Plesk, **не** появятся
в `/admin/media` — пакет их не сканирует.

Решение — загружать через админку или написать консольный команду для
ре-скана.

## PageBlock

### Блок не отображается на публичном сайте

Проверки по порядку:
1. `is_active = true`.
2. `status = 'published'`.
3. `publish_at` <= now() или NULL.
4. `unpublish_at` > now() или NULL.
5. Кэш страницы сброшен (`Cache::flush()`).
6. Blade-шаблон использует `->published()` scope, а не `->where(...)`.

### «duplicate block_key»

`block_key` уникален в пределах страницы. При создании блока — если
редактор не задал явно, генерится автоматически. Если попался коллизион
(два блока с одним именем) — поменяй ключ руками.

### Scheduled publishing не срабатывает

- Проверь cron:
  ```bash
  crontab -l
  ```
  Должна быть строка `* * * * * php artisan schedule:run`.
- Запусти вручную и посмотри:
  ```bash
  php artisan admin-core:apply-schedule --dry-run
  ```
- Убедись, что `AdminCore::schedulable(...)` вызвана.
- Проверь, что trait `Publishable` реально подключён:
  ```php
  class_uses_recursive(App\Models\Article::class);
  ```

## Права

### «403 Forbidden» на своих же экранах

Проверь:
- Ты под какой ролью? (`$user->getRoleNames()`).
- Матрица `/admin/permissions` — у этой роли `articles.view`
  включено?
- Spatie-кэш: `php artisan permission:cache-reset`.

### Матрица прав пустая / нет чекбоксов

- Установлен ли `spatie/laravel-permission`?
- Роли существуют? (`Role::all()`).
- После первого визита матрица лениво создаёт permissions — зайди на
  неё, обнови страницу.

## Webhooks

### Webhook «последний запуск: ни разу»

- Тестовая кнопка работает? Если да — событие просто ещё не наступило.
- События выбраны правильно? (Совпадают с `event name` моделей).
- Модель `use Webhookable`?
- Удали и пересоздай webhook, иногда БД-кэш виноват.

### HMAC не совпадает

- Проверь, что секрет **точно** такой на обеих сторонах.
- Считай подпись от **body**, а не от `json_decode`-результата (важен
  exact byte stream).
- Сравнивай через timing-safe compare (`hash_equals` в PHP,
  `crypto.timingSafeEqual` в Node).

## Deploy

### `composer install` упал на проде, сайт не работает

```bash
git checkout HEAD~1  # откати коммит
composer install      # должно пройти
php artisan config:clear
```

Потом разбирайся локально, что не так в новом коммите.

### Frontend не обновился после деплоя

Проверь:
1. `npm run build` выполнен, `public/build/assets/admin-spa-*.js` —
   свежий файл.
2. `public/build/manifest.json` — обновлён.
3. Bootstrap cache: `php artisan view:clear && php artisan route:clear`.
4. Кэш браузера: hard reload (Cmd+Shift+R).
5. OPcache: `php -r "opcache_reset();"`.

## Общие диагностические команды

```bash
# Какая версия пакета установлена
composer show meta/admin-core | grep versions

# Какие миграции прогнаны
php artisan migrate:status

# Какие роуты зарегистрированы
php artisan route:list --path=admin

# Что в кэше
php artisan cache:forget admin-core.sitemap.xml

# Что в спатом кэше прав
php artisan permission:cache-reset

# OPcache
php -r "opcache_reset();"

# Laravel-оптимизация (кладёт кэш конфига/роутов)
php artisan optimize

# Обратка — всё чистит
php artisan optimize:clear
```

## Ничего не помогает

1. Скачай бэкап (`/admin/backup`).
2. Восстанови БД локально, воспроизведи проблему.
3. Зайди в GitHub issues пакета — возможно, уже есть.
4. Напиши разработчику с:
   - Версия пакета.
   - PHP и Laravel версии.
   - Полный стек ошибки (`storage/logs/laravel.log`).
   - Что делал до ошибки.

---

## Дополнительные ссылки

- CHANGELOG — `../CHANGELOG.md`
- GitHub — https://github.com/sirserik/meta-admin-core
- Laravel docs — https://laravel.com/docs
- Inertia docs — https://inertiajs.com
- Spatie/laravel-permission — https://spatie.be/docs/laravel-permission
- Intervention/image — https://image.intervention.io/
