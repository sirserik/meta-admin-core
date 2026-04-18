<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@admin-core/components/PageHeader.vue';

const props = defineProps({
    title: String,
    features: { type: Array, default: () => [] },
});

const busy = ref(null);
function toggle(feat) {
    if (!feat.available) return;
    busy.value = feat.name;
    router.patch(`/admin/features/${feat.name}`, { enabled: !feat.enabled }, {
        preserveScroll: true,
        onFinish: () => { busy.value = null; },
    });
}
</script>

<template>
    <Head :title="title" />
    <PageHeader title="Фичи" subtitle="Опциональные модули — включай и выключай одной кнопкой">
    </PageHeader>

    <div class="mb-6 flex items-start gap-3 text-sm bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-900/50 text-blue-900 dark:text-blue-200 px-4 py-3 rounded-lg">
        <i class="fas fa-info-circle mt-0.5"></i>
        <div>
            <p>Здесь — модули, что поставляются с <code class="font-mono bg-blue-100 dark:bg-blue-900/40 px-1 rounded">meta/admin-core</code>. Включение сохраняется в БД (settings.feature_*), переживает деплой и перебивает <code class="font-mono">.env</code> флаги.</p>
            <p class="text-xs mt-1 opacity-80">После переключения обнови страницу жёстко (Cmd+Shift+R) — сайдбар увидит новые разделы.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div v-for="f in features" :key="f.name"
            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 transition"
            :class="!f.available ? 'opacity-60' : ''">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-3 flex-1">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                        :class="f.enabled ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-500'">
                        <i class="fas text-lg" :class="f.icon"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ f.label }}</h3>
                        <p class="text-xs text-gray-400 font-mono mt-0.5">{{ f.name }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">{{ f.description }}</p>
                        <p v-if="!f.available && f.unavailableReason" class="mt-2 text-xs text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-900/40 rounded px-2 py-1">
                            <i class="fas fa-triangle-exclamation mr-1"></i>
                            {{ f.unavailableReason }}
                        </p>
                    </div>
                </div>

                <!-- Toggle switch -->
                <button type="button" @click="toggle(f)"
                    :disabled="!f.available || busy === f.name"
                    :aria-pressed="f.enabled"
                    :class="[
                        'relative inline-flex h-7 w-12 items-center rounded-full transition flex-shrink-0',
                        f.enabled ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600',
                        !f.available ? 'cursor-not-allowed' : 'cursor-pointer',
                    ]">
                    <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition"
                        :class="f.enabled ? 'translate-x-6' : 'translate-x-1'"></span>
                    <i v-if="busy === f.name" class="fas fa-spinner fa-spin absolute inset-0 flex items-center justify-center text-xs text-white"></i>
                </button>
            </div>
        </div>
    </div>

    <div v-if="features.length === 0" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-12 text-center text-gray-500">
        <i class="fas fa-puzzle-piece text-4xl mb-2 opacity-30"></i>
        <p>Пока нет зарегистрированных фич</p>
    </div>
</template>
