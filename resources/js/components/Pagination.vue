<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    meta: { type: Object, required: true }, // Laravel paginator meta (links array)
});
</script>

<template>
    <nav v-if="meta.last_page > 1" class="flex items-center justify-between mt-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">
            {{ meta.from }}–{{ meta.to }} из {{ meta.total }}
        </div>
        <div class="flex gap-1">
            <component
                :is="link.url ? Link : 'span'"
                v-for="(link, i) in meta.links"
                :key="i"
                :href="link.url"
                preserve-scroll
                preserve-state
                v-html="link.label"
                class="px-3 py-1.5 text-sm rounded border"
                :class="link.active
                    ? 'bg-red-600 text-white border-red-600'
                    : (link.url ? 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' : 'text-gray-400 border-transparent cursor-default')"
            />
        </div>
    </nav>
</template>
