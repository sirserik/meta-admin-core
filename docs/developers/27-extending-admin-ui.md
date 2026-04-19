# 27. Расширение Vue-интерфейса

Как добавить свою Inertia-страницу в админку, или заменить существующую.

## Где живут Vue-страницы

- Пакетные: `vendor/meta/admin-core/resources/js/pages/**/*.vue`.
- Консьюмерские: `resources/js/admin-spa/pages/**/*.vue` (если ты сделал
  такой каталог).

Vite-bootstrap в consumer-приложении:

```js
// resources/js/admin-spa.js

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';

const corePages  = import.meta.glob('../../vendor/meta/admin-core/resources/js/pages/**/*.vue',
                                     { eager: true });
const localPages = import.meta.glob('./admin-spa/pages/**/*.vue', { eager: true });

createInertiaApp({
    resolve: (name) => {
        // Local overrides first
        const local = localPages[`./admin-spa/pages/${name}.vue`];
        if (local) return local.default;

        const core = corePages[`../../vendor/meta/admin-core/resources/js/pages/${name}.vue`];
        return core?.default;
    },
    setup: ({ el, App, props, plugin }) =>
        createApp({ render: () => h(App, props) }).use(plugin).mount(el),
});
```

## Свой компонент

Допустим, нужен `/admin/reports/weekly`.

### 1. Backend — контроллер и роут

```php
// routes/web.php
Route::middleware(['auth','verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/reports/weekly', [ReportsController::class, 'weekly'])
        ->name('reports.weekly');
});
```

```php
namespace App\Http\Controllers\Admin;

use Inertia\Inertia;

class ReportsController
{
    public function weekly()
    {
        return Inertia::render('Reports/Weekly', [
            'title' => 'Еженедельный отчёт',
            'stats' => [...],
        ]);
    }
}
```

### 2. Frontend — Vue-компонент

`resources/js/admin-spa/pages/Reports/Weekly.vue`:

```vue
<script setup>
import AdminLayout from '@admin-core/layouts/AdminLayout.vue';

const props = defineProps({ title: String, stats: Array });
defineOptions({ layout: AdminLayout });
</script>

<template>
    <div class="max-w-5xl mx-auto p-6">
        <h1 class="text-2xl font-bold">{{ title }}</h1>
        <div class="grid grid-cols-3 gap-4 mt-6">
            <div v-for="s in stats" :key="s.label" class="bg-white p-4 rounded-xl">
                <div class="text-3xl font-bold">{{ s.value }}</div>
                <div class="text-sm text-gray-500">{{ s.label }}</div>
            </div>
        </div>
    </div>
</template>
```

### 3. Пункт меню

В `AdminResourceServiceProvider::boot()`:

```php
AdminCore::menuItem('Отчёты', '/admin/reports/weekly', 'fa-chart-line', 'Аналитика', 50);
```

Или группа:

```php
AdminCore::menuGroup('Аналитика', [
    ['label' => 'Еженедельный', 'href' => '/admin/reports/weekly', 'icon' => 'fa-chart-line'],
    ['label' => 'Помесячный',   'href' => '/admin/reports/monthly','icon' => 'fa-chart-bar'],
]);
```

### 4. Пересобрать

```bash
npm run build
```

## Override пакетной страницы

Скажем, хочешь свой `Dashboard.vue`:

```
resources/js/admin-spa/pages/Dashboard.vue
```

Vite увидит local → загрузит его вместо пакетного (см. resolve-logic
выше).

## Использовать компоненты пакета

```vue
<script setup>
import SimpleField from '@admin-core/components/SimpleField.vue';
import TranslatableField from '@admin-core/components/TranslatableField.vue';
import PageHeader from '@admin-core/components/PageHeader.vue';
import LocaleTabs from '@admin-core/components/LocaleTabs.vue';
import FocalPointPicker from '@admin-core/components/FocalPointPicker.vue';
import RichTextEditor from '@admin-core/components/RichTextEditor.vue';
import Pagination from '@admin-core/components/Pagination.vue';
</script>
```

## Использовать composable пакета

```js
import { useDraftAutosave } from '@admin-core/composables/useDraftAutosave.js';
```

## Shared props

Все страницы получают от Inertia:

```js
const page = usePage();
page.props.auth.user                 // текущий юзер
page.props.brand                     // { name, subtitle, color, logo_char }
page.props.navigation                // готовое дерево сайдбара
page.props.flash                     // { success, error } от back-end
page.props.activeLocale              // 'ru'|'kk'|'en'
```

## Custom layouts

Для нестандартных экранов можно обойтись без `AdminLayout`:

```vue
<script setup>
// Нет defineOptions({ layout }) → страница рендерится «голой»
</script>

<template>
    <div class="fullscreen-report">...</div>
</template>
```

Или сделай свой `@/layouts/ReportLayout.vue` и подключи через `defineOptions`.

## Стили и Tailwind

Пакет предполагает Tailwind 3.x / 4.x в consumer'е. Базовые классы
(`bg-white`, `dark:bg-gray-800`, `rounded-xl`, …) — стандартный
Tailwind. Специфических пакетных классов нет.

Dark-mode — class-based (`<html class="dark">`).

## Расширение `Blocks/Form.vue`

Эта страница сложная (pick block type, data-editor, preview). Override
через local page работает, но копировать 600 строк вручную — больно.

Альтернатива: **wrap-around**. Сделай свой local компонент, импортируй
пакетный, расшифруй через slot'ы. Или patch-forward стратегия:

```js
// resources/js/admin-spa/pages/Blocks/Form.vue
import CoreForm from '../../../../../vendor/meta/admin-core/resources/js/pages/Blocks/Form.vue';
export default CoreForm;
```

Используй как «отправная точка» + добавляй свои изменения.

## Компоненты пакета — список

- `AdminLayout` — основной макет (sidebar + header + content).
- `PageHeader` — заголовок + actions справа.
- `CommandPalette` — Cmd+K поиск (сам регистрируется в AdminLayout).
- `LocaleTabs` — переключатель ru/kk/en (v-model binding).
- `TranslatableField` — поле с вкладками.
- `SimpleField` — generic поле (text/select/date/boolean/…).
- `BlockDataEditor` — визуальный редактор `data` JSON.
- `FocalPointPicker` — клик-по-картинке-выбрать-фокус.
- `FlashToasts` — всплывающие уведомления из `flash.success` /
  `flash.error`.
- `IconPicker` — выбор FontAwesome-иконки.
- `RichTextEditor` — обёртка над Tiptap.
- `MenuRow`, `Pagination`, `TranslatableField` — служебные.

## Следующее

→ [28. Тестирование](./28-testing.md)
