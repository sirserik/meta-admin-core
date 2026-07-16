<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '../../components/PageHeader.vue';
import LocaleTabs from '../../components/LocaleTabs.vue';
import TranslatableField from '../../components/TranslatableField.vue';
import SimpleField from '../../components/SimpleField.vue';

const props = defineProps({
    title: String,
    item: Object,
    fields:     { type: Array, default: () => [] },
    attributes: { type: Array, default: () => [] },
    actions:    { type: Array, default: () => [] },
    locales:    { type: Array, default: () => ['ru','kk','en'] },
    image_field: { type: String, default: null },
    resource: String,
    is_edit: Boolean,
});

const activeLocale = ref('ru');

const initial = () => {
    const base = { _method: props.is_edit ? 'put' : 'post' };
    for (const f of props.fields) {
        const v = props.item?.[f.name];
        if (v && typeof v === 'object') {
            base[f.name] = { ru: v.ru ?? '', kk: v.kk ?? '', en: v.en ?? '' };
        } else if (v != null && v !== '') {
            base[f.name] = { ru: String(v), kk: '', en: '' };
        } else {
            base[f.name] = { ru: '', kk: '', en: '' };
        }
    }
    for (const a of props.attributes) {
        base[a.name] = props.item?.[a.name] ?? (a.type === 'boolean' ? false : '');
    }
    if (props.image_field) {
        base[props.image_field] = null;
        base['remove_' + props.image_field] = false;
    }
    return base;
};

const form = useForm(initial());

const imagePreview = ref(null);
const existingImageUrl = ref(props.item?.[props.image_field + '_url'] ?? null);

function onImageChange(e) {
    const f = e.target.files?.[0];
    form[props.image_field] = f || null;
    form['remove_' + props.image_field] = false;
    if (f) { const r = new FileReader(); r.onload = ev => (imagePreview.value = ev.target.result); r.readAsDataURL(f); }
    else imagePreview.value = null;
}
function removeImage() {
    form[props.image_field] = null;
    form['remove_' + props.image_field] = true;
    imagePreview.value = null;
    existingImageUrl.value = null;
}

// ----- Поворот изображения на 90° -----
// Новый (ещё не загруженный) файл крутим на canvas до отправки;
// уже сохранённый — на сервере, на месте (путь не меняется).
const rotating = ref(false);
async function rotateImage() {
    if (rotating.value) return;
    if (form[props.image_field] instanceof File) {
        rotating.value = true;
        try {
            const rotated = await rotateFileClockwise(form[props.image_field]);
            form[props.image_field] = rotated;
            const r = new FileReader();
            r.onload = ev => (imagePreview.value = ev.target.result);
            r.readAsDataURL(rotated);
        } finally { rotating.value = false; }
        return;
    }
    if (!existingImageUrl.value) return;
    rotating.value = true;
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch('/admin/upload/rotate-image', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ path: existingImageUrl.value, degrees: 90 }),
        });
        const data = await res.json();
        if (data.success) existingImageUrl.value = data.url;
        else alert(data.message || 'Не удалось повернуть изображение');
    } catch { alert('Не удалось повернуть изображение'); }
    finally { rotating.value = false; }
}

function rotateFileClockwise(file) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        const url = URL.createObjectURL(file);
        img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = img.naturalHeight;
            canvas.height = img.naturalWidth;
            const ctx = canvas.getContext('2d');
            ctx.translate(canvas.width, 0);
            ctx.rotate(Math.PI / 2);
            ctx.drawImage(img, 0, 0);
            URL.revokeObjectURL(url);
            const type = file.type === 'image/png' ? 'image/png' : file.type === 'image/webp' ? 'image/webp' : 'image/jpeg';
            canvas.toBlob(
                b => b ? resolve(new File([b], file.name, { type })) : reject(new Error('rotate failed')),
                type, 0.92,
            );
        };
        img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('load failed')); };
        img.src = url;
    });
}

function submit() {
    const key = props.item?._route_key ?? props.item?.id;
    const url = props.is_edit
        ? `/admin/${props.resource}/${key}`
        : `/admin/${props.resource}`;
    form.post(url, { forceFormData: !!props.image_field });
}

const primaryActions = computed(() => props.actions.filter(a => a.primary));
const secondaryActions = computed(() => props.actions.filter(a => !a.primary));

// ----- Conditional fields -----
// A field/attribute can declare `visible_when: {field, equals|not_equals|in|not_empty}`
// OR `visible_when: [ …list… ]` (AND semantics). Translatable form values
// are objects {ru,kk,en} — we pick the active locale for comparison so the
// condition reflects what the editor currently sees.
function valueFor(name) {
    const v = form[name];
    if (v && typeof v === 'object' && ('ru' in v || 'kk' in v || 'en' in v)) {
        return v[activeLocale.value] ?? v.ru ?? v.kk ?? v.en ?? '';
    }
    return v;
}
function evalCond(c) {
    if (!c || typeof c !== 'object' || !('field' in c)) return true;
    const v = valueFor(c.field);
    if ('equals'      in c) return v === c.equals;
    if ('not_equals'  in c) return v !== c.not_equals;
    if ('in'          in c) return Array.isArray(c.in) && c.in.includes(v);
    if ('not_in'      in c) return Array.isArray(c.not_in) && !c.not_in.includes(v);
    if ('not_empty'   in c) return !!v;
    if ('empty'       in c) return !v;
    return true;
}
function isFieldVisible(f) {
    const c = f.visible_when;
    if (!c) return true;
    const list = Array.isArray(c) && !('field' in c) ? c : [c];
    return list.every(evalCond);
}

// Split sections: translatable fields go to main, plain attributes to sidebar.
// Explicit `group: 'content'` on attribute forces main column; `group: 'sidebar'`
// forces right column. Default for attributes = sidebar.
const mainSections = computed(() => {
    const out = [];
    const map = new Map();
    const order = [];
    const hasFields = props.fields.length > 0;

    for (const f of props.fields) {
        const g = f.group || (hasFields ? 'Основная информация' : 'Содержимое');
        if (!map.has(g)) {
            map.set(g, { label: g, icon: f.group_icon || null, translatable: [], plain: [] });
            order.push(g);
        }
        map.get(g).translatable.push(f);
    }
    // Attributes explicitly marked as main content
    for (const a of props.attributes) {
        if (a.group === 'content' || a.main) {
            const g = a.section_label || 'Основная информация';
            if (!map.has(g)) {
                map.set(g, { label: g, icon: null, translatable: [], plain: [] });
                order.push(g);
            }
            map.get(g).plain.push(a);
        }
    }
    for (const l of order) out.push(map.get(l));
    return out;
});

const sidebarAttributes = computed(() =>
    props.attributes.filter(a => a.group !== 'content' && !a.main),
);

// Group sidebar attrs by optional `group` label (not 'content'/'sidebar').
const sidebarSections = computed(() => {
    const out = [];
    const map = new Map();
    const order = [];
    for (const a of sidebarAttributes.value) {
        const g = (a.group && a.group !== 'sidebar') ? a.group : 'Параметры';
        if (!map.has(g)) {
            map.set(g, { label: g, icon: a.group_icon || null, items: [] });
            order.push(g);
        }
        map.get(g).items.push(a);
    }
    for (const l of order) out.push(map.get(l));
    return out;
});

const hasTranslatableAnywhere = computed(() =>
    mainSections.value.some(s => s.translatable.length > 0)
);
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title">
        <template #actions>
            <a v-for="a in secondaryActions" :key="a.url" :href="a.url"
                class="inline-flex items-center gap-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 px-3 py-2 rounded-lg text-sm">
                <i class="fas" :class="a.icon"></i><span class="hidden sm:inline">{{ a.label }}</span>
            </a>
            <Link :href="`/admin/${resource}`" class="inline-flex items-center gap-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 px-3 py-2 rounded-lg text-sm">
                <i class="fas fa-arrow-left"></i><span class="hidden sm:inline">К списку</span>
            </Link>
        </template>
    </PageHeader>

    <!-- Primary CTA banner(s) -->
    <div v-for="a in primaryActions" :key="a.url"
        class="mb-6 rounded-xl border border-red-200 dark:border-red-900/50 bg-gradient-to-r from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20 p-4 sm:p-5">
        <div class="flex items-center justify-between gap-3 flex-wrap sm:flex-nowrap">
            <div class="flex items-start gap-3 min-w-0">
                <div class="w-10 h-10 rounded-lg bg-red-600 text-white flex items-center justify-center flex-shrink-0">
                    <i class="fas text-lg" :class="a.icon"></i>
                </div>
                <div class="min-w-0">
                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ a.label }}</h3>
                    <p v-if="a.description" class="text-sm text-gray-600 dark:text-gray-300 mt-0.5">{{ a.description }}</p>
                </div>
            </div>
            <a :href="a.url" class="flex-shrink-0 inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-5 py-2.5 rounded-lg font-medium shadow-sm">
                <span>Открыть</span>
                <i class="fas fa-arrow-right text-xs"></i>
            </a>
        </div>
    </div>

    <form @submit.prevent="submit" class="pb-24 lg:pb-0">
        <!-- Sticky locale tabs — only if translatable fields exist -->
        <div v-if="hasTranslatableAnywhere" class="sticky top-16 z-20 bg-gray-50 dark:bg-gray-900 py-2 mb-2 -mx-4 md:-mx-6 px-4 md:px-6 border-b border-gray-200 dark:border-gray-700">
            <LocaleTabs v-model="activeLocale" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5">
            <!-- Main column -->
            <div class="space-y-5 min-w-0">
                <!-- Translatable sections -->
                <div v-for="sec in mainSections" :key="sec.label"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                        <i v-if="sec.icon" class="fas text-gray-400" :class="sec.icon"></i>
                        {{ sec.label }}
                    </h3>
                    <div class="space-y-5">
                        <template v-for="f in sec.translatable" :key="f.name">
                            <TranslatableField v-if="isFieldVisible(f)"
                                :name="f.name" :type="f.type" :label="f.label" :required="f.required"
                                :active-locale="activeLocale" :errors="form.errors" v-model="form[f.name]" />
                        </template>
                        <div v-if="sec.plain.length" :class="sec.translatable.length ? 'pt-4 border-t border-gray-100 dark:border-gray-700' : ''">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <template v-for="a in sec.plain" :key="a.name">
                                    <SimpleField v-if="isFieldVisible(a)"
                                        :name="a.name" :type="a.type" :label="a.label"
                                        :required="a.required" :placeholder="a.placeholder"
                                        :options="a.options || []" :help="a.help"
                                        :errors="form.errors"
                                        v-model="form[a.name]" />
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right sidebar: image + metadata attributes -->
            <aside class="space-y-5">
                <!-- Image uploader -->
                <div v-if="image_field" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-5">
                    <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2 mb-3">
                        <i class="fas fa-image text-gray-400"></i>
                        Изображение
                    </h3>
                    <div v-if="imagePreview || existingImageUrl" class="relative mb-3">
                        <img :src="imagePreview || existingImageUrl" class="w-full rounded-lg object-cover max-h-60">
                        <button type="button" @click="rotateImage" title="Повернуть на 90° по часовой" :disabled="rotating"
                            class="absolute top-2 right-12 w-8 h-8 rounded-full bg-white/90 dark:bg-gray-900/90 text-gray-700 dark:text-gray-200 flex items-center justify-center shadow hover:bg-white disabled:opacity-50">
                            <i class="fas text-sm" :class="rotating ? 'fa-spinner fa-spin' : 'fa-rotate-right'"></i>
                        </button>
                        <button type="button" @click="removeImage" title="Удалить"
                            class="absolute top-2 right-2 w-8 h-8 rounded-full bg-red-600 text-white flex items-center justify-center shadow hover:bg-red-700">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>
                    <label class="block">
                        <span class="sr-only">Выбрать файл</span>
                        <input type="file" accept="image/*" @change="onImageChange"
                            class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-sm file:bg-red-50 file:text-red-700 hover:file:bg-red-100 dark:file:bg-red-900/30 dark:file:text-red-300 cursor-pointer">
                    </label>
                </div>

                <!-- Sidebar attribute sections -->
                <div v-for="sec in sidebarSections" :key="sec.label"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-5">
                    <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2 mb-3">
                        <i v-if="sec.icon" class="fas text-gray-400 text-sm" :class="sec.icon"></i>
                        {{ sec.label }}
                    </h3>
                    <div class="space-y-4">
                        <template v-for="a in sec.items" :key="a.name">
                            <SimpleField v-if="isFieldVisible(a)"
                                :name="a.name" :type="a.type" :label="a.label"
                                :required="a.required" :placeholder="a.placeholder"
                                :options="a.options || []" :help="a.help"
                                :errors="form.errors"
                                v-model="form[a.name]" />
                        </template>
                    </div>
                </div>
            </aside>
        </div>

        <!-- Sticky bottom action bar (mobile) + inline actions (desktop) -->
        <div class="hidden lg:flex items-center justify-end gap-3 mt-6">
            <Link :href="`/admin/${resource}`" class="px-5 py-2.5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg text-sm font-medium">
                Отмена
            </Link>
            <button type="submit" :disabled="form.processing"
                class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white px-6 py-2.5 rounded-lg font-medium shadow-sm">
                <i class="fas" :class="form.processing ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                <span>{{ is_edit ? 'Сохранить' : 'Создать' }}</span>
            </button>
        </div>

        <!-- Mobile: sticky bottom bar -->
        <div class="lg:hidden fixed bottom-0 inset-x-0 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 px-4 py-3 flex items-center gap-2 z-30 shadow-lg">
            <Link :href="`/admin/${resource}`" class="flex-1 text-center py-2.5 text-gray-700 dark:text-gray-300 rounded-lg text-sm border border-gray-300 dark:border-gray-600">
                Отмена
            </Link>
            <button type="submit" :disabled="form.processing"
                class="flex-1 inline-flex items-center justify-center gap-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white py-2.5 rounded-lg font-medium shadow-sm">
                <i class="fas" :class="form.processing ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                <span>{{ is_edit ? 'Сохранить' : 'Создать' }}</span>
            </button>
        </div>
    </form>
</template>
