<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@admin-core/components/PageHeader.vue';

const props = defineProps({
    title: String,
    status: { type: Object, default: () => ({}) },
    log: { type: String, default: null },
    capabilities: { type: Object, default: () => ({}) },
});

const checkForm = useForm({});
const runForm = useForm({});
const confirmOpen = ref(false);

const badge = computed(() => {
    if (props.status.available) return { text: 'Доступно обновление', cls: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' };
    if (props.status.error)    return { text: 'Ошибка проверки',      cls: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' };
    return { text: 'Актуальная версия',                                cls: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' };
});

function confirmUpdate() {
    confirmOpen.value = false;
    runForm.post('/admin/updates/run', { preserveScroll: true });
}
</script>

<template>
    <Head :title="title" />
    <PageHeader title="Обновления системы" subtitle="meta/admin-core" />

    <!-- Version card -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="space-y-1">
                <div class="flex items-center gap-3">
                    <span class="text-xs uppercase tracking-wider text-gray-400">Установлена</span>
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ status.current }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs uppercase tracking-wider text-gray-400">На GitHub</span>
                    <span class="text-2xl font-semibold" :class="status.available ? 'text-amber-600' : 'text-gray-500'">
                        {{ status.latest }}
                    </span>
                </div>
            </div>
            <div class="flex flex-col items-end gap-2">
                <span :class="badge.cls" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium">
                    {{ badge.text }}
                </span>
                <span v-if="status.checked_at" class="text-xs text-gray-400">
                    Проверено: {{ new Date(status.checked_at).toLocaleString() }}
                </span>
            </div>
        </div>

        <div class="mt-5 flex items-center gap-2 flex-wrap">
            <button @click="checkForm.post('/admin/updates/check')" :disabled="checkForm.processing"
                class="inline-flex items-center gap-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg text-sm disabled:opacity-50">
                <i class="fas" :class="checkForm.processing ? 'fa-spinner fa-spin' : 'fa-rotate'"></i>
                Проверить заново
            </button>
            <button v-if="status.available"
                @click="confirmOpen = true" :disabled="runForm.processing"
                class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm">
                <i class="fas" :class="runForm.processing ? 'fa-spinner fa-spin' : 'fa-download'"></i>
                Обновить до {{ status.latest }}
            </button>
            <a :href="`https://github.com/sirserik/meta-admin-core/releases/tag/v${status.latest}`" target="_blank"
                v-if="status.latest && status.latest !== 'unknown'"
                class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 ml-auto">
                GitHub релиз <i class="fas fa-arrow-up-right-from-square text-[10px]"></i>
            </a>
        </div>

        <div v-if="status.error" class="mt-4 text-sm text-red-600 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900/50 rounded-lg p-3">
            Ошибка при проверке: {{ status.error }}
        </div>
    </div>

    <!-- Changelog -->
    <div v-if="status.available && status.changelog" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
            <i class="fas fa-scroll text-amber-500"></i>
            Что нового в {{ status.latest }}
        </h3>
        <pre class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap font-sans leading-relaxed">{{ status.changelog }}</pre>
    </div>

    <!-- Environment capabilities -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
            <i class="fas fa-server text-gray-400"></i>
            Окружение
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            <div class="flex items-center gap-2">
                <i :class="capabilities.composer_found ? 'fa-check text-green-600' : 'fa-xmark text-red-600'" class="fas w-4"></i>
                <span class="text-gray-700 dark:text-gray-300">composer</span>
                <span v-if="capabilities.composer_path" class="text-xs text-gray-400 font-mono truncate">{{ capabilities.composer_path }}</span>
            </div>
            <div class="flex items-center gap-2">
                <i :class="capabilities.npm_found ? 'fa-check text-green-600' : 'fa-xmark text-amber-500'" class="fas w-4"></i>
                <span class="text-gray-700 dark:text-gray-300">npm</span>
                <span v-if="!capabilities.npm_found" class="text-xs text-amber-500">— сборку фронта запусти вручную</span>
            </div>
            <div class="flex items-center gap-2">
                <i :class="capabilities.shell_exec ? 'fa-check text-green-600' : 'fa-xmark text-red-600'" class="fas w-4"></i>
                <span class="text-gray-700 dark:text-gray-300">shell_exec()</span>
            </div>
            <div class="flex items-center gap-2">
                <i :class="capabilities.writable_vendor ? 'fa-check text-green-600' : 'fa-xmark text-red-600'" class="fas w-4"></i>
                <span class="text-gray-700 dark:text-gray-300">vendor/ доступен на запись</span>
            </div>
        </div>
    </div>

    <!-- Previous run log -->
    <div v-if="log" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                <i class="fas fa-terminal text-gray-400"></i>
                Лог последнего обновления
            </h3>
        </div>
        <pre class="p-6 text-xs font-mono text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-900/50 overflow-x-auto max-h-96">{{ log }}</pre>
    </div>

    <!-- Confirm modal -->
    <div v-if="confirmOpen" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="confirmOpen = false">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6">
            <div class="flex items-start gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-600 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-white">Запустить обновление?</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Процесс займёт 1–3 минуты. Админка на это время может быть недоступна. Рекомендуется сделать бэкап перед обновлением.
                    </p>
                </div>
            </div>
            <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-5 pl-4">
                <li>• composer update meta/admin-core</li>
                <li>• php artisan migrate --force</li>
                <li>• очистка кэша (config, route, view)</li>
                <li>• npm run build (если доступен)</li>
            </ul>
            <div class="flex gap-2 justify-end">
                <button @click="confirmOpen = false" class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg text-sm">
                    Отмена
                </button>
                <button @click="confirmUpdate" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium">
                    Да, обновить
                </button>
            </div>
        </div>
    </div>
</template>
