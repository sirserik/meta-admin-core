# 12. Таксономии — trait `Taxable`

Полиморфные теги / категории / аудитории — любые «словарики» для
классификации контента.

## Структура БД

```
taxonomy_terms
 - id
 - type                 (string, 50)   — 'tag' | 'category' | …
 - slug                 (string, 120)
 - label                (string)
 - label_translations   (json, nullable)
 - sort_order           (int)
 - timestamps

 UNIQUE (type, slug)

taxonomy_term_model
 - id
 - term_id              → taxonomy_terms.id (cascade delete)
 - taxable_type         (string)
 - taxable_id           (unsigned)
 - timestamps

 UNIQUE (term_id, taxable_type, taxable_id)
 INDEX  (taxable_type, taxable_id)
```

Одна таблица терминов — любое количество словарей (`type` — разделитель).
Одна morph-pivot — любые модели.

## Подключение

```php
use Meta\AdminCore\Concerns\Taxable;

class Article extends Model
{
    use Taxable;
}
```

Больше ничего — таблицы едины для всего приложения.

## API trait'а

### Работа с привязкой

```php
$article->terms;                    // все термины, независимо от типа
$article->termsOfType('tag');       // Collection — только теги
$article->termsOfType('category');  // только категории
```

Синхронизация по типу:

```php
$article->syncTerms('tag', ['interview', 'opinion']);
$article->syncTerms('category', ['admissions']);
```

**Важно**: `syncTerms('tag', [...])` **не трогает** категории. Это
opinionated decision: sync внутри словаря, не между.

Если слага среди существующих нет — **создаётся автоматически** (с
label'ом, сгенерированным из slug'а). Полезно для UX: редактор пишет
«новый тег» прямо в форме, без визита на /admin/taxonomies.

### Query-скоупы

```php
Article::withTerm('category', 'admissions')->get();
    // статьи с категорией 'admissions'

Article::withAnyTerm('tag', ['interview', 'opinion'])->get();
    // статьи с хотя бы одним из этих тегов
```

### Eager load

```php
$articles = Article::with('terms')->get();
foreach ($articles as $a) {
    foreach ($a->terms as $t) {
        echo $t->type.':'.$t->slug."\n";
    }
}
```

## Админка

Экран `/admin/taxonomies` — CRUD по терминам:

- Переключатель словарей сверху (`tag`, `category`, …).
- Создать новый словарь — поле «+ новый словарь».
- В словаре — список терминов с label, slug, сортировкой, переводами.

Подробнее — см. пользовательский раздел [09. Словари](../users/09-taxonomies.md).

## Интеграция с формой ресурса

Чтобы редактор мог прикрепить термины на форме — добавь поле типа
`taxable` в `attributes` (если такой тип в пакете не реализован —
используй `select` с multi-select mode или кастомный рендерер).

Generic подход через `select` с custom-загрузкой:

```php
// На уровне ResourceController::update() — hook через observer
protected static function booted(): void
{
    static::saved(function (Article $a) {
        if ($tagsInput = request('tags_sync')) {
            $a->syncTerms('tag', explode(',', $tagsInput));
        }
    });
}
```

И в форме:

```vue
<input v-model="form.tags_sync" placeholder="наука,студентам,..." />
```

## Content API фильтры

Пакет автоматически понимает `?tag=` и `?category=` для моделей с
Taxable:

```
GET /api/content/articles?tag=interview,opinion
GET /api/content/articles?category=admissions
GET /api/content/articles?tag=science&category=students
```

В результате в каждом JSON-объекте статьи появляется ключ `terms`:

```json
{
    "id": 14,
    "title": "Новости науки",
    "terms": {
        "tag": [
            {"slug": "science", "label": "Наука"},
            {"slug": "interview", "label": "Интервью"}
        ],
        "category": [
            {"slug": "students", "label": "Студентам"}
        ]
    }
}
```

Лейблы локализованы по `?locale=` / `Accept-Language`.

## Создание словарей из сида

```php
use Meta\AdminCore\Models\TaxonomyTerm;

TaxonomyTerm::firstOrCreate(
    ['type' => 'category', 'slug' => 'admissions'],
    [
        'label' => 'Поступление',
        'label_translations' => [
            'ru' => 'Поступление',
            'kk' => 'Түсу',
            'en' => 'Admissions',
        ],
        'sort_order' => 10,
    ],
);
```

## Переводы лейблов

```php
$term = TaxonomyTerm::where('slug', 'science')->first();
$term->localizedLabel('kk'); // «Ғылым» (если заполнено)
$term->localizedLabel('en'); // «Science»
```

Fallback на `label` (основное поле) если перевод пустой.

## Ограничения v1

- **Нет иерархии.** Термины плоские. Если нужно «категория → подкатегория»,
  добавляй два отдельных словаря или используй свойство-parent в slug
  конвенцией (`education/higher`, `education/school`).
- **Нет reorder через drag-n-drop** в UI — только через поле
  `sort_order`.
- **Slug меняется — URL ломаются.** Админка хранит связь через `term_id`,
  но если у тебя публичный URL вида `/tag/{slug}`, старые ссылки
  отвалятся. Делай редиректы, если сменил.

## Удаление термина

При удалении termID по cascade удаляются все связи в
`taxonomy_term_model`. Модели (статьи, страницы) **не затрагиваются** —
просто теряют этот термин.

## Несколько типов на одной модели

Ничего не мешает:

```php
$article->syncTerms('tag', ['science', 'history']);
$article->syncTerms('category', ['admissions']);
$article->syncTerms('audience', ['students', 'parents']);
```

Все хранятся в одной таблице, различаются по `type`.

## Следующее

→ [13. `Webhookable` — webhooks](./13-webhookable.md)
