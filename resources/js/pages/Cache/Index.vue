<script setup>
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@admin-core/components/PageHeader.vue';

const props = defineProps({
    title: String,
    stats: Object,
    groups: Array,
});

function flush(group) {
    if (group === 'all' && !confirm('Очистить ВЕСЬ кэш?')) return;
    router.post('/admin/cache/flush', { group }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title" subtitle="Управление кэшем приложения">
        <template #actions>
            <button @click="flush('all')" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-broom"></i><span>Очистить всё</span>
            </button>
        </template>
    </PageHeader>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div v-for="(val, key) in stats" :key="key"
            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <div class="text-xs text-gray-500 uppercase tracking-wider">{{ key }}</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ val }}</div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <ul class="divide-y divide-gray-100 dark:divide-gray-700">
            <li v-for="g in groups" :key="g.key" class="px-5 py-4 flex items-center gap-4">
                <div class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-500">
                    <i class="fas fa-folder"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-gray-900 dark:text-white">{{ g.label }}</div>
                    <div v-if="g.description" class="text-sm text-gray-500 dark:text-gray-400">{{ g.description }}</div>
                    <code class="text-xs text-gray-400 font-mono">{{ g.key }}</code>
                </div>
                <button @click="flush(g.key)" class="px-3 py-1.5 bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 hover:bg-amber-100 rounded-lg text-sm">
                    <i class="fas fa-broom mr-1"></i> Очистить
                </button>
            </li>
        </ul>
    </div>
</template>
