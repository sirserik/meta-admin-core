<script setup>
/**
 * Cmd+K / Ctrl+K quick navigation. Pulls items from the admin
 * navigation shared prop, lets the user fuzzy-filter, and routes
 * via Inertia. ESC / overlay click closes.
 */
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
const open = ref(false);
const query = ref('');
const cursor = ref(0);
const inputEl = ref(null);

const allItems = computed(() => {
    const out = [];
    const nav = page.props.navigation ?? [];
    for (const group of nav) {
        for (const item of (group.items ?? [])) {
            out.push({ ...item, group: group.section });
        }
    }
    return out;
});

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return allItems.value.slice(0, 30);
    return allItems.value.filter((x) => {
        return (x.label || '').toLowerCase().includes(q)
            || (x.href  || '').toLowerCase().includes(q)
            || (x.group || '').toLowerCase().includes(q);
    }).slice(0, 30);
});

watch(query, () => { cursor.value = 0; });
watch(open, async (v) => {
    if (v) {
        query.value = '';
        cursor.value = 0;
        await nextTick();
        inputEl.value?.focus();
    }
});

function go(item) {
    if (!item) return;
    open.value = false;
    router.visit(item.href);
}

function onKey(e) {
    // Open / close with Cmd+K or Ctrl+K
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        open.value = !open.value;
        return;
    }
    if (!open.value) return;
    if (e.key === 'Escape') {
        e.preventDefault();
        open.value = false;
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        cursor.value = Math.min(cursor.value + 1, filtered.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        cursor.value = Math.max(cursor.value - 1, 0);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        go(filtered.value[cursor.value]);
    }
}

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));

defineExpose({ open: () => { open.value = true; } });
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[100] flex items-start justify-center p-4 sm:pt-[12vh]">
            <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="open = false"></div>
            <div class="relative w-full max-w-xl bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                    <i class="fas fa-search text-gray-400"></i>
                    <input ref="inputEl" v-model="query" type="text"
                        placeholder="Перейти к разделу или ресурсу…"
                        class="flex-1 bg-transparent outline-none text-gray-900 dark:text-white placeholder-gray-400 text-sm">
                    <kbd class="hidden sm:inline text-[10px] font-mono text-gray-400 border border-gray-200 dark:border-gray-600 rounded px-1.5 py-0.5">ESC</kbd>
                </div>
                <ul class="max-h-[60vh] overflow-y-auto">
                    <li v-for="(it, i) in filtered" :key="it.href"
                        @click="go(it)"
                        @mouseenter="cursor = i"
                        class="flex items-center gap-3 px-4 py-2.5 cursor-pointer"
                        :class="cursor === i ? 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300' : 'text-gray-700 dark:text-gray-300'">
                        <i :class="it.icon" class="w-4 text-center opacity-70"></i>
                        <span class="flex-1 truncate text-sm">{{ it.label }}</span>
                        <span class="text-[11px] text-gray-400 dark:text-gray-500">{{ it.group }}</span>
                    </li>
                    <li v-if="filtered.length === 0" class="px-4 py-8 text-center text-sm text-gray-400">
                        Ничего не найдено
                    </li>
                </ul>
            </div>
        </div>
    </Teleport>
</template>
