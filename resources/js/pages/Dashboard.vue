<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '../components/PageHeader.vue';

const props = defineProps({
    title:        String,
    stats:        { type: Array, default: () => [] },
    recent:       { type: Array, default: () => [] },
    quickActions: { type: Array, default: () => [] },
});

const hasStats = computed(() => props.stats.length > 0);
const hasRecent = computed(() => props.recent.some(w => w.items?.length));
const hasActions = computed(() => props.quickActions.length > 0);
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title" subtitle="Админ-панель" />

    <!-- Quick actions row -->
    <div v-if="hasActions" class="mb-6 flex flex-wrap gap-2">
        <Link v-for="(a, i) in quickActions" :key="i" :href="a.url"
            class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm">
            <i class="fas" :class="a.icon"></i>
            <span>{{ a.label }}</span>
        </Link>
    </div>

    <!-- KPI stat cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <component v-for="(s, i) in stats" :key="i"
            :is="s.url ? 'a' : 'div'"
            :href="s.url"
            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 transition-all"
            :class="s.url ? 'hover:border-red-600 hover:shadow cursor-pointer' : ''">
            <div class="flex items-center gap-3 mb-2">
                <div v-if="s.icon" class="w-9 h-9 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-300 flex items-center justify-center">
                    <i class="fas" :class="s.icon"></i>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ s.label }}</div>
            </div>
            <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ s.value }}</div>
            <div v-if="s.trend" class="text-xs text-gray-500 mt-1">{{ s.trend }}</div>
        </component>
        <div v-if="!hasStats" class="col-span-full bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500">
            <i class="fas fa-gauge-high text-3xl mb-2 opacity-30"></i>
            <p class="text-sm">
                Добавь статистику через
                <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-900 rounded text-xs font-mono">AdminCore::dashboardStat(fn () =&gt; [...])</code>
            </p>
        </div>
    </div>

    <!-- Recent items widgets -->
    <div v-if="hasRecent" class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div v-for="w in recent" :key="w.resource"
            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <i v-if="w.icon" class="fas text-gray-400" :class="w.icon"></i>
                    {{ w.label }}
                </h3>
                <Link :href="w.index_url" class="text-sm text-red-600 hover:underline inline-flex items-center gap-1">
                    Все <i class="fas fa-arrow-right text-xs"></i>
                </Link>
            </div>
            <div v-if="w.items.length" class="divide-y divide-gray-100 dark:divide-gray-700">
                <Link v-for="r in w.items" :key="r.id" :href="r.url"
                    class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/30 group">
                    <span class="truncate text-sm text-gray-900 dark:text-white group-hover:text-red-700 pr-4">
                        {{ r.title || '(без названия)' }}
                    </span>
                    <span v-if="r.date" class="text-xs text-gray-400 flex-shrink-0">{{ r.date }}</span>
                </Link>
            </div>
            <div v-else class="px-5 py-8 text-center text-sm text-gray-400">Пусто</div>
        </div>
    </div>
</template>
