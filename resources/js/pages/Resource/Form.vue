<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '../../components/PageHeader.vue';
import LocaleTabs from '../../components/LocaleTabs.vue';
import TranslatableField from '../../components/TranslatableField.vue';

const props = defineProps({
    title: String,
    item: Object,
    fields: Array,
    locales: Array,
    resource: String,
    is_edit: Boolean,
});

const activeLocale = ref('ru');

// Translatable fields
const translatableFieldNames = computed(() => props.fields.filter(f => f.translatable !== false && ['text', 'textarea', 'editor'].includes(f.type)).map(f => f.name));

const initial = () => {
    const base = { _method: props.is_edit ? 'put' : 'post' };

    // Seed translatable fields as {ru,kk,en}
    for (const f of props.fields) {
        const v = props.item?.[f.name] ?? {};
        base[f.name] = typeof v === 'object' && v !== null
            ? { ru: v.ru ?? '', kk: v.kk ?? '', en: v.en ?? '' }
            : { ru: v ?? '', kk: '', en: '' };
    }

    // Carry over plain fields from item
    for (const key of Object.keys(props.item || {})) {
        if (!translatableFieldNames.value.includes(key) && key !== 'id') {
            base[key] = props.item[key];
        }
    }

    return base;
};

const form = useForm(initial());

function submit() {
    const url = props.is_edit
        ? `/admin/${props.resource}/${props.item.id}`
        : `/admin/${props.resource}`;
    form.post(url, { forceFormData: true });
}
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title">
        <template #actions>
            <Link :href="`/admin/${resource}`" class="inline-flex items-center gap-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-arrow-left"></i><span>К списку</span>
            </Link>
        </template>
    </PageHeader>

    <form @submit.prevent="submit" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <LocaleTabs v-model="activeLocale" />
                    <div class="space-y-5">
                        <TranslatableField v-for="f in fields" :key="f.name"
                            :name="f.name" :type="f.type" :label="f.label" :required="f.required"
                            :active-locale="activeLocale" :errors="form.errors" v-model="form[f.name]" />
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Публикация</h3>
                    <button type="submit" :disabled="form.processing" class="w-full bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white py-2.5 rounded-lg font-medium">
                        <i class="fas" :class="form.processing ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                        {{ is_edit ? 'Сохранить' : 'Создать' }}
                    </button>
                </div>
            </div>
        </div>
    </form>
</template>
