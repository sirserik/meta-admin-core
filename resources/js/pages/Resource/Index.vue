<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '../../components/PageHeader.vue';
import Pagination from '../../components/Pagination.vue';

const props = defineProps({
    title: String,
    items: Object,       // paginator
    resource: String,    // resource name (articles, news, programs, …)
    filters: Object,
    activeFilterLabels: { type: Object, default: () => ({}) },
    fields: Array,
});

const search = ref(props.filters?.search ?? '');
let tm = null;
watch(search, (v) => {
    clearTimeout(tm);
    tm = setTimeout(() => {
        // Preserve other filters while searching
        const params = { ...(props.filters || {}), search: v };
        if (!v) delete params.search;
        router.get(`/admin/${props.resource}`, params,
            { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});

// Filters other than 'search' (e.g. school_id) — preserve them when
// clicking Create and on "Отфильтровано" indicator.
const activeFilters = computed(() => {
    const out = {};
    for (const [k, v] of Object.entries(props.filters || {})) {
        if (k !== 'search' && v !== null && v !== '' && v !== undefined) out[k] = v;
    }
    return out;
});
const hasActiveFilters = computed(() => Object.keys(activeFilters.value).length > 0);
const createUrl = computed(() => {
    const qs = new URLSearchParams(activeFilters.value).toString();
    return `/admin/${props.resource}/create${qs ? '?' + qs : ''}`;
});
const filterSummary = computed(() => {
    const parts = Object.values(props.activeFilterLabels || {}).map(
        (f) => `${f.label}: ${f.value}`
    );
    return parts.length ? parts.join(' · ') : null;
});
const subtitle = computed(() => {
    if (filterSummary.value) return filterSummary.value;
    return `Всего: ${props.items.total}`;
});

const emptyState = computed(() => {
    if (hasActiveFilters.value) {
        return {
            icon: 'fa-inbox',
            title: 'По этому фильтру записей пока нет',
            hint: 'Нажми «Создать» чтобы добавить первую запись — фильтр уже подставится в форму. Или сбрось фильтр чтобы увидеть все записи.',
        };
    }
    if (props.items.total === 0 && props.filters?.search) {
        return { icon: 'fa-magnifying-glass', title: 'Ничего не найдено', hint: 'Попробуй другой поисковый запрос.' };
    }
    return { icon: 'fa-inbox', title: 'Пока ничего нет', hint: 'Нажми «Создать» чтобы добавить первую запись.' };
});

const badgeClass = (color) => ({
    amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    red:   'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    green: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    blue:  'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    purple:'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
    gray:  'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
}[color] || 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300');

function rowKey(row) {
    return row._route_key ?? row.id;
}
function destroy(row) {
    if (!confirm(`Удалить «${row.title}»?`)) return;
    router.delete(`/admin/${props.resource}/${rowKey(row)}`, { preserveScroll: true });
}
function toggle(row) {
    router.patch(`/admin/${props.resource}/${rowKey(row)}/toggle-publish`, {}, { preserveScroll: true });
}

function statusBadge(row) {
    if (row.is_published === true) return 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300';
    if (row.is_active === true)    return 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300';
    if (row.status === 'published') return 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300';
    return 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400';
}
function statusLabel(row) {
    if (row.is_published !== undefined) return row.is_published ? 'Опубликовано' : 'Черновик';
    if (row.is_active    !== undefined) return row.is_active    ? 'Активно' : 'Скрыто';
    if (row.status)                     return row.status === 'published' ? 'Опубликовано' : row.status;
    return '';
}

function hasPublishableFlag(row) {
    return row.is_published !== undefined || row.is_active !== undefined || row.status !== undefined;
}
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title" :subtitle="subtitle">
        <template #actions>
            <Link v-if="hasActiveFilters" :href="`/admin/${resource}`"
                class="inline-flex items-center gap-2 bg-white dark:bg-gray-800 border border-amber-300 dark:border-amber-900/50 text-amber-700 dark:text-amber-300 px-3 py-2 rounded-lg text-sm">
                <i class="fas fa-filter"></i>
                <span class="hidden sm:inline">Сбросить фильтр</span>
            </Link>
            <Link :href="createUrl" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-plus"></i><span>Создать</span>
            </Link>
        </template>
    </PageHeader>

    <!-- Filter banner -->
    <div v-if="hasActiveFilters" class="mb-4 flex items-start gap-3 text-sm bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-900/50 text-amber-800 dark:text-amber-200 px-4 py-3 rounded-lg">
        <i class="fas fa-filter mt-0.5"></i>
        <div class="flex-1 space-y-1">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-medium">Показаны только:</span>
                <span v-for="(v, k) in activeFilters" :key="k" class="inline-flex items-center gap-1 bg-amber-100 dark:bg-amber-900/40 px-2 py-0.5 rounded">
                    <span class="text-amber-700/70 dark:text-amber-400">{{ (activeFilterLabels[k] && activeFilterLabels[k].label) || k }}:</span>
                    <strong>{{ (activeFilterLabels[k] && activeFilterLabels[k].value) || v }}</strong>
                </span>
            </div>
            <p class="text-xs text-amber-700/80 dark:text-amber-300/70">
                Кнопка «Создать» добавит запись с этим фильтром. «Сбросить фильтр» — увидеть все записи.
            </p>
        </div>
    </div>

    <!-- Search -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3 sm:p-4 mb-4">
        <div class="relative">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input v-model="search" type="search" placeholder="Поиск…"
                class="w-full pl-9 pr-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-red-500">
        </div>
    </div>

    <!-- Desktop/tablet: real table. Mobile: card list. -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Table layout — md+ -->
        <table class="w-full hidden md:table">
            <thead class="bg-gray-50 dark:bg-gray-900/40 border-b border-gray-200 dark:border-gray-700 sticky top-16 z-10">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Запись</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-36">Статус</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-36 whitespace-nowrap">Действия</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr v-for="row in items.data" :key="row.id"
                    class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
                    :class="row._dim ? 'opacity-60' : ''">
                    <td class="px-4 py-3">
                        <Link :href="row.url" class="flex items-center gap-3 group">
                            <img v-if="row.image_url" :src="row.image_url" class="w-10 h-10 rounded object-cover flex-shrink-0" :class="row._dim ? 'grayscale' : ''">
                            <div v-else class="w-10 h-10 rounded bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-400 flex-shrink-0">
                                <i class="fas fa-file"></i>
                            </div>
                            <div class="min-w-0">
                                <span class="font-medium text-gray-900 dark:text-white group-hover:text-red-700 dark:group-hover:text-red-400 block truncate">
                                    {{ row.title || '(без названия)' }}
                                </span>
                                <div v-if="row.badges && row.badges.length" class="flex flex-wrap items-center gap-1 mt-1">
                                    <span v-for="(b, i) in row.badges" :key="i"
                                        :class="badgeClass(b.color)"
                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium">
                                        <i v-if="b.icon" class="fas text-[10px]" :class="b.icon"></i>
                                        {{ b.label }}
                                    </span>
                                    <span v-if="row._dim" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                        <i class="fas fa-eye-slash text-[10px]"></i>
                                        Скрыт
                                    </span>
                                </div>
                            </div>
                        </Link>
                    </td>
                    <td class="px-4 py-3">
                        <span v-if="hasPublishableFlag(row)" :class="statusBadge(row)" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium whitespace-nowrap">
                            {{ statusLabel(row) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap w-36">
                        <div class="flex justify-end gap-1">
                            <Link :href="row.url" title="Редактировать"
                                class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded">
                                <i class="fas fa-pen"></i>
                            </Link>
                            <button v-if="hasPublishableFlag(row)" @click="toggle(row)" title="Опубликовать/скрыть"
                                class="p-2 text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
                                <i class="fas" :class="row.is_published || row.is_active || row.status === 'published' ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                            <button @click="destroy(row)" title="Удалить"
                                class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <tr v-if="items.data.length === 0">
                    <td colspan="3" class="px-6 py-16 text-center">
                        <i class="fas text-5xl mb-4 text-gray-300 dark:text-gray-600" :class="emptyState.icon"></i>
                        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ emptyState.title }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-5">{{ emptyState.hint }}</p>
                        <div class="flex items-center justify-center gap-2 flex-wrap">
                            <Link :href="createUrl" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                <i class="fas fa-plus"></i><span>Создать</span>
                            </Link>
                            <Link v-if="hasActiveFilters" :href="`/admin/${resource}`"
                                class="inline-flex items-center gap-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg text-sm">
                                <i class="fas fa-times"></i><span>Сбросить фильтр</span>
                            </Link>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Card list — mobile (below md) -->
        <ul class="md:hidden divide-y divide-gray-100 dark:divide-gray-700">
            <li v-for="row in items.data" :key="row.id"
                class="p-4 flex items-start gap-3"
                :class="row._dim ? 'opacity-60' : ''">
                <Link :href="row.url" class="flex-shrink-0">
                    <img v-if="row.image_url" :src="row.image_url" class="w-14 h-14 rounded object-cover" :class="row._dim ? 'grayscale' : ''">
                    <div v-else class="w-14 h-14 rounded bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-400">
                        <i class="fas fa-file text-lg"></i>
                    </div>
                </Link>
                <div class="flex-1 min-w-0">
                    <Link :href="row.url" class="font-medium text-gray-900 dark:text-white block truncate hover:text-red-700 dark:hover:text-red-400">
                        {{ row.title || '(без названия)' }}
                    </Link>
                    <div class="flex flex-wrap items-center gap-1 mt-1">
                        <span v-if="hasPublishableFlag(row)" :class="statusBadge(row)" class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium">
                            {{ statusLabel(row) }}
                        </span>
                        <span v-for="(b, i) in (row.badges || [])" :key="i"
                            :class="badgeClass(b.color)"
                            class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium">
                            <i v-if="b.icon" class="fas text-[10px]" :class="b.icon"></i>
                            {{ b.label }}
                        </span>
                    </div>
                    <!-- Mobile actions row -->
                    <div class="flex items-center gap-3 mt-3">
                        <Link :href="row.url" class="flex items-center gap-1 text-sm text-blue-600 hover:underline">
                            <i class="fas fa-pen text-xs"></i><span>Изменить</span>
                        </Link>
                        <button v-if="hasPublishableFlag(row)" @click="toggle(row)"
                            class="flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400 hover:underline">
                            <i class="fas text-xs" :class="row.is_published || row.is_active || row.status === 'published' ? 'fa-eye-slash' : 'fa-eye'"></i>
                            <span>{{ row.is_published || row.is_active || row.status === 'published' ? 'Скрыть' : 'Показать' }}</span>
                        </button>
                        <button @click="destroy(row)" class="flex items-center gap-1 text-sm text-red-600 hover:underline ml-auto">
                            <i class="fas fa-trash text-xs"></i><span>Удалить</span>
                        </button>
                    </div>
                </div>
            </li>
            <li v-if="items.data.length === 0" class="px-6 py-16 text-center">
                <i class="fas text-5xl mb-4 text-gray-300 dark:text-gray-600" :class="emptyState.icon"></i>
                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ emptyState.title }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-5">{{ emptyState.hint }}</p>
                <Link :href="createUrl" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    <i class="fas fa-plus"></i><span>Создать</span>
                </Link>
            </li>
        </ul>
    </div>

    <Pagination :meta="items" />
</template>
