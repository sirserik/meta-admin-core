# 11. Ревизии — trait `Revisionable`

Автоматический снэпшот атрибутов модели на каждое изменение. Встроенный
«undo» уровня БД.

## Как работает

На `updating`-событии модели trait сохраняет **пре-update состояние** в
таблицу `revisions`. Хранится JSON + автор + timestamp.

Восстановление — `$model->restoreRevision($revisionId)` — применяет
сохранённые атрибуты и делает `save()` (который генерирует **ещё одну
ревизию**, так что откат обратим).

## Таблица

```
revisions
 - id
 - revisionable_type  (string)      — FQCN модели
 - revisionable_id    (unsigned)    — ID записи
 - user_id            (unsigned, nullable) — кто правил (из Auth)
 - data               (json)        — полный snapshot attributes
 - note               (string, 255, nullable)
 - created_at         (timestamp)
```

Ревизия — **иммутабельная** (нет `updated_at`, нет `update()` в коде).

## Подключение

```php
use Meta\AdminCore\Concerns\Revisionable;

class Article extends Model
{
    use Revisionable;
}
```

Всё — больше ничего не нужно. На каждый `update()` создаётся ревизия.

Миграция `revisions` уже есть в пакете (создаётся автоматически при
`php artisan migrate`).

## API

### Список ревизий

```php
$article->revisions;                     // относиться по убыванию created_at

$article->revisions()->latest()->take(5)->get();
```

Относительный запрос MorphMany — поддерживает обычный eloquent.

### Восстановить

```php
$ok = $article->restoreRevision($revisionId);
```

Возвращает `true` при успехе, `false` если:
- Ревизия не найдена.
- Ревизия принадлежит другому типу или другой записи (security).

Текущее состояние **после восстановления** тоже попадает в новую
ревизию — то есть откат обратим.

### Skip на одну операцию

Иногда надо обновить без ревизии (например, увеличение счётчика
просмотров):

```php
$article->withoutRevision(function () use ($article) {
    $article->update(['views_count' => $article->views_count + 1]);
});
```

### Глобальный opt-out

```php
class Article extends Model
{
    use Revisionable;

    protected static bool $revisionable = false;
}
```

Тогда `update()` не создаёт ревизию. Полезно для технических
моделей, где история не нужна.

## Опции

### Ограничить кол-во ревизий

Для блогов с частыми правками история может раздуться. Установи лимит:

```php
class Article extends Model
{
    use Revisionable;

    public int $maxRevisions = 50;   // хранить только 50 последних
}
```

На каждой новой ревизии пакет удаляет старые поверх лимита.

### Исключить поля из snapshot

Большие поля (`content` на 100К символов) можно не включать в snapshot:

```php
class Article extends Model
{
    use Revisionable;

    protected array $revisionHidden = ['analytics_blob', 'cached_html'];
}
```

## Как хранится author

`user_id` заполняется из `Auth::id()` в момент `updating`. Если изменение
пришло из CLI (миграция, сидер, artisan-команда) — `null`.

В Vue-странице `Revisions/Index.vue` это показывается как «система / CLI».

## UI — /admin/{resource}/{id}/revisions

Generic Vue-страница ревизий показывает:
- Дата и время.
- Автор (имя / email из User).
- Кнопка «Показать» — развернуть JSON snapshot.
- Кнопка «Восстановить» — confirm → POST restore.

Для built-in PageBlock маршруты:

```
GET  /admin/blocks/{id}/revisions
POST /admin/blocks/{id}/revisions/{revId}/restore
```

Для любого зарегистрированного через `AdminCore::resource()`:

```
GET  /admin/{resource}/{id}/revisions
POST /admin/{resource}/{id}/revisions/{revId}/restore
```

Ссылка на историю рендерится в форме блока (правый сайдбар, кнопка
«История изменений»).

## Добавить кнопку на форму других ресурсов

В generic `Resource/Form.vue` кнопки по умолчанию **нет** — только
PageBlock добавляет её своим кастомным Form.vue.

Чтобы показать на других ресурсах — кастомизируй `Resource/Form.vue`
через [расширение UI](./27-extending-admin-ui.md) либо добавь ссылку
в своей кастомной форме:

```blade
<a href="{{ route('admin.revisions.index', ['resource' => 'articles', 'id' => $article->id]) }}">
    История изменений
</a>
```

## Диффы между ревизиями

В UI — нет diff-view (v1 ограничение). Сравнить две ревизии нужно
глазами, разворачивая snapshot JSON.

Программно:

```php
$a = Revision::find(1);
$b = Revision::find(2);

$diff = array_diff_assoc($a->data, $b->data);
```

Или через любую diff-библиотеку на JSON.

## Связь с удалением записи

При `delete()` модели — ревизии **остаются** в БД, так как foreign key
не установлен (интересно может быть "посмотреть что было в удалённой
записи"). Если хочешь cascade:

```php
// Миграция
Schema::table('revisions', function ($t) {
    // Вместо полиморфного FK можно через observer на модели
});

// Observer
class ArticleObserver
{
    public function deleted(Article $a): void
    {
        $a->revisions()->delete();
    }
}
```

## Performance

Каждая ревизия = одна строка JSON. Для модели с content на 500К символов
— ~500К в строке. 100 ревизий = 50МБ. Следи за `$maxRevisions`.

Индексы:

- `(revisionable_type, revisionable_id)` — быстрый per-record lookup.
- `created_at` — для сортировки.

Для очень высоконагруженных моделей (тысячи правок/день) рассмотри
сжатие snapshot'а:

```php
// Override в модели
public function revisionPayload(): array
{
    $data = parent::revisionPayload();
    return [
        'compressed' => base64_encode(gzencode(json_encode($data))),
    ];
}
```

Но на большинстве сайтов это over-engineering.

## Следующее

→ [12. `Taxable` — таксономии](./12-taxable.md)
