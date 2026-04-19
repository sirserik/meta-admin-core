<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@admin-core/layouts/AdminLayout.vue';

const props = defineProps({
    title:     String,
    resource:  String,
    record:    Object,
    revisions: Array,
});

defineOptions({ layout: AdminLayout });

const expanded = ref(null);
function toggle(id) { expanded.value = expanded.value === id ? null : id; }

function restore(rev) {
    if (!confirm(`Восстановить состояние от ${rev.created_at}? Текущее станет новой ревизией.`)) return;
    router.post(`/admin/${props.resource}/${props.record.id}/revisions/${rev.id}/restore`, {}, {
        preserveScroll: true,
    });
}

const empty = computed(() => (props.revisions ?? []).length === 0);
</script>

<template>
    <div class="max-w-4xl mx-auto p-6 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">История изменений</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ record.label || `#${record.id}` }}
                </p>
            </div>
            <a :href="record.edit_url"
               class="px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-800 dark:text-gray-200 rounded-lg text-sm font-medium inline-flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> К редактированию
            </a>
        </div>

        <div v-if="empty" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-8 text-center text-gray-500 dark:text-gray-400">
            <i class="fas fa-clock-rotate-left text-4xl mb-3 block text-gray-300 dark:text-gray-600"></i>
            Ревизий ещё нет — запись не редактировалась после подключения trait.
        </div>

        <div v-else class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                <li v-for="rev in revisions" :key="rev.id" class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                    <div class="flex items-center gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 text-sm">
                                <i class="fas fa-clock text-gray-400"></i>
                                <span class="font-medium text-gray-900 dark:text-white">{{ rev.created_at }}</span>
                            </div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 truncate">
                                <template v-if="rev.user">
                                    {{ rev.user.name || rev.user.email }}
                                </template>
                                <template v-else>система / CLI</template>
                                <span v-if="rev.note"> — {{ rev.note }}</span>
                            </div>
                        </div>
                        <button type="button" @click="toggle(rev.id)"
                                class="px-3 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300">
                            {{ expanded === rev.id ? 'Скрыть' : 'Показать' }}
                        </button>
                        <button type="button" @click="restore(rev)"
                                class="px-3 py-1.5 text-xs rounded-md bg-red-600 hover:bg-red-700 text-white font-medium">
                            Восстановить
                        </button>
                    </div>
                    <pre v-if="expanded === rev.id"
                         class="mt-4 p-3 rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-xs text-gray-800 dark:text-gray-200 overflow-auto max-h-96">{{ JSON.stringify(rev.data, null, 2) }}</pre>
                </li>
            </ul>
        </div>
    </div>
</template>
