# 09. Мультиязычность — trait `Translatable`

Пакет реализует переводы через **полиморфную таблицу**
`translations`: одна запись = одно переводное поле + локаль + значение.
Trait `Translatable` даёт моделям API для работы с ней.

## Таблица

```
translations
 - id
 - translatable_type  (string)   — FQCN модели, e.g. 'App\Models\Article'
 - translatable_id    (unsigned) — ID записи в своей таблице
 - locale             (string, 5)
 - field              (string, 100)
 - value              (text)
 - timestamps
```

Индексы: `(translatable_type, translatable_id)` + `locale` + уникальный
ключ `(translatable_type, translatable_id, locale, field)`.

## Подключение

```php
namespace App\Models;

use Meta\AdminCore\Concerns\Translatable;

class Article extends Model
{
    use Translatable;

    protected $translatableFields = ['title', 'excerpt', 'content'];
}
```

## API

### Чтение

```php
$article->translate('title');           // текущая локаль app()
$article->translate('title', 'kk');     // конкретная
$article->translate('content', 'en');
```

Цепочка fallback:

1. Запрошенная локаль.
2. `kk` (казахская).
3. `ru` (русская).
4. `en`.
5. Сырая колонка в основной таблице (`$model->title`).
6. `null`.

Fallback-цепочка настраивается на уровне пакета (константа в trait), по
умолчанию подходит под META-сайты (`kk > ru > en`). Если нужна другая
— оверрайд trait через свой.

### Сохранение

```php
$article->saveTranslations('kk', [
    'title' => 'Мақала',
    'content' => '<p>…</p>',
]);
```

Обновляет/создаёт строки в `translations`. Неуказанные поля **не
трогаются** — это inсremental save.

### Разом получить все переводы

```php
$all = $article->translations; // Eager collection

// Группировка по локалям
$grouped = $all->groupBy('locale')->map(
    fn ($rows) => $rows->pluck('value', 'field')->all()
);
// ['ru' => ['title'=>'…','content'=>'…'], 'kk' => [...], 'en' => [...]]
```

### В списке (N+1 prevention)

`Translatable` делает per-instance cache. Если подгружаешь коллекцию —
используй eager load `translations`:

```php
$articles = Article::with('translations')->get();

foreach ($articles as $a) {
    echo $a->translate('title', 'kk'); // не делает SELECT — берёт из relation
}
```

## Полиморфный тип — нюанс

Trait использует `get_class($this)` для `translatable_type`. Если у тебя
**есть две модели с одинаковым именем** (одна в app, одна в пакете), и
они пишут в одну и ту же таблицу — будет расхождение.

Пример: `App\Models\MenuItem` (local) и `Meta\AdminCore\Models\MenuItem`
(пакет). Админка пишет через пакетную → `translatable_type =
'Meta\AdminCore\Models\MenuItem'`. Публичная часть читает через
локальную → `translatable_type = 'App\Models\MenuItem'` → не находит.

**Решение**: override `getMorphClass()` в локальной модели:

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

Теперь обе модели обращаются к одному `translatable_type`, переводы
общие. См. также [25. Миграции](./25-migrations.md) про нормализацию
существующих данных.

## Локаль по умолчанию

Активная локаль берётся из `app()->getLocale()`. Контролируется:

- Middleware `SetLocale` (часто есть в consumer-приложении).
- Header `Accept-Language`.
- Query-параметр `?locale=`.

## Конфигурация локалей

Список допустимых локалей — в `config('admin-core.locales')`:

```php
'locales' => ['ru', 'kk', 'en'],    // по умолчанию
'locales' => ['en'],                // монолингвальный
'locales' => ['en','fr','es'],      // больше языков
```

Админские формы используют этот список для вкладок `ru/kk/en`. Первая
локаль — **основная**: колонка `title`/`content`/`subtitle` всегда пишется
на ней.

## Как делают переводимое поле на форме

В описании ресурса добавь в `translatable`:

```php
AdminCore::resource('articles', [
    'translatable' => ['title', 'excerpt', 'content'],
    // …
]);
```

Поля из `fields` с этими именами автоматически рендерятся с вкладками
переключения локали. См. [05. Поля](./05-fields.md).

## Отдельные поля в `data` блоков

PageBlock имеет поле `data` (JSON) — там часто лежат
списки/карточки. Если внутри нужно переводить текст:

```php
'data' => [
    'items' => [
        ['title' => ['ru' => 'Первое', 'kk' => 'Бірінші', 'en' => 'First'],
         'url'   => 'https://example.com'],
        // …
    ],
],
```

То есть каждое переводимое значение — само по себе `{ru, kk, en}`. Vue-
редактор данных знает об этом и рендерит вкладки для таких полей, если
схема указана (`blockSchema()` — см. [07. BlockCatalog](./07-block-catalog.md)).

В Blade:

```blade
@foreach ($block->items() as $item)
    <a href="{{ $item['url'] }}">
        {{ $item['title'][app()->getLocale()] ?? $item['title']['ru'] ?? '' }}
    </a>
@endforeach
```

Или через helper:

```blade
<a href="{{ $item['url'] }}">
    {{ locale_value($item['title']) }}
</a>
```

(если в consumer-приложении есть такой helper).

## Следующее

→ [10. `Publishable` — scheduled publishing](./10-publishable.md)
