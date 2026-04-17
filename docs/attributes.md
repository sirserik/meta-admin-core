# Attribute types

**Attributes** are the plain (non-translatable) scalar fields rendered
in the **sidebar** of the form. They map 1:1 to columns on the model's
own table. Each attribute declares a `type` that drives three things:

1. The HTML input used in `SimpleField.vue`
2. The Laravel validation rules generated server-side
3. The JSON shape sent to the form on edit (dates get formatted, for
   example)

## Entry schema

```php
[
    'name'        => 'slug',              // required — column name
    'type'        => 'text',              // required — see types below
    'label'       => 'Slug',              // rendered above the input
    'required'    => false,               // red asterisk + required rule
    'unique'      => false,               // adds unique:{table},{col},{id}
    'max'         => 500,                 // string max length (only for `text`)
    'placeholder' => 'автогенерация',     // input placeholder
    'help'        => '…',                 // subtle grey hint below the input
    'options'     => [...] | fn() => […], // only for `select`
]
```

Any unknown keys are silently ignored, so you can attach custom metadata
for your own extensions.

## Type reference

### `text`

| | |
|---|---|
| HTML           | `<input type="text">` |
| Validation     | `nullable\|string\|max:{max}` (default `max` = 500) |
| Modifiers      | `max`, `placeholder`, `unique` |

```php
['name' => 'slug', 'type' => 'text', 'label' => 'Slug', 'max' => 255, 'unique' => true]
```

### `textarea`

| | |
|---|---|
| HTML           | `<textarea rows="4">` |
| Validation     | `nullable\|string\|max:{max}` |
| Modifiers      | `placeholder` |

Use for plain multi-line text that should **not** be translatable (e.g.
an internal note column). If the field should be rich HTML, use `editor`
under `fields` instead.

### `email`

| | |
|---|---|
| HTML           | `<input type="email">` |
| Validation     | `nullable\|email\|max:255` |

Browser does basic format check before the request is sent.

### `url`

| | |
|---|---|
| HTML           | `<input type="url">` |
| Validation     | `nullable\|url\|max:2000` |
| Modifiers      | `max` |

### `number`

| | |
|---|---|
| HTML           | `<input type="number" inputmode="numeric">` |
| Validation     | `nullable\|numeric` |

Empty input → `null` (not `0`).

### `date`

| | |
|---|---|
| HTML           | `<input type="date">` |
| Validation     | `nullable\|date` |
| Form value     | `Y-m-d` (formatted server-side for edits) |

Works with `DateTime` and `Carbon` attributes on the model — the
`presentForm()` converter formats them when rendering the edit form.

### `datetime-local`

| | |
|---|---|
| HTML           | `<input type="datetime-local">` |
| Validation     | `nullable\|date` |
| Form value     | `Y-m-d\TH:i` |

### `select`

| | |
|---|---|
| HTML           | `<select>` with options |
| Validation     | `nullable\|in:{value1,value2,...}` |
| Modifiers      | `options`, `required` |

```php
['name' => 'status', 'type' => 'select', 'label' => 'Статус',
    'options' => [
        ['value' => 'draft',     'label' => 'Черновик'],
        ['value' => 'published', 'label' => 'Опубликовано'],
        ['value' => 'archived',  'label' => 'В архиве'],
    ],
]
```

If the attribute is **not required**, an empty `—` option is prepended
and the value `''` is accepted (stored as `null`).

**Closure-based options** for foreign-key selects: see
[select-options](select-options.md).

### `boolean`

| | |
|---|---|
| HTML           | `<input type="checkbox">` |
| Validation     | `nullable\|boolean` |

Stored as `bool` on the model. The controller uses `$request->boolean()`
to handle `"on"`, `"true"`, `1` etc.

Boolean attributes are **always optional** at the validator level —
unchecked means `false`, not "missing".

### `color`

| | |
|---|---|
| HTML           | Native color picker **plus** a text input in a flex row |
| Validation     | `nullable\|string` |
| Storage        | Hex string, e.g. `#C41E3A` |

The text input lets you type `rgb()` / `var(--x)` / CSS expressions the
picker can't produce.

## Modifiers

### `required`

Adds red asterisk to the label, HTML `required` on the input, and
`required` instead of `nullable` to the validation rule.

### `unique`

Adds `unique:{table},{name}` to the rule. On update, the current row's
id is appended to ignore it:

```
unique:contacts,key           — on create
unique:contacts,key,5         — on update (id 5)
```

### `max`

Overrides the default 500/2000 cap on `text`/`url`:

```php
['name' => 'meta_description', 'type' => 'text', 'max' => 160]
```

### `placeholder`, `help`

Display-only. `placeholder` goes to the native HTML attribute; `help`
renders as grey text below the input.

## Storage behavior

All attributes are written back via `$model->{$name} = $value`. No
fillable filter applies — make sure the column is in your `$fillable`
(or `$guarded = []`).

Empty string → `null` is the default:

```php
$m->{$name} = $data[$name] !== '' ? $data[$name] : null;
```

Booleans are always coerced with `$request->boolean()`.

## When NOT to use attributes

Attributes are for **scalar columns**. Use something else when:

- **The field is translatable** → put it under `translatable` + `fields`.
- **The field is a rich-text block** → use `editor` under `fields`.
- **The value is a collection** (images gallery, tags, related rows) →
  override the Vue form with a custom page.
- **Complex validation** (e.g. conditional, cross-field) → override
  `store()` / `update()` with a custom controller.

## Examples

### Datetime + status combo

```php
'attributes' => [
    ['name' => 'status',       'type' => 'select', 'label' => 'Статус',
        'options' => [
            ['value' => 'draft',     'label' => 'Черновик'],
            ['value' => 'published', 'label' => 'Опубликовано'],
        ],
    ],
    ['name' => 'published_at', 'type' => 'datetime-local', 'label' => 'Дата публикации'],
],
```

### External links

```php
'attributes' => [
    ['name' => 'document_url', 'type' => 'url', 'label' => 'PDF документ', 'max' => 2000],
    ['name' => 'video_url',    'type' => 'url', 'label' => 'YouTube / Vimeo'],
],
```

### Number with range

```php
['name' => 'priority', 'type' => 'number', 'label' => 'Приоритет (1–4)', 'help' => '1 — самый высокий'],
```

The package only validates `numeric` — if you need `min`/`max`, either
validate client-side, or add a constraint check in the model's `saving`
hook.
