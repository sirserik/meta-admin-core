# 22. Autosave черновиков — `useDraftAutosave` composable

Vue composable, который сохраняет состояние Inertia-формы в
`localStorage` и восстанавливает при reload.

## Использование

```vue
<script setup>
import { useForm } from '@inertiajs/vue3';
import { useDraftAutosave } from '@admin-core/composables/useDraftAutosave.js';

const props = defineProps({ item: Object, isEdit: Boolean });

const form = useForm({
    title:   props.item.title ?? '',
    content: props.item.content ?? '',
    // …
});

const {
    savedAt,
    restorePrompt,
    discard,
    acceptRestore,
    declineRestore,
} = useDraftAutosave(form, {
    key: `article:${props.item.id ?? 'new'}`,
    debounce: 1500,
    reference_updated_at: props.item.updated_at ?? null,
});
</script>

<template>
    <div v-if="restorePrompt" class="restore-banner">
        Есть несохранённая версия от
        {{ new Date(restorePrompt.savedAt).toLocaleString() }}.
        <button @click="acceptRestore">Восстановить</button>
        <button @click="declineRestore">Отбросить</button>
    </div>

    <form @submit.prevent="form.post('/admin/articles')">
        <input v-model="form.title">
        <textarea v-model="form.content"></textarea>
        <button>Сохранить</button>
    </form>

    <small v-if="savedAt">Автосохранено в {{ new Date(savedAt).toLocaleTimeString() }}</small>
</template>
```

## Опции

- `key` *(required)* — уникальный идентификатор. Обычно `{тип}:{id}`.
  Для нового (без id) — `'new'` или с nonce.
- `debounce` *(default 1500)* — миллисекунд между последним изменением
  и записью в localStorage.
- `reference_updated_at` *(default null)* — timestamp последнего
  сохранения с сервера. Нужен, чтобы НЕ предлагать восстановление,
  если сохранение уже прошло через другой девайс.

## Возвращаемый API

```
{
    savedAt:       Ref<string | null>      // когда последний раз записалось
    restorePrompt: Ref<{savedAt, data} | null>  // объект для UI-баннера
    discard():     void                    // сбросить черновик (не показывать)
    acceptRestore(): void                  // применить данные из черновика
    declineRestore(): void                 // = discard()
}
```

## Что сохраняется

`form.data()` — вся полезная нагрузка формы. В `localStorage` ключ:

```
admin-core:draft:{key}
```

Значение:

```json
{
    "data":    { "title": "…", "content": "…" },
    "savedAt": "2026-04-19T14:23:45.123Z"
}
```

## Когда чистится

- `form.wasSuccessful` становится `true` (т.е. `form.post()/put()` успешно
  завершился) → `discard()`.
- `declineRestore()` / `discard()` вручную.
- Stale-detection: если `reference_updated_at > savedAt` (т.е. кто-то
  другой сохранил свежее) → `discard()` при mount, промпт не показывается.

## Защита от пропуска

Composable `skipNext` при `acceptRestore()` — следующее изменение формы
не перезаписывает восстановленный черновик сразу. Предотвращает race
между fill и debounced save.

## Что НЕ сохраняется

- **File-inputs.** `useForm` держит File-объекты, они не сериализуются
  в JSON. При восстановлении поле `<input type="file">` остаётся пустым.
  Придётся перезагрузить файл.
- **FormData с blob'ами.**

## Лимиты localStorage

~5 МБ на домен. Если форма огромная (рич-текст на мегабайты) — может
переполнить. Composable глушит `QuotaExceededError` — черновик просто
не сохранится, без ошибок наружу.

## Opt-out

Если в этой конкретной форме autosave не нужен — не вызывай composable.
Либо условно:

```js
if (props.isEdit) {
    useDraftAutosave(form, { key: `…:${props.item.id}` });
}
```

## Для других ресурсов

Сейчас подключён только в `Blocks/Form.vue`. Чтобы в generic
`Resource/Form.vue` тоже работало — импортируй composable внутри этого
компонента и обернись:

```vue
<script setup>
import { useDraftAutosave } from '@admin-core/composables/useDraftAutosave.js';

const draftKey = computed(() =>
    `${props.resource}:${props.item._route_key ?? props.item.id ?? 'new'}`,
);

const { savedAt, restorePrompt, acceptRestore, declineRestore }
    = useDraftAutosave(form, { key: draftKey.value });
</script>
```

Это не в пакете по умолчанию — чтобы не форсировать на простых формах,
где autosave лишний.

## Мульти-девайс / мульти-вкладка

Composable привязан к одному браузеру + одному ключу:
- Открыл форму в двух вкладках одновременно → оба пишут в один ключ,
  последнее сохранение выиграет.
- На другом компьютере черновик не увидишь (он в localStorage).

Для real cross-device drafts нужен **server-side** вариант (таблица
`drafts`). Это оставлено на будущую версию.

## Следующее

→ [23. Live preview iframe](./23-live-preview.md)
