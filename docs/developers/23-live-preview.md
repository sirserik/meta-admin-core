# 23. Live preview iframe

Встроенный **сплит-скрин** для редактирования блоков: справа iframe
с публичной страницей, обновляется на каждое сохранение формы.

## Где работает

Встроено в `Blocks/Form.vue`. На других формах (generic Resource) — не
автоматически, нужно руками добавить.

## Как работает

1. Кнопка «Предпросмотр» переключает `previewEnabled` (сохраняется в
   localStorage `admin-core:preview-split`).
2. URL iframe рассчитывается по `page_name` блока:
   - `home` → `/`.
   - остальное → `/{slug}`.
   - `header`, `footer`, `menu` — preview скрывается (нет соответствующей
     страницы сайта).
3. На `form.wasSuccessful` — cache-buster меняется (`?_preview={ts}`),
   iframe перезагружается.

## Ключевые куски кода

```js
const previewEnabled = ref(false);
try { previewEnabled.value = localStorage.getItem('admin-core:preview-split') === '1'; } catch {}
watch(previewEnabled, (v) => {
    try { localStorage.setItem('admin-core:preview-split', v ? '1' : '0'); } catch {}
});

const previewBuster = ref(0);
const previewUrl = computed(() => {
    const page = form.page_name || props.item.page_name;
    if (!page || page === 'header' || page === 'footer' || page === 'menu') return '';
    const base = page === 'home' ? '/' : ('/' + page);
    return base + '?_preview=' + (previewBuster.value || 0);
});

watch(() => form.wasSuccessful, (ok) => { if (ok) previewBuster.value = Date.now(); });
```

## Почему не «по-настоящему live»

«По-настоящему live» = iframe перерисовывается **на каждый keystroke**
без reload. Для этого нужно:

1. Описать каждый блок в **двух местах**: Blade для прода + Vue для
   preview. Копия, которая разъезжается.
2. Или **JS-рендер в iframe**: принимать payload через `postMessage`,
   рендерить тем же Vue, что и админка.

Оба варианта нарушают принцип «Blade — источник истины для
публичного сайта». Пакет выбрал компромисс: **save-reload preview**.
95% value за 20% effort.

Если нужна настоящая live preview — это работа консьюмер-приложения,
не пакета.

## Расширение на другие формы

Возьми блок template из `Blocks/Form.vue` и встрой в свой компонент:

```vue
<template>
    <div class="grid grid-cols-[1fr_1fr] gap-4">
        <!-- Левая колонка: твоя форма -->
        <form @submit.prevent="submit">...</form>

        <!-- Правая колонка: iframe -->
        <div v-if="previewUrl" class="sticky top-16">
            <iframe :src="previewUrl" class="w-full h-[70vh] border rounded-lg"></iframe>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';

const previewUrl = computed(() => `/articles/${props.item.slug}?_preview=${buster.value}`);
const buster = ref(0);
watch(() => form.wasSuccessful, (ok) => { if (ok) buster.value = Date.now(); });
</script>
```

## Для драфтов preview

Текущая реализация показывает **опубликованное** состояние страницы в
iframe. То есть если блок в `status='draft'`, он вообще не показывается
на публичном сайте, и preview пустой.

Workaround: preview-режим должен понять специальный query-параметр,
например `?_preview=draft`, и показывать черновики. Логика:

```php
// routes/web.php (consumer)
Route::get('/{slug}', function (string $slug) {
    $blocks = request()->query('_preview') === 'draft'
        ? PageBlock::where('page_name', $slug)->orderBy('sort_order')->get()
        : PageBlock::published()->forPage($slug)->orderBy('sort_order')->get();
    return view('pages.dynamic', compact('blocks'));
});
```

Тогда preview-URL надо формировать как `/{slug}?_preview=draft`, а не
cache-buster. Меняй Vue:

```js
const previewUrl = computed(() => {
    const base = form.page_name === 'home' ? '/' : `/${form.page_name}`;
    return base + '?_preview=draft';
});
```

И iframe перезагружай принудительно:

```js
watch(() => form.wasSuccessful, () => iframeEl.value?.contentWindow.location.reload());
```

## CORS и cookies

iframe и админка — на одном домене (обычно), так что cookies/auth
работают. Если публичная часть на другом домене — iframe не увидит
авторизации и покажет публичную версию. Плюс получишь CORS-лимит на
некоторые ресурсы.

Решение — разместить publics и admin на одном домене, либо настроить
cross-origin cookies с `SameSite=None; Secure`.

## Следующее

→ [24. Обновление пакета](./24-upgrading.md)
