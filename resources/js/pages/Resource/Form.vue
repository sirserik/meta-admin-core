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
    fields:     { type: Array, default: () => [] },   // translatable main fields
    attributes: { type: Array, default: () => [] },   // plain form fields
    actions:    { type: Array, default: () => [] },   // extra CTA buttons/banners
    locales:    { type: Array, default: () => ['ru','kk','en'] },
    image_field: { type: String, default: null },
    resource: String,
    is_edit: Boolean,
});

const activeLocale = ref('ru');

const initial = () => {
    const base = { _method: props.is_edit ? 'put' : 'post' };

    for (const f of props.fields) {
        const v = props.item?.[f.name] ?? {};
        base[f.name] = typeof v === 'object' && v !== null
            ? { ru: v.ru ?? '', kk: v.kk ?? '', en: v.en ?? '' }
            : { ru: v ?? '', kk: '', en: '' };
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

function submit() {
    const key = props.item?._route_key ?? props.item?.id;
    const url = props.is_edit
        ? `/admin/${props.resource}/${key}`
        : `/admin/${props.resource}`;
    form.post(url, { forceFormData: !!props.image_field });
}

const primaryActions = computed(() => props.actions.filter(a => a.primary));
const secondaryActions = computed(() => props.actions.filter(a => !a.primary));

/**
 * Build a single ordered list of sections mixing translatable fields and
 * plain attributes. Each field can declare `group` (label) + `group_icon`.
 * Unnamed-group fields land in "Основное" or "Дополнительно" depending on
 * type. Preserves declaration order — first occurrence of a group wins
 * its position.
 */
const sections = computed(() => {
    const order = [];                     // [groupLabel, ...]
    const map = new Map();                // label → { label, icon, translatable:[], plain:[] }
    const hasFields = props.fields.length > 0;

    for (const f of props.fields) {
        const g = f.group || (hasFields ? 'Основная информация' : 'Основное');
        if (!map.has(g)) {
            map.set(g, { label: g, icon: f.group_icon || null, translatable: [], plain: [] });
            order.push(g);
        }
        map.get(g).translatable.push(f);
    }
    for (const a of props.attributes) {
        const g = a.group || 'Атрибуты';
        if (!map.has(g)) {
            map.set(g, { label: g, icon: a.group_icon || null, translatable: [], plain: [] });
            order.push(g);
        }
        const sec = map.get(g);
        if (!sec.icon && a.group_icon) sec.icon = a.group_icon;
        sec.plain.push(a);
    }
    return order.map(l => map.get(l));
});

const hasTranslatableAnywhere = computed(() =>
    sections.value.some(s => s.translatable.length > 0)
);
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title">
        <template #actions>
            <a v-for="a in secondaryActions" :key="a.url" :href="a.url"
                class="inline-flex items-center gap-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 px-4 py-2 rounded-lg text-sm">
                <i class="fas" :class="a.icon"></i><span>{{ a.label }}</span>
            </a>
            <Link :href="`/admin/${resource}`" class="inline-flex items-center gap-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-arrow-left"></i><span>К списку</span>
            </Link>
        </template>
    </PageHeader>

    <!-- Primary CTA banner(s) -->
    <div v-for="a in primaryActions" :key="a.url"
        class="mb-6 rounded-xl border border-red-200 dark:border-red-900/50 bg-gradient-to-r from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20 p-5">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-lg bg-red-600 text-white flex items-center justify-center flex-shrink-0">
                    <i class="fas text-lg" :class="a.icon"></i>
                </div>
                <div>
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

    <form @submit.prevent="submit" class="max-w-5xl">
        <!-- Global locale tabs, shown once if form has any translatable fields -->
        <div v-if="hasTranslatableAnywhere" class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-900 pb-4 mb-2">
            <LocaleTabs v-model="activeLocale" />
        </div>

        <div class="space-y-5">
            <!-- Sections — translatable + plain mixed under a common heading -->
            <div v-for="sec in sections" :key="sec.label"
                class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <i v-if="sec.icon" class="fas text-gray-400" :class="sec.icon"></i>
                    {{ sec.label }}
                </h3>
                <div class="space-y-5">
                    <TranslatableField v-for="f in sec.translatable" :key="f.name"
                        :name="f.name" :type="f.type" :label="f.label" :required="f.required"
                        :active-locale="activeLocale" :errors="form.errors" v-model="form[f.name]" />
                    <div v-if="sec.plain.length" :class="sec.translatable.length ? 'pt-4 border-t border-gray-100 dark:border-gray-700' : ''">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <SimpleField v-for="a in sec.plain" :key="a.name"
                                :name="a.name" :type="a.type" :label="a.label"
                                :required="a.required" :placeholder="a.placeholder"
                                :options="a.options || []" :help="a.help"
                                :errors="form.errors"
                                v-model="form[a.name]" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Image uploader (stays as its own section) -->
            <div v-if="image_field" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-3">
                <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <i class="fas fa-image text-gray-400"></i>
                    Изображение
                </h3>
                <div v-if="imagePreview || existingImageUrl" class="relative inline-block">
                    <img :src="imagePreview || existingImageUrl" class="rounded-lg object-cover max-h-60">
                    <button type="button" @click="removeImage" class="absolute top-2 right-2 w-8 h-8 rounded-full bg-red-600 text-white flex items-center justify-center shadow">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
                <input type="file" accept="image/*" @change="onImageChange"
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-red-50 file:text-red-700 hover:file:bg-red-100 dark:file:bg-red-900/30 dark:file:text-red-300">
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <Link :href="`/admin/${resource}`" class="px-5 py-2.5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg text-sm">
                    Отмена
                </Link>
                <button type="submit" :disabled="form.processing"
                    class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white px-6 py-2.5 rounded-lg font-medium shadow-sm">
                    <i class="fas" :class="form.processing ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                    {{ is_edit ? 'Сохранить' : 'Создать' }}
                </button>
            </div>
        </div>
    </form>
</template>
