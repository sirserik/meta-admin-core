<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@admin-core/components/PageHeader.vue';
import Pagination from '@admin-core/components/Pagination.vue';

const props = defineProps({
    title: String,
    items: Object,
    actions: Array,
    filters: Object,
});

const search = ref(props.filters.search ?? '');
const action = ref(props.filters.action ?? '');
let tm = null;
watch(search, (v) => { clearTimeout(tm); tm = setTimeout(() => apply({ search: v, action: action.value }), 300); });
watch(action, (v) => apply({ search: search.value, action: v }));

function apply(params) {
    router.get('/admin/activity',
        Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v != null)),
        { preserveState: true, preserveScroll: true, replace: true });
}

function actionColor(a) {
    const map = {
        created: 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300',
        updated: 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
        deleted: 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300',
    };
    return map[a] || 'bg-gray-100 dark:bg-gray-700 text-gray-600';
}
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title" :subtitle="`Всего записей: ${items.total}`" />

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-4 flex flex-col md:flex-row gap-3">
        <div class="relative flex-1">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input v-model="search" type="search" placeholder="Описание / тип модели…" class="w-full pl-9 pr-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-red-500">
        </div>
        <select v-model="action" class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm px-3 py-2">
            <option value="">Все действия</option>
            <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
        </select>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900/40 border-b border-gray-200 dark:border-gray-700">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Время</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Пользователь</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Действие</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Объект</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Описание</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase hidden md:table-cell">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr v-for="l in items.data" :key="l.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                    <td class="px-4 py-2 text-xs text-gray-500 whitespace-nowrap">{{ l.created_at }}</td>
                    <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">{{ l.user?.name || '—' }}</td>
                    <td class="px-4 py-2"><span :class="actionColor(l.action)" class="px-2 py-0.5 rounded text-xs font-medium">{{ l.action }}</span></td>
                    <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400"><code class="font-mono">{{ l.model_type }}{{ l.model_id ? '#' + l.model_id : '' }}</code></td>
                    <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">{{ l.description || '—' }}</td>
                    <td class="px-4 py-2 text-xs text-gray-500 font-mono hidden md:table-cell">{{ l.ip_address }}</td>
                </tr>
                <tr v-if="items.data.length === 0">
                    <td colspan="6" class="px-4 py-12 text-center text-gray-500">
                        <i class="fas fa-clock-rotate-left text-4xl mb-2 opacity-30"></i>
                        <p>Записей не найдено</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <Pagination :meta="items" />
</template>
