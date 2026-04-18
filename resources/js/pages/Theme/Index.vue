<script setup>
import { reactive } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@admin-core/components/PageHeader.vue';

const props = defineProps({
    title: String,
    tokens: Object,  // { group: { name: value } }
});

const form = useForm({
    tokens: JSON.parse(JSON.stringify(props.tokens || {})),
});

function save() {
    form.put('/admin/theme', { preserveScroll: true });
}
function reset() {
    if (!confirm('Сбросить все цвета к значениям по умолчанию?')) return;
    router.post('/admin/theme/reset', {}, { preserveScroll: true });
}

function isColor(v) { return typeof v === 'string' && /^#[0-9a-f]{3,8}$/i.test(v); }
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title" subtitle="CSS-переменные темы">
        <template #actions>
            <button @click="reset" class="inline-flex items-center gap-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-rotate-left"></i><span>Сбросить</span>
            </button>
            <button @click="save" :disabled="form.processing" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas" :class="form.processing ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                <span>Сохранить</span>
            </button>
        </template>
    </PageHeader>

    <div class="space-y-6">
        <section v-for="(group, groupName) in form.tokens" :key="groupName"
            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <header class="px-6 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                <h2 class="font-semibold text-gray-900 dark:text-white">{{ groupName }}</h2>
            </header>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                <div v-for="(val, key) in group" :key="key" class="px-6 py-3 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                    <code class="md:col-span-1 text-sm font-mono text-gray-900 dark:text-white">{{ key }}</code>
                    <div class="md:col-span-2 flex gap-2 items-center">
                        <input v-if="isColor(val)" v-model="form.tokens[groupName][key]" type="color" class="w-10 h-10 rounded cursor-pointer border border-gray-300 dark:border-gray-600">
                        <input v-model="form.tokens[groupName][key]" type="text" class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm font-mono">
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
