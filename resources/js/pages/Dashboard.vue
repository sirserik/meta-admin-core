<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '../components/PageHeader.vue';

defineProps({
    title: String,
    stats: Array,
});
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title" subtitle="Админ-панель" />

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="(s, i) in stats" :key="i"
            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center gap-3 mb-2">
                <div v-if="s.icon" class="w-9 h-9 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-300 flex items-center justify-center">
                    <i :class="'fas ' + s.icon"></i>
                </div>
                <div class="text-xs text-gray-500 uppercase tracking-wider">{{ s.label }}</div>
            </div>
            <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ s.value }}</div>
            <div v-if="s.hint" class="text-xs text-gray-500 mt-1">{{ s.hint }}</div>
        </div>
        <div v-if="!stats || stats.length === 0" class="col-span-full bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-12 text-center text-gray-500">
            <i class="fas fa-gauge-high text-4xl mb-2 opacity-30"></i>
            <p>Добавь статистику через <code class="font-mono">AdminCore::dashboardStat(fn () =&gt; [...])</code></p>
        </div>
    </div>
</template>
