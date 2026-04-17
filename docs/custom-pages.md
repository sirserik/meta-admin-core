# Custom Vue pages

The default `Resource/Index.vue` and `Resource/Form.vue` cover standard
list + form CRUD. For specialised UI (galleries, related-record
editors, workflow actions, dashboards), replace them per resource.

## The override mechanism

In your entry (`resources/js/admin-spa.js`):

```js
const sitePages = import.meta.glob('./admin-spa/pages/**/*.vue');
const corePages = import.meta.glob('../../vendor/meta/admin-core/resources/js/pages/**/*.vue');
bootAdminCore({ sitePages, corePages, AdminLayout });
```

Resolution order in `bootAdminCore`:

```js
const loader = sitePages[sitePath] || corePages[corePath];
```

**Site pages win.** If you drop a file at
`resources/js/admin-spa/pages/{Page}/{Component}.vue`, it's used instead
of the package's version.

## Overriding a single resource

If you register a resource with `'page' => 'Articles'`:

```php
AdminCore::resource('articles', [
    'page' => 'Articles',     // use Articles/Index.vue + Articles/Form.vue
    // ...
]);
```

…and drop a file at `resources/js/admin-spa/pages/Articles/Index.vue`,
your file is used **only for the articles list**. Other resources still
get the generic `Resource/Index.vue`.

Default when `page` is omitted is `'Resource'`, so `Resource/Index.vue`
and `Resource/Form.vue` cover everything. A shared override for all
resources is as simple as placing files at
`resources/js/admin-spa/pages/Resource/Index.vue` — they supersede the
package ones.

## Inertia props passed to your page

### `Index.vue` receives:

```js
{
    title:      'Статьи',              // from config.label
    items:      { data: [...], meta: { ... } },   // LengthAwarePaginator
    resource:   'articles',            // the URL slug
    filters:    { search: '...' },     // query-string filters
    fields:     [...],                 // translatable field defs (for column rendering)
    attributes: [...],                 // plain attribute defs
}
```

Each row in `items.data` has:

- `id`, `url` (link to edit)
- `title` — auto-resolved from translatable `title`/`name` or physical column
- Every key in `plain` + `attributes` that exists on the model
- `image`, `image_url` (if `image_field` is set)

### `Form.vue` receives:

```js
{
    title:       'Новый: Статьи' | 'Редактирование',
    item:        null | { id, title: {ru,kk,en}, ... },
    fields:      [...],                 // translatable fields
    attributes:  [...],                 // plain attributes (with resolved options)
    locales:     ['ru', 'kk', 'en'],
    resource:    'articles',
    image_field: 'featured_image' | null,
    is_edit:     false | true,
}
```

## Submitting from custom forms

Use Inertia's `useForm`:

```vue
<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    title: { ru: '', kk: '', en: '' },
    slug: '',
    featured_image: null,
    is_published: false,
});

function submit() {
    const url = props.is_edit
        ? `/admin/${props.resource}/${props.item.id}`
        : `/admin/${props.resource}`;
    form.post(url, { forceFormData: !!props.image_field });
}
</script>
```

For edits, include the `_method: 'put'` field (as the stock Form does):

```js
const form = useForm({ _method: 'put', ... });
form.post(url);   // Inertia/Laravel treat it as PUT
```

## Using package components

Import from the `@admin-core` alias:

```vue
<script setup>
import PageHeader from '@admin-core/components/PageHeader.vue';
import LocaleTabs from '@admin-core/components/LocaleTabs.vue';
import TranslatableField from '@admin-core/components/TranslatableField.vue';
import SimpleField from '@admin-core/components/SimpleField.vue';
import RichTextEditor from '@admin-core/components/RichTextEditor.vue';
import Pagination from '@admin-core/components/Pagination.vue';
</script>
```

Each component accepts standard `v-model` + `:errors` props. See
`resources/js/components/*.vue` in the package source for the full
interface.

## Dropping into raw Laravel controllers

If the resource needs work that's beyond customising a Vue page — say,
a batch-import action, a multi-step workflow, heavy query joins — skip
`AdminCore::resource()` and:

1. Write your own controller returning `Inertia::render(...)`.
2. Register explicit routes before the package catch-all.
3. Keep the sidebar entry with `AdminCore::menuItem()`.

The package **doesn't require** you to put every resource through its
API. Mix and match — see the ETU and etec projects: ~14 resources go
through the generic API, ~7 keep specialised controllers (Leads,
RectorQuestions, Menu, Settings, PageBlocks, etc.).

## Example: custom list page

Suppose the articles list needs a grid layout with large image cards
instead of a table. With `'page' => 'Articles'` registered, drop:

```vue
<!-- resources/js/admin-spa/pages/Articles/Index.vue -->
<script setup>
import { Link, router } from '@inertiajs/vue3';
import PageHeader from '@admin-core/components/PageHeader.vue';
import Pagination from '@admin-core/components/Pagination.vue';

const props = defineProps({
    title: String,
    items: Object,
    resource: String,
    filters: Object,
});

function destroy(id) {
    if (!confirm('Удалить?')) return;
    router.delete(`/admin/${props.resource}/${id}`);
}
</script>

<template>
    <PageHeader :title="title">
        <template #actions>
            <Link :href="`/admin/${resource}/create`" class="btn-primary">Новая статья</Link>
        </template>
    </PageHeader>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div v-for="a in items.data" :key="a.id" class="card">
            <img v-if="a.image_url" :src="a.image_url" class="w-full h-40 object-cover">
            <div class="p-4">
                <h3>{{ a.title }}</h3>
                <div class="flex gap-2 mt-3">
                    <Link :href="a.url">Редактировать</Link>
                    <button @click="destroy(a.id)">Удалить</button>
                </div>
            </div>
        </div>
    </div>

    <Pagination :paginator="items" />
</template>
```

The server side is unchanged — `ResourceController::index()` still
handles pagination, search, presenting rows.

## Example: custom form page (gallery relation)

When a model has a `@HasMany ArticleImage::class` relation, the generic
form can't manage it. Override the form:

```vue
<!-- resources/js/admin-spa/pages/Articles/Form.vue -->
<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import GenericForm from '@admin-core/pages/Resource/Form.vue';
// import your own <GalleryManager :item="item" />

const props = defineProps(['title','item','fields','attributes','locales','image_field','resource','is_edit']);
</script>

<template>
    <GenericForm v-bind="$props" />
    <GalleryManager v-if="is_edit" :item="item" class="mt-6" />
</template>
```

Composing on top of `GenericForm` keeps the CRUD boilerplate and lets
you append a gallery manager below it.
