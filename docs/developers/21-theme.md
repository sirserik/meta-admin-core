# 21. Темизация (design tokens)

Цветовая палитра + типографика + отступы сайта хранятся как **именованные
токены** в `config/theme.php`, сохраняются в `settings` таблице, и
пробрасываются на фронт через CSS-переменные.

## Конфиг по умолчанию

```php
// config/theme.php
return [
    'tokens' => [
        'color_primary'        => '#C41E3A',
        'color_primary_hover'  => '#9f1730',
        'color_primary_active' => '#7a1124',
        'color_accent'         => '#2563EB',
        'color_danger'         => '#DC2626',
        'color_warning'        => '#F59E0B',
        'color_success'        => '#10B981',
        'color_foreground'     => '#111827',
        'color_background'     => '#FFFFFF',
        'color_foreground_dark'=> '#F3F4F6',
        'color_background_dark'=> '#111827',

        'radius_sm' => '0.25rem',
        'radius_md' => '0.5rem',
        'radius_lg' => '1rem',

        // …
    ],
];
```

## Публикация

```bash
php artisan vendor:publish --tag=admin-core-theme-config
```

## Runtime-override через settings

`/admin/theme` (UI) сохраняет изменённые значения в `settings`:

```
settings
 - key: 'theme.color_primary', value: '#2563EB'
```

`ThemeService` при рендере layout'а берёт:
1. Из settings (если есть).
2. Иначе — из config файла.

Результат рендерится в `<style>` top-of-page:

```html
<style>
:root {
    --color-primary: #2563EB;
    --color-accent: #F59E0B;
    …
}
</style>
```

## Использование в CSS

### Твои стили

```css
.hero {
    background: var(--color-primary);
    color: var(--color-background);
}

.btn {
    background: var(--color-primary);
    border-radius: var(--radius-md);
}

.btn:hover {
    background: var(--color-primary-hover);
}
```

### Tailwind v4 с custom properties

```css
@layer utilities {
    .bg-primary { background-color: var(--color-primary); }
}
```

Или через Tailwind 4 `@theme`:

```css
@theme {
    --color-primary: var(--color-primary);
    --color-primary-hover: var(--color-primary-hover);
}
```

Теперь `bg-primary`, `text-primary`, `hover:bg-primary-hover` — работают.

## API

```php
use Meta\AdminCore\Services\ThemeService;

app(ThemeService::class)->all();                       // array of tokens
app(ThemeService::class)->get('color_primary');        // one value
app(ThemeService::class)->set('color_primary', '#F00'); // persist into settings
app(ThemeService::class)->reset();                     // обнулить overrides
```

## Темный режим

Пакет поддерживает dark-mode через CSS class `dark` на `<html>`.
Токены `color_foreground_dark` / `color_background_dark` подтягиваются
автоматом:

```css
html.dark {
    --color-foreground: var(--color-foreground-dark);
    --color-background: var(--color-background-dark);
}
```

Переключатель темы в шапке админки сохраняет выбор в `localStorage` и
приписывает class.

## Пресеты

В пакете заготовлены:
- `etec-red` — основной красный ETEC.
- `meta-blue` — синий META University.
- `sdg-green` — для SDG-фич.

Можно расширить — добавь свой в `config/theme.php`:

```php
'presets' => [
    'etec-red' => [
        'color_primary' => '#C41E3A',
        // …
    ],
    'my-brand' => [
        'color_primary' => '#7C3AED',
        // …
    ],
],
```

В UI `/admin/theme` появятся кнопки «Применить пресет».

## Экспорт в CSS

Для consumer-приложения, где нужен SSR, можно сгенерировать CSS-файл:

```bash
php artisan admin-core:theme:export --out=resources/css/theme.css
```

(Команда не ship'ится в core на v0.43 — напиши свою, если надо.)

## Следующее

→ [22. useDraftAutosave](./22-drafts.md)
