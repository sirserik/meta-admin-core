<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@admin-core/layouts/AdminLayout.vue';

const props = defineProps({
    title: String,
    forms: Array,
});
defineOptions({ layout: AdminLayout });

function destroy(f) {
    if (!confirm(`Удалить форму «${f.name}»?`)) return;
    router.delete(`/admin/forms/${f.id}`);
}
</script>

<template>
    <div class="max-w-5xl mx-auto p-6 space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Формы</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Конструктор форм с привязкой публичного POST-эндпоинта <code class="font-mono text-xs">/api/forms/{'{slug}'}</code>.
                </p>
            </div>
            <Link href="/admin/forms/create"
                  class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                + Новая форма
            </Link>
        </div>

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <div v-if="forms.length === 0" class="p-8 text-center text-gray-500 dark:text-gray-400">
                Форм пока нет.
            </div>
            <ul v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                <li v-for="f in forms" :key="f.id" class="px-5 py-4 flex items-center gap-4">
                    <span class="w-2 h-2 rounded-full" :class="f.is_active ? 'bg-green-500' : 'bg-gray-400'"></span>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-gray-900 dark:text-white truncate">{{ f.name }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                            /api/forms/{{ f.slug }} · {{ f.fields_count }} пол{{ f.fields_count === 1 ? 'е' : 'ей' }}
                        </div>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                        <i class="fas fa-inbox mr-1"></i>{{ f.submissions_count }}
                    </div>
                    <Link :href="`/admin/forms/${f.id}/submissions`"
                          class="px-3 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300">
                        Заявки
                    </Link>
                    <Link :href="`/admin/forms/${f.id}/edit`"
                          class="px-3 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300">
                        Ред.
                    </Link>
                    <button @click="destroy(f)"
                            class="px-2 py-1.5 text-xs rounded-md text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30">
                        <i class="fas fa-trash"></i>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
