<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '@admin-core/components/PageHeader.vue';
import Pagination from '@admin-core/components/Pagination.vue';

const props = defineProps({
    title: String,
    items: Object,
    statuses: Array,
    types: Array,
    filters: Object,
    counts: Object,
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const type = ref(props.filters.type ?? '');
let tm = null;
watch(search, (v) => { clearTimeout(tm); tm = setTimeout(() => apply({ search: v, status: status.value, type: type.value }), 300); });
watch(status, (v) => apply({ search: search.value, status: v, type: type.value }));
watch(type, (v) => apply({ search: search.value, status: status.value, type: v }));

function apply(params) {
    router.get('/admin/leads',
        Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v != null)),
        { preserveState: true, preserveScroll: true, replace: true });
}
function destroy(l) {
    if (!confirm(`Удалить заявку от «${l.name}»?`)) return;
    router.delete(`/admin/leads/${l.id}`, { preserveScroll: true });
}

function statusBadge(s) {
    const st = props.statuses.find(x => x.value === s);
    const color = st?.color || 'gray';
    const map = {
        blue:   'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
        indigo: 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300',
        purple: 'bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300',
        green:  'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300',
        amber:  'bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300',
        gray:   'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400',
    };
    return map[color];
}
function typeLabel(t) {
    return (props.types.find(x => x.value === t) || {}).label || t || '—';
}
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title" :subtitle="`Всего: ${counts.total} · Новых: ${counts.new} · Преобразовано: ${counts.converted}`" />

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-4 flex flex-col md:flex-row gap-3">
        <div class="relative flex-1">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input v-model="search" type="search" placeholder="Имя / email / телефон…"
                class="w-full pl-9 pr-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-red-500">
        </div>
        <select v-model="status" class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm px-3 py-2">
            <option value="">Все статусы</option>
            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>
        <select v-model="type" class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm px-3 py-2">
            <option value="">Все типы</option>
            <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
        </select>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900/40 border-b border-gray-200 dark:border-gray-700">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Заявитель</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase hidden md:table-cell">Контакты</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase hidden lg:table-cell">Программа</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Статус</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase hidden md:table-cell">Дата</th>
                    <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Действия</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr v-for="l in items.data" :key="l.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                    <td class="px-4 py-3">
                        <Link :href="`/admin/leads/${l.id}`" class="font-medium text-gray-900 dark:text-white hover:text-red-700">
                            {{ l.name || '(без имени)' }}
                        </Link>
                        <div class="text-xs text-gray-500 mt-0.5">{{ typeLabel(l.type) }}</div>
                    </td>
                    <td class="px-4 py-3 text-sm hidden md:table-cell">
                        <div v-if="l.email" class="text-gray-700 dark:text-gray-300 truncate">{{ l.email }}</div>
                        <div v-if="l.phone" class="text-gray-500 text-xs font-mono">{{ l.phone }}</div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 hidden lg:table-cell">{{ l.program || '—' }}</td>
                    <td class="px-4 py-3">
                        <span :class="statusBadge(l.status)" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium">
                            {{ l.status_name }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500 hidden md:table-cell">{{ l.created_at }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-1">
                            <Link :href="`/admin/leads/${l.id}`" class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded" title="Просмотр"><i class="fas fa-eye"></i></Link>
                            <button @click="destroy(l)" class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <tr v-if="items.data.length === 0">
                    <td colspan="6" class="px-4 py-12 text-center text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-2 opacity-30"></i>
                        <p>Заявок не найдено</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <Pagination :meta="items" />
</template>
