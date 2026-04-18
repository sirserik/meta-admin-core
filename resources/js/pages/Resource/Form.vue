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
    attributes: { type: Array, default: () => [] },   // plain sidebar attributes
    actions:    { type: Array, default: () => [] },   // extra CTA buttons/banners
    locales:    { type: Array, default: () => ['ru','kk','en'] },
    image_field: { type: String, default: null },
    resource: String,
    is_edit: Boolean,
});

const primaryActions = computed(() => props.actions.filter(a => a.primary));
const secondaryActions = computed(() => props.actions.filter(a => !a.primary));

const activeLocale = ref('ru');

const initial = () => {
    const base = { _method: props.is_edit ? 'put' : 'post' };

    // Translatable fields: {ru,kk,en}
    for (const f of props.fields) {
        const v = props.item?.[f.name] ?? {};
        base[f.name] = typeof v === 'object' && v !== null
            ? { ru: v.ru ?? '', kk: v.kk ?? '', en: v.en ?? '' }
            : { ru: v ?? '', kk: '', en: '' };
    }

    // Plain attributes: flat values from item
    for (const a of props.attributes) {
        base[a.name] = props.item?.[a.name] ?? (a.type === 'boolean' ? false : '');
    }

    // Image field slots
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
    // Use _route_key sent from the backend (slug for News, id for most
    // others). Fallback to id for older payloads.
    const key = props.item?._route_key ?? props.item?.id;
    const url = props.is_edit
        ? `/admin/${props.resource}/${key}`
        : `/admin/${props.resource}`;
    form.post(url, { forceFormData: !!props.image_field });
}
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title">
        <template #actions>
            <!-- Secondary actions (small buttons in header) -->
            <a v-for="a in secondaryActions" :key="a.url" :href="a.url"
                class="inline-flex items-center gap-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 px-4 py-2 rounded-lg text-sm">
                <i class="fas" :class="a.icon"></i><span>{{ a.label }}</span>
            </a>
            <Link :href="`/admin/${resource}`" class="inline-flex items-center gap-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-arrow-left"></i><span>К списку</span>
            </Link>
        </template>
    </PageHeader>

    <!-- Primary action banner(s) — big CTA for "edit related resource" flows -->
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
            <a :href="a.url"
                class="flex-shrink-0 inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-5 py-2.5 rounded-lg font-medium shadow-sm">
                <span>Открыть</span>
                <i class="fas fa-arrow-right text-xs"></i>
            </a>
        </div>
    </div>

    <form @submit.prevent="submit" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main: translatable fields -->
            <div :class="fields.length ? 'lg:col-span-2' : 'lg:col-span-3'">
                <div v-if="fields.length" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <LocaleTabs v-model="activeLocale" />
                    <div class="space-y-5">
                        <TranslatableField v-for="f in fields" :key="f.name"
                            :name="f.name" :type="f.type" :label="f.label" :required="f.required"
                            :active-locale="activeLocale" :errors="form.errors" v-model="form[f.name]" />
                    </div>
                </div>
            </div>

            <!-- Sidebar: actions + plain attributes + image -->
            <div class="space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <button type="submit" :disabled="form.processing"
                        class="w-full bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white py-2.5 rounded-lg font-medium">
                        <i class="fas" :class="form.processing ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                        {{ is_edit ? 'Сохранить' : 'Создать' }}
                    </button>
                </div>

                <div v-if="attributes.length" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Атрибуты</h3>
                    <SimpleField v-for="a in attributes" :key="a.name"
                        :name="a.name"
                        :type="a.type"
                        :label="a.label"
                        :required="a.required"
                        :placeholder="a.placeholder"
                        :options="a.options || []"
                        :help="a.help"
                        :errors="form.errors"
                        v-model="form[a.name]" />
                </div>

                <div v-if="image_field" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-3">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Изображение</h3>
                    <div v-if="imagePreview || existingImageUrl" class="relative">
                        <img :src="imagePreview || existingImageUrl" class="w-full rounded-lg object-cover max-h-48">
                        <button type="button" @click="removeImage" class="absolute top-2 right-2 w-7 h-7 rounded-full bg-red-600 text-white flex items-center justify-center">
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>
                    <input type="file" accept="image/*" @change="onImageChange"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-red-50 file:text-red-700 hover:file:bg-red-100 dark:file:bg-red-900/30 dark:file:text-red-300">
                </div>
            </div>
        </div>
    </form>
</template>
