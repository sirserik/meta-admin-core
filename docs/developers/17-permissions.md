# 17. Интеграция с `spatie/laravel-permission`

Пакет сам не реализует роли/права — он полагается на
[spatie/laravel-permission](https://github.com/spatie/laravel-permission)
как de-facto стандарт Laravel. Если spatie не установлен, экран прав в
админке скрывается.

## Установка

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

## Настройка User

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

## Создание ролей (сид)

```php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

Role::firstOrCreate(['name' => 'admin']);
Role::firstOrCreate(['name' => 'editor']);
Role::firstOrCreate(['name' => 'manager']);
```

Матрица прав (`/admin/permissions`) сама создаст permission-записи
вида `articles.view`, `articles.update`, `blocks.publish` и т.п. для
каждого зарегистрированного ресурса — тебе не надо seed'ить их руками.

## Назначение пользователя в роль

```php
$user->assignRole('editor');
$user->assignRole(['editor', 'manager']); // несколько
$user->removeRole('editor');
$user->hasRole('editor');
```

## Проверка в контроллере / Blade

```php
// Gate
if ($user->can('articles.update')) { … }

// Blade
@can('articles.update')
    <a href="{{ route('admin.articles.edit', $article) }}">Edit</a>
@endcan
```

## Super-admin

Обычно роли `admin` выдают всё-всё через Gate::before:

```php
// App\Providers\AuthServiceProvider::boot()
use Illuminate\Support\Facades\Gate;

Gate::before(function ($user, $ability) {
    return $user->hasRole('admin') ? true : null;
});
```

Теперь `admin` игнорирует матрицу — всегда `true` на любом `can`.

## Кэш прав

Spatie кэширует права на уровне приложения. После правки матрицы:

```bash
php artisan permission:cache-reset
```

Или из админки — [«Сброс кэша»](../users/15-cache.md).

## Pакетные middleware

Чтобы защитить весь админский раздел на уровне роли, можно обернуть:

```php
// config/admin-core.php
'middleware' => ['auth', 'verified', 'role:admin|editor'],
```

Теперь в `/admin/*` вообще не пускает без одной из ролей.

## Policy для fine-grained

Для сложной логики (например, «автор статьи может редактировать только
свои») — используй Laravel Policy:

```php
class ArticlePolicy
{
    public function update(User $user, Article $a): bool
    {
        if ($user->hasRole('admin')) return true;
        if ($user->hasPermissionTo('articles.update')) {
            return $a->author_id === $user->id;
        }
        return false;
    }
}
```

Привязка в ресурсе:

```php
AdminCore::resource('articles', [
    'policies' => [
        'update' => 'update',
        'delete' => 'delete',
    ],
    // …
]);
```

`ResourceController` вызовет `Gate::authorize('update', $article)` вместо
generic permission-check.

## Matrix UI — что делает

`/admin/permissions`:
1. Читает `AdminCore::getResources()`.
2. Для каждого ресурса × действия (`view`, `create`, `update`, `delete`,
   `publish`) создаёт permission, если нет.
3. Показывает матрицу с чекбоксами per role.
4. Сохранение — `syncPermissions()` на роли.

## Расширение действий

Стандартный набор — `['view', 'create', 'update', 'delete', 'publish']`.
Чтобы добавить своё (скажем, `articles.archive`):

Override контроллер пакета:

```php
namespace App\Http\Controllers\Admin;

use Meta\AdminCore\Http\Controllers\PermissionsController as Base;

class PermissionsController extends Base
{
    protected const ACTIONS = ['view', 'create', 'update', 'delete', 'publish', 'archive'];
}
```

И зарегистрируй роут-override в `routes/web.php` (до пакетных роутов):

```php
Route::middleware(['web', 'auth'])->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('/permissions',
            [\App\Http\Controllers\Admin\PermissionsController::class, 'index'])
            ->name('permissions.index');
    });
```

## Опт-ин проверка в ResourceController

Generic CRUD-controller в пакете использует spatie по умолчанию:

- `articles.view`    → разрешает `index`/`show`.
- `articles.create`  → разрешает `create`/`store`.
- `articles.update`  → разрешает `edit`/`update`.
- `articles.delete`  → разрешает `destroy`.
- `articles.publish` → разрешает изменение `status` на publish.

Если spatie не установлен и middleware не проверяет — пропускает всех
залогиненных.

## Несколько guard'ов

По умолчанию пакет использует guard `web`. Если нужно `api` guard:

```php
// config/permission.php
'default_guard' => 'api',
```

И в матрице при создании permissions будет автоматом браться из конфига.

## Следующее

→ [18. Меню сайта](./18-menu.md)
