<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '@admin-core/components/PageHeader.vue';
import Pagination from '@admin-core/components/Pagination.vue';

const props = defineProps({
    title: String,
    items: Object,
    statuses: Array,
    categories: Array,
    filters: Object,
    counts: Object,
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const category = ref(props.filters.category ?? '');
let tm = null;
watch(search, (v) => { clearTimeout(tm); tm = setTimeout(() => apply({ search: v, status: status.value, category: category.value }), 300); });
watch(status, (v) => apply({ search: search.value, status: v, category: category.value }));
watch(category, (v) => apply({ search: search.value, status: status.value, category: v }));

function apply(params) {
    router.get('/admin/rector-questions',
        Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v != null)),
        { preserveState: true, preserveScroll: true, replace: true });
}
function destroy(q) {
    if (!confirm(`Удалить вопрос #${q.id}?`)) return;
    router.delete(`/admin/rector-questions/${q.id}`, { preserveScroll: true });
}
function statusLabel(s) {
    return (props.statuses.find(x => x.value === s) || {}).label || s;
}
function statusBadge(s) {
    const st = props.statuses.find(x => x.value === s);
    const color = st?.color || 'gray';
    const map = {
        blue:  'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
        amber: 'bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300',
        green: 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300',
        gray:  'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400',
    };
    return map[color];
}
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title" :subtitle="`Всего: ${counts.total} · Новых: ${counts.new} · Отвечено: ${counts.answered}`" />

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-4 flex flex-col md:flex-row gap-3">
        <div class="relative flex-1">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input v-model="search" type="search" placeholder="Поиск…"
                class="w-full pl-9 pr-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-red-500">
        </div>
        <select v-model="status" class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm px-3 py-2">
            <option value="">Все статусы</option>
            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>
        <select v-model="category" class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm px-3 py-2">
            <option value="">Все категории</option>
            <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
        </select>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900/40 border-b border-gray-200 dark:border-gray-700">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Тема / Автор</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase hidden md:table-cell">Категория</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Статус</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase hidden md:table-cell">Дата</th>
                    <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Действия</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr v-for="q in items.data" :key="q.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                    <td class="px-4 py-3">
                        <Link :href="`/admin/rector-questions/${q.id}`" class="font-medium text-gray-900 dark:text-white hover:text-red-700 line-clamp-1">
                            {{ q.subject || '(без темы)' }}
                        </Link>
                        <div class="text-xs text-gray-500 mt-0.5">{{ q.name }} — {{ q.email }}</div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 hidden md:table-cell">{{ q.category_label }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <span :class="statusBadge(q.status)" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium">
                                {{ statusLabel(q.status) }}
                            </span>
                            <span v-if="q.is_published" class="text-xs text-green-600" title="Опубликовано на сайте">
                                <i class="fas fa-globe"></i>
                            </span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500 hidden md:table-cell">{{ q.created_at }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-1">
                            <Link :href="`/admin/rector-questions/${q.id}`" class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded"><i class="fas fa-eye"></i></Link>
                            <button @click="destroy(q)" class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <tr v-if="items.data.length === 0">
                    <td colspan="5" class="px-4 py-12 text-center text-gray-500">
                        <i class="fas fa-comments text-4xl mb-2 opacity-30"></i>
                        <p>Вопросов не найдено</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <Pagination :meta="items" />
</template>
