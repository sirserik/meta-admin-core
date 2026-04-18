<script setup>
import { ref, watch, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '@admin-core/components/PageHeader.vue';

const props = defineProps({
    title: String,
    groups: Object,     // { pageName: [rows] }
    pages: Array,       // [{slug, label}]
    types: Array,       // [{key, label, icon, category, description, preview}]
    statuses: Array,
    filters: Object,
    counts: Object,
});

const pageLabelMap = Object.fromEntries((props.pages || []).map(p => [p.slug, p.label]));
function pageLabel(slug) { return pageLabelMap[slug] || slug; }

const search = ref(props.filters.search ?? '');
const page = ref(props.filters.page ?? '');
const type = ref(props.filters.type ?? '');
const status = ref(props.filters.status ?? '');

let tm = null;
watch(search, (v) => { clearTimeout(tm); tm = setTimeout(() => apply({ search: v }), 300); });
watch(page,   (v) => apply({ page: v }));
watch(type,   (v) => apply({ type: v }));
watch(status, (v) => apply({ status: v }));

function apply(partial) {
    const merged = {
        search: search.value,
        page:   page.value,
        type:   type.value,
        status: status.value,
        ...partial,
    };
    router.get('/admin/blocks',
        Object.fromEntries(Object.entries(merged).filter(([, v]) => v !== '' && v != null)),
        { preserveState: true, preserveScroll: true, replace: true });
}

function toggle(b) { router.patch(`/admin/blocks/${b.id}/toggle-active`, {}, { preserveScroll: true }); }
function publish(b) { router.patch(`/admin/blocks/${b.id}/publish`, {}, { preserveScroll: true }); }
function destroy(b) {
    if (!confirm(`Удалить блок «${b.block_key}» на странице ${b.page_name}?`)) return;
    router.delete(`/admin/blocks/${b.id}`, { preserveScroll: true });
}

const expanded = reactive({});
function toggleGroup(pageName) {
    expanded[pageName] = !expanded[pageName];
}
// On mount, expand all if a specific page filter is active
if (props.filters.page) expanded[props.filters.page] = true;

function statusBadge(s) {
    const map = {
        draft:     'bg-gray-100 dark:bg-gray-700 text-gray-600',
        published: 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300',
        archived:  'bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300',
    };
    return map[s] || map.draft;
}
function statusLabel(s) { return (props.statuses.find(x => x.value === s) || {}).label || s; }
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title"
        :subtitle="`${counts.total} блоков · ${counts.published} опубликовано · ${counts.drafts} черновиков`">
        <template #actions>
            <Link href="/admin/blocks/create" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-plus"></i><span>Новый блок</span>
            </Link>
        </template>
    </PageHeader>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-4 grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="relative md:col-span-2">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input v-model="search" type="search" placeholder="Поиск по block_key, page_name, title…" class="w-full pl-9 pr-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-red-500">
        </div>
        <select v-model="page" class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm px-3 py-2">
            <option value="">Все страницы</option>
            <option v-for="p in pages" :key="p.slug" :value="p.slug">{{ p.label }}</option>
        </select>
        <select v-model="type" class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm px-3 py-2">
            <option value="">Все типы</option>
            <option v-for="t in types" :key="t.key" :value="t.key">{{ t.label }}</option>
        </select>
        <select v-model="status" class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm px-3 py-2 md:col-span-4 md:max-w-xs">
            <option value="">Все статусы</option>
            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>
    </div>

    <div class="space-y-3">
        <section v-for="(rows, pageName) in groups" :key="pageName"
            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <header @click="toggleGroup(pageName)"
                class="px-5 py-3 flex items-center justify-between cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900/30">
                <div class="flex items-center gap-3 min-w-0">
                    <i class="fas fa-chevron-right text-gray-400 text-xs transition-transform" :class="expanded[pageName] ? 'rotate-90' : ''"></i>
                    <div class="min-w-0">
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ pageLabel(pageName) }}</h3>
                        <code class="text-xs text-gray-400 font-mono">{{ pageName }}</code>
                    </div>
                    <span class="text-xs text-gray-400">{{ rows.length }}</span>
                </div>
                <Link :href="`/admin/blocks/create?page=${pageName}`" @click.stop class="text-xs text-red-600 hover:text-red-700">
                    <i class="fas fa-plus"></i> Блок сюда
                </Link>
            </header>
            <div v-show="expanded[pageName]" class="divide-y divide-gray-100 dark:divide-gray-700">
                <div v-for="b in rows" :key="b.id" class="px-5 py-3 flex items-center gap-3 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                    <div class="text-xs text-gray-400 font-mono w-8">#{{ b.sort_order }}</div>
                    <div class="w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-500 flex items-center justify-center flex-shrink-0" :title="b.type_label">
                        <i :class="'fas ' + (b.type_icon || 'fa-puzzle-piece')"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <Link :href="`/admin/blocks/${b.id}/edit`" class="font-medium text-gray-900 dark:text-white hover:text-red-700 truncate block">
                            {{ b.title || '(без заголовка)' }}
                        </Link>
                        <div class="text-xs text-gray-500 flex items-center gap-2 mt-0.5 flex-wrap">
                            <span class="px-1.5 py-0.5 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 rounded font-medium">{{ b.type_label }}</span>
                            <code class="font-mono text-gray-400">{{ b.block_key }}</code>
                        </div>
                    </div>
                    <span :class="statusBadge(b.status)" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium">
                        {{ statusLabel(b.status) }}
                    </span>
                    <span v-if="b.is_active" class="w-2 h-2 rounded-full bg-green-500" title="Активен"></span>
                    <span v-else class="w-2 h-2 rounded-full bg-gray-400" title="Скрыт"></span>
                    <div class="flex gap-1">
                        <Link :href="`/admin/blocks/${b.id}/edit`" class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded"><i class="fas fa-pen"></i></Link>
                        <button @click="toggle(b)" class="p-2 text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded"><i class="fas" :class="b.is_active ? 'fa-eye-slash' : 'fa-eye'"></i></button>
                        <a :href="`/admin/blocks/${b.id}/edit-legacy`" class="p-2 text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/30 rounded" title="Старый редактор (для сложных блоков)"><i class="fas fa-tools"></i></a>
                        <button @click="destroy(b)" class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
        </section>
        <div v-if="Object.keys(groups).length === 0" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-12 text-center text-gray-500">
            <i class="fas fa-cubes text-4xl mb-2 opacity-30"></i>
            <p>Блоков не найдено</p>
        </div>
    </div>
</template>
