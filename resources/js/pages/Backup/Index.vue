<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@admin-core/components/PageHeader.vue';

const props = defineProps({
    title: String,
    backups: Array,
    dbPath: String,
    dbSize: String,
});

const createForm = useForm({});
function create() {
    if (!confirm('Создать новый бэкап? Это может занять минуту.')) return;
    createForm.post('/admin/backup', { preserveScroll: true });
}
function destroy(name) {
    if (!confirm(`Удалить бэкап «${name}»?`)) return;
    router.delete('/admin/backup', { data: { name }, preserveScroll: true });
}
function download(name) {
    window.location.href = `/admin/backup/download?name=${encodeURIComponent(name)}`;
}
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title" :subtitle="`База данных: ${dbSize}`">
        <template #actions>
            <button @click="create" :disabled="createForm.processing"
                class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas" :class="createForm.processing ? 'fa-spinner fa-spin' : 'fa-plus'"></i>
                <span>{{ createForm.processing ? 'Создаётся…' : 'Новый бэкап' }}</span>
            </button>
        </template>
    </PageHeader>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900/40 border-b border-gray-200 dark:border-gray-700">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Имя файла</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Размер</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Дата</th>
                    <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Действия</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr v-for="b in backups" :key="b.name" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                    <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">{{ b.name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ b.size }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ b.date }}</td>
                    <td class="px-4 py-3 text-right">
                        <button @click="download(b.name)" class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded" title="Скачать"><i class="fas fa-download"></i></button>
                        <button @click="destroy(b.name)" class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded" title="Удалить"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <tr v-if="backups.length === 0">
                    <td colspan="4" class="px-4 py-12 text-center text-gray-500">
                        <i class="fas fa-database text-4xl mb-2 opacity-30"></i>
                        <p>Бэкапов ещё нет</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
