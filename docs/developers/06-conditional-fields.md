# 06. Условные поля — `visible_when`

Иногда поле должно появляться только при определённом значении другого
поля. Например:

- `external_url` нужно только если `link_type == 'external'`.
- `school_id` — только если `is_public_program == false`.
- `video_embed` — только если `content_type in ['video','mixed']`.

## Синтаксис

На любом элементе `fields` или `attributes` добавь ключ `visible_when`:

### Одно условие

```php
[
    'name' => 'external_url',
    'type' => 'url',
    'label' => 'URL',
    'visible_when' => ['field' => 'link_type', 'equals' => 'external'],
],
```

### Массив условий (AND)

```php
[
    'name' => 'confirmation_note',
    'type' => 'textarea',
    'label' => 'Комментарий к отмене',
    'visible_when' => [
        ['field' => 'status', 'equals' => 'cancelled'],
        ['field' => 'notify_email', 'not_empty' => true],
    ],
],
```

## Операторы

| Оператор | Значение | Пример |
|---|---|---|
| `equals` | Равно | `['field' => 'type', 'equals' => 'video']` |
| `not_equals` | Не равно | `['field' => 'type', 'not_equals' => 'draft']` |
| `in` | В массиве | `['field' => 'type', 'in' => ['video', 'audio']]` |
| `not_in` | Не в массиве | `['field' => 'status', 'not_in' => ['draft', 'archived']]` |
| `not_empty` | Не пусто | `['field' => 'email', 'not_empty' => true]` |
| `empty` | Пусто | `['field' => 'slug', 'empty' => true]` |

`not_empty` / `empty` игнорируют значение флага — важен только сам факт
оператора.

## Как это работает

Проверка делается **на клиенте** (Vue), в реальном времени. Поле,
которое скрыто, **не рендерится в DOM** — то есть вообще не существует
для пользователя. Когда условие становится истинным, поле появляется.

**При сохранении** скрытое поле может отправиться с пустым значением
(`null`). Если это критично — добавь серверную проверку (см. ниже).

## Локализованные поля

Если поле, на которое ссылается условие, переводимое (`{ru, kk, en}`),
сравнение идёт со значением в **активной локали**:

```php
// form.title = {ru: 'Привет', kk: '', en: 'Hello'}
// activeLocale = 'ru'
// тогда visible_when ищет в form.title.ru
```

Это удобно: если ты на вкладке `KK`, условие проверяется с казахским
значением. Переключился на `RU` — перепроверяется с русским.

## Примеры

### Type-dependent fields

```php
'attributes' => [
    ['name' => 'content_type', 'type' => 'select', 'label' => 'Тип', 'options' => [
        ['value' => 'text',  'label' => 'Текст'],
        ['value' => 'video', 'label' => 'Видео'],
        ['value' => 'embed', 'label' => 'Встраиваемый'],
    ]],

    ['name' => 'video_url',   'type' => 'url',  'label' => 'URL видео',
     'visible_when' => ['field' => 'content_type', 'equals' => 'video']],

    ['name' => 'embed_code',  'type' => 'textarea', 'label' => 'HTML-код',
     'visible_when' => ['field' => 'content_type', 'equals' => 'embed']],

    ['name' => 'text_source', 'type' => 'textarea', 'label' => 'Источник',
     'visible_when' => ['field' => 'content_type', 'equals' => 'text']],
],
```

### Dependent cascade

```php
'attributes' => [
    ['name' => 'school_id', 'type' => 'select', 'label' => 'Школа',
     'options' => fn() => School::toSelectOptions()],

    // Показать только если выбрана engineering school
    ['name' => 'specialization', 'type' => 'select', 'label' => 'Специализация',
     'visible_when' => ['field' => 'school_id', 'equals' => 1],
     'options' => fn() => Specialization::where('school_id', 1)->toSelectOptions()],
],
```

## Ограничения

- **Не каскадные зависимости**. Поле A скрыто → поле B, зависящее от A,
  не обновится. Нет реактивной пересборки цепочек.
- **Только на клиенте.** Серверная сторона видит все поля в запросе.
  Если тебе нужно отсечь поле на бэкенде — делай это в validation-правиле
  через closure-rule:

  ```php
  'rules' => Rule::requiredIf(fn () => request('link_type') === 'external'),
  ```

- **Только для `fields` / `attributes`** стандартного Resource-формата.
  Для кастомных форм (например, форма блока в `Blocks/Form.vue`) нужно
  писать свои v-if.

## Серверная валидация скрытых полей

По умолчанию скрытое поле может прилететь пустым. Если поле обязательное
**только** при определённом значении другого — делай условную валидацию:

```php
// В FormRequest
public function rules()
{
    return [
        'link_type'    => 'required|in:internal,external',
        'external_url' => 'required_if:link_type,external|nullable|url',
        'internal_page_id' => 'required_if:link_type,internal|nullable|exists:pages,id',
    ];
}
```

## Следующее

→ [07. BlockCatalog и DTO](./07-block-catalog.md)
