# 05. Типы полей и атрибутов

Справочник: какие бывают типы полей в `fields` / `attributes`, что
каждый рендерит, как валидируется.

## Общий формат

Каждый элемент массива — ассоциативный массив с обязательным `name` и
`type`:

```php
[
    'name'        => 'title',        // имя колонки / свойства
    'type'        => 'text',          // тип поля
    'label'       => 'Заголовок',     // подпись в форме
    'required'    => true,            // валидация required
    'placeholder' => 'Введите…',      // подсказка внутри
    'help'        => 'Подсказка под', // мелкий текст
    'group'       => 'Основное',      // секция формы
    'group_icon'  => 'fa-info-circle',// иконка секции
    'options'     => [...],           // для select/radio
    'default'     => '...',           // начальное значение
    'visible_when' => ['field' => 'type', 'equals' => 'external'],
    // опционально:
    'rules'   => 'max:255|starts_with:Hi',    // дополнительные Laravel rules
    'main'    => true,                        // attribute в основном блоке (не в sidebar)
    'section_label' => 'Meta',                // кастомный заголовок секции
],
```

## Поддерживаемые типы

### `text`

Однострочный `<input type="text">`.

```php
['name' => 'title', 'type' => 'text', 'label' => 'Заголовок'],
```

### `textarea`

Многострочный `<textarea>`. Высота 3 строки.

```php
['name' => 'excerpt', 'type' => 'textarea', 'label' => 'Краткое описание'],
```

### `editor`

Tiptap rich-text редактор (жирный, курсив, ссылки, списки, картинки,
таблицы).

```php
['name' => 'content', 'type' => 'editor', 'label' => 'Содержимое'],
```

В БД пишет HTML.

### `email`

`<input type="email">` с браузерной валидацией + Laravel `email`-rule.

```php
['name' => 'contact_email', 'type' => 'email', 'label' => 'Email'],
```

### `url`

`<input type="url">` + Laravel `url`-rule.

```php
['name' => 'website', 'type' => 'url', 'label' => 'Сайт'],
```

### `tel`

`<input type="tel">` — без валидации формата (слишком вариативно по
странам), но мобильная клавиатура подстраивается.

```php
['name' => 'phone', 'type' => 'tel', 'label' => 'Телефон'],
```

### `number`

`<input type="number">` + Laravel `numeric`.

```php
['name' => 'price',    'type' => 'number', 'label' => 'Цена'],
['name' => 'priority', 'type' => 'number', 'label' => 'Приоритет'],
```

### `date` / `datetime`

Дата или дата+время:

```php
['name' => 'published_at', 'type' => 'datetime', 'label' => 'Опубликовано'],
['name' => 'birth_date',   'type' => 'date',     'label' => 'Дата рождения'],
```

В БД — `timestamp` или `date`. Пакет формирует `d-m-Y H:i` для
datetime-input.

### `boolean`

Чекбокс. В БД — `boolean`.

```php
['name' => 'is_featured', 'type' => 'boolean', 'label' => 'В топе'],
```

### `select`

Выпадающий список с фиксированными вариантами:

```php
['name' => 'status', 'type' => 'select', 'label' => 'Статус', 'options' => [
    ['value' => 'draft',     'label' => 'Черновик'],
    ['value' => 'published', 'label' => 'Опубликовано'],
]],
```

Опции могут загружаться из callable:

```php
['name' => 'school_id', 'type' => 'select', 'label' => 'Школа',
 'options' => fn () => \App\Models\School::orderBy('name')->get()
                         ->map(fn($s) => ['value' => $s->id, 'label' => $s->name])
                         ->all()],
```

### `radio`

Радио-кнопки (те же опции, что у select, но визуально — кружки).

```php
['name' => 'visibility', 'type' => 'radio', 'label' => 'Видимость', 'options' => [
    ['value' => 'public',  'label' => 'Всем'],
    ['value' => 'private', 'label' => 'Только команде'],
]],
```

### `color`

Color picker. В БД — hex-строка (`#ff00aa`).

```php
['name' => 'gradient_from', 'type' => 'color', 'label' => 'Градиент от'],
```

### `icon`

Пикер FontAwesome-иконок с поиском. В БД — строка (`fa-graduation-cap`).

```php
['name' => 'icon', 'type' => 'icon', 'label' => 'Иконка'],
```

### `image`

Uploader + превью. Отличается от `image_field` ресурса тем, что это
отдельное поле в `data`/`attributes`.

```php
['name' => 'banner', 'type' => 'image', 'label' => 'Баннер'],
```

В БД — путь (string).

### `file`

Любой файл (PDF, DOC, ZIP). Uploader без превью.

```php
['name' => 'attachment', 'type' => 'file', 'label' => 'Документ'],
```

### `json`

Raw JSON-textarea с валидацией разметки. Используется редко — только
когда схема меняется часто, и визуальный редактор не успевает за ней.

```php
['name' => 'extra_config', 'type' => 'json', 'label' => 'Доп. настройки'],
```

### `relation` (планируется)

Выбор из связанной модели. Сейчас — через `select` + `options_from`.

## `fields` vs. `attributes`

Выбор зависит от того, **где** должно стоять поле на форме.

**`fields`** — «контент» (на главной части формы, широкие):

- title, subtitle, content
- excerpt, description
- бизнес-поля, которые надо писать долго

**`attributes`** — «метаданные» (сайдбар справа, узкие):

- slug
- статус публикации, категория
- даты
- булевы флаги
- SEO (meta_title, meta_description)

При рендере `fields` попадают в левую колонку (основной блок), `attributes`
по умолчанию — в правый сайдбар. Опция `'main' => true` на атрибуте
переносит его в основной блок:

```php
'attributes' => [
    ['name' => 'subtitle', 'type' => 'text', 'label' => 'Подзаголовок', 'main' => true],
],
```

## Валидация

Laravel-правила пакет частично генерирует автоматически по типу:
- `required` → через флаг `required: true`.
- `email` / `url` / `numeric` / `boolean` / `date` — от типа.

Свои правила — через ключ `rules`:

```php
['name' => 'slug', 'type' => 'text', 'label' => 'Slug',
 'rules' => 'required|alpha_dash|max:120|unique:articles,slug'],
```

Правило `unique:...,id` пакет автоматически дополняет ID текущей
записи при редактировании (через `rule->ignore($model)`).

## Группировка

Ключ `group` объединяет поля в секцию:

```php
'fields' => [
    ['name' => 'title',   'type' => 'text',   'label' => 'Заголовок', 'group' => 'Основное'],
    ['name' => 'content', 'type' => 'editor', 'label' => 'Содержимое', 'group' => 'Основное'],
],
'attributes' => [
    ['name' => 'slug',       'type' => 'text',    'label' => 'Slug',       'group' => 'URL'],
    ['name' => 'canonical',  'type' => 'url',     'label' => 'Canonical',  'group' => 'URL'],
    ['name' => 'meta_title', 'type' => 'text',    'label' => 'Meta title', 'group' => 'SEO', 'group_icon' => 'fa-search'],
    ['name' => 'meta_desc',  'type' => 'textarea','label' => 'Meta description','group' => 'SEO'],
],
```

Форма разобьётся на карточки по group-именам.

## Переводимость

Если поле названо в `translatable` ресурса — оно **автоматически**
становится переводимым (рендерится с вкладками `ru/kk/en`):

```php
AdminCore::resource('articles', [
    'translatable' => ['title', 'excerpt', 'content'],   // ← эти три переводимы
    'fields' => [
        ['name' => 'title',   'type' => 'text',   …],   // ← рендерится с вкладками
        ['name' => 'excerpt', 'type' => 'textarea',…],  // ← тоже
        ['name' => 'content', 'type' => 'editor', …],   // ← тоже
    ],
    'attributes' => [
        ['name' => 'slug',    'type' => 'text',   …],   // ← slug НЕ переводимый
    ],
]);
```

## Условная видимость

См. [06. Conditional fields](./06-conditional-fields.md).

```php
['name' => 'external_url', 'type' => 'url', 'label' => 'URL',
 'visible_when' => ['field' => 'link_type', 'equals' => 'external']],
```

## Следующее

→ [06. Условные поля](./06-conditional-fields.md)
