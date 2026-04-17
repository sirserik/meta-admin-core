# Dynamic FK selects

A common pattern: a `Teacher` has a `school_id` column, and you want the
admin form to show a dropdown of all Schools. The package supports this
via **closure-based `options`** that evaluate per request.

## Static options

When the option list is fixed, use a plain array:

```php
['name' => 'level', 'type' => 'select', 'label' => 'Уровень',
    'options' => [
        ['value' => 'bachelor', 'label' => 'Бакалавриат'],
        ['value' => 'master',   'label' => 'Магистратура'],
        ['value' => 'doctoral', 'label' => 'Докторантура'],
    ],
]
```

## Closure options

When the option list depends on DB state, pass a closure. It's called on
every request that renders the form, so the list reflects current data.

```php
['name' => 'school_id', 'type' => 'select', 'label' => 'Школа', 'required' => true,
    'options' => fn () => \App\Models\School::orderBy('name')
        ->get(['id', 'name'])
        ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])
        ->all(),
]
```

Resolution happens in `ResourceController::resolveAttributeOptions()`
— it calls `call_user_func($options)` for any attribute of type
`select` whose `options` is callable.

## Storing references to helpers

Hoist the closure if you use the same FK list in multiple resources:

```php
protected function registerAdminResources(): void
{
    $schoolsOptions = fn () => \App\Models\School::orderBy('name')
        ->get(['id', 'name'])
        ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])
        ->all();

    AdminCore::resource('teachers', [
        // ...
        'attributes' => [
            ['name' => 'school_id', 'type' => 'select', 'options' => $schoolsOptions, 'required' => true],
        ],
    ]);

    AdminCore::resource('management', [
        // ...
        'attributes' => [
            ['name' => 'school_id', 'type' => 'select', 'options' => $schoolsOptions],
        ],
    ]);

    AdminCore::resource('programs', [
        // ...
        'attributes' => [
            ['name' => 'school_id', 'type' => 'select', 'options' => $schoolsOptions, 'required' => true],
        ],
    ]);
}
```

## Translated labels

Often FK labels should respect the current admin UI locale. With Spatie
Translatable:

```php
fn () => \App\Models\School::orderBy('order_position')
    ->get(['id'])
    ->map(fn ($s) => [
        'value' => $s->id,
        'label' => $s->translate('name', app()->getLocale()) ?? $s->getTranslation('name', 'ru'),
    ])
    ->all();
```

With a custom `translate()` method:

```php
fn () => \App\Models\School::orderBy('name')
    ->get(['id', 'name'])
    ->map(fn ($s) => [
        'value' => $s->id,
        'label' => $s->translate('name', 'ru') ?? $s->name,
    ])
    ->all();
```

## Rich labels

Since `label` is free-form, decorate it:

```php
fn () => \App\Models\SdgGoal::orderBy('number')->get(['id', 'number'])
    ->map(fn ($g) => [
        'value' => $g->id,
        'label' => 'Цель ' . $g->number . ' — ' . ($g->translate('title', 'ru') ?? ''),
    ])
    ->all();
```

```
Цель 1 — Ликвидация нищеты
Цель 2 — Ликвидация голода
Цель 3 — Хорошее здоровье и благополучие
…
```

## Validation

The rule generator calls the closure once more when building `in:…`:

```
nullable|in:1,2,3,4,5
```

So invalid IDs are rejected even if the form is tampered with
client-side.

## Large lists

If the FK table has thousands of rows, rendering a 5000-option `<select>`
is slow. Options:

- **Limit with scope:** `School::active()->get(['id', 'name'])`.
- **Switch to a different input:** override the Vue page and use a
  searchable combobox (native `<datalist>`, or something like Vue
  Multiselect). See [custom pages](custom-pages.md).
- **Free-text field:** if you just need user input, switch `type` to
  `text` and accept string IDs.

## Caching

The closure runs every request. If evaluation is expensive (heavy query
or external call), cache it:

```php
fn () => cache()->remember('admin:schools:options', 60, function () {
    return \App\Models\School::orderBy('name')->get(['id', 'name'])
        ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])
        ->all();
});
```

Remember to invalidate the cache when School data changes (model observer).

## Multiple values (multi-select)

Not supported out of the box by `SimpleField.vue`. For a multi-select
field (pivot table, checkbox group), override the Vue form page.
