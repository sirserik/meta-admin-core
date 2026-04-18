<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@admin-core/components/PageHeader.vue';

const props = defineProps({
    title: String,
    lead: Object,
    statuses: Array,
});

const statusForm = useForm({ status: props.lead.status });

function updateStatus() {
    statusForm.patch(`/admin/leads/${props.lead.id}/status`, { preserveScroll: true });
}
function destroy() {
    if (!confirm(`Удалить заявку от «${props.lead.name}»?`)) return;
    router.delete(`/admin/leads/${props.lead.id}`);
}

// --- UA parsing: достаём браузер / устройство / OS, чтобы не показывать простыню.
const ua = computed(() => {
    const s = props.lead.user_agent || '';
    if (!s) return null;

    // Browser
    let browser = 'Браузер';
    let browserIcon = 'fa-globe';
    if (/Edg\//.test(s))         { browser = 'Edge';    browserIcon = 'fa-edge'; }
    else if (/OPR|Opera/.test(s)){ browser = 'Opera';   browserIcon = 'fa-opera'; }
    else if (/Firefox/.test(s))  { browser = 'Firefox'; browserIcon = 'fa-firefox-browser'; }
    else if (/Chrome/.test(s) && !/Chromium/.test(s)) { browser = 'Chrome';  browserIcon = 'fa-chrome'; }
    else if (/Safari/.test(s))   { browser = 'Safari';  browserIcon = 'fa-safari'; }

    // Platform / device
    let os = 'OS';
    let osIcon = 'fa-desktop';
    let isMobile = /Mobi|Android|iPhone|iPad/.test(s);
    if (/iPhone/.test(s))        { os = 'iPhone';  osIcon = 'fa-apple'; }
    else if (/iPad/.test(s))     { os = 'iPad';    osIcon = 'fa-apple'; }
    else if (/Android/.test(s))  { os = 'Android'; osIcon = 'fa-android'; }
    else if (/Mac OS X|Macintosh/.test(s)) { os = 'macOS';  osIcon = 'fa-apple'; }
    else if (/Windows/.test(s))  { os = 'Windows'; osIcon = 'fa-windows'; }
    else if (/Linux/.test(s))    { os = 'Linux';   osIcon = 'fa-linux'; }

    return { browser, browserIcon, os, osIcon, isMobile, raw: s };
});

const copied = ref('');
async function copy(value, key) {
    try {
        await navigator.clipboard.writeText(value);
        copied.value = key;
        setTimeout(() => (copied.value = ''), 1400);
    } catch {}
}
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title">
        <template #actions>
            <Link href="/admin/leads" class="inline-flex items-center gap-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-arrow-left"></i><span>К списку</span>
            </Link>
            <button @click="destroy" class="inline-flex items-center gap-2 bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 hover:bg-red-100 px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-trash"></i><span>Удалить</span>
            </button>
        </template>
    </PageHeader>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="font-semibold text-gray-900 dark:text-white mb-4">Контактная информация</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Имя</dt>
                        <dd class="font-medium text-gray-900 dark:text-white mt-0.5">{{ lead.name || '—' }}</dd>
                    </div>
                    <div v-if="lead.email">
                        <dt class="text-gray-500 dark:text-gray-400">Email</dt>
                        <dd class="mt-0.5"><a :href="`mailto:${lead.email}`" class="text-red-600 hover:underline">{{ lead.email }}</a></dd>
                    </div>
                    <div v-if="lead.phone">
                        <dt class="text-gray-500 dark:text-gray-400">Телефон</dt>
                        <dd class="mt-0.5 font-mono"><a :href="`tel:${lead.phone}`" class="text-red-600 hover:underline">{{ lead.phone }}</a></dd>
                    </div>
                    <div v-if="lead.year">
                        <dt class="text-gray-500 dark:text-gray-400">Год поступления</dt>
                        <dd class="font-medium text-gray-900 dark:text-white mt-0.5">{{ lead.year }}</dd>
                    </div>
                    <div v-if="lead.program">
                        <dt class="text-gray-500 dark:text-gray-400">Программа</dt>
                        <dd class="font-medium text-gray-900 dark:text-white mt-0.5">{{ lead.program }}</dd>
                    </div>
                    <div v-if="lead.call_time">
                        <dt class="text-gray-500 dark:text-gray-400">Удобное время</dt>
                        <dd class="font-medium text-gray-900 dark:text-white mt-0.5">{{ lead.call_time }}</dd>
                    </div>
                </dl>
            </div>

            <div v-if="lead.message || lead.questions" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="font-semibold text-gray-900 dark:text-white mb-2">Сообщение</h2>
                <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ lead.message || lead.questions }}</p>
            </div>

            <div v-if="lead.interests && lead.interests.length" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="font-semibold text-gray-900 dark:text-white mb-2">Интересы</h2>
                <div class="flex flex-wrap gap-2">
                    <span v-for="i in lead.interests" :key="i" class="px-2.5 py-1 bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded-full text-xs">{{ i }}</span>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-3">
                <h3 class="font-semibold text-gray-900 dark:text-white">Статус</h3>
                <select v-model="statusForm.status"
                    @change="updateStatus"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm">
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <p v-if="statusForm.processing" class="text-xs text-gray-500">Сохранение…</p>
                <p v-if="statusForm.errors.status" class="text-xs text-red-500">{{ statusForm.errors.status }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
                <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <i class="fas fa-circle-info text-gray-400"></i>
                    <span>Метаданные</span>
                </h3>

                <dl class="space-y-3 text-sm">
                    <!-- Created -->
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-500 flex-shrink-0">
                            <i class="fas fa-clock text-xs"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <dt class="text-xs uppercase tracking-wider text-gray-400 mb-0.5">Создано</dt>
                            <dd class="text-gray-900 dark:text-white font-medium">{{ lead.created_at }}</dd>
                        </div>
                    </div>

                    <!-- Source -->
                    <div v-if="lead.source" class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-300 flex-shrink-0">
                            <i class="fas fa-signs-post text-xs"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <dt class="text-xs uppercase tracking-wider text-gray-400 mb-0.5">Источник</dt>
                            <dd>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-mono text-xs">
                                    {{ lead.source }}
                                </span>
                            </dd>
                        </div>
                    </div>

                    <!-- IP -->
                    <div v-if="lead.ip_address" class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-500 flex-shrink-0">
                            <i class="fas fa-location-dot text-xs"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <dt class="text-xs uppercase tracking-wider text-gray-400 mb-0.5">IP-адрес</dt>
                            <dd class="flex items-center gap-2">
                                <span class="font-mono text-gray-900 dark:text-white">{{ lead.ip_address }}</span>
                                <button type="button" @click="copy(lead.ip_address, 'ip')"
                                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-xs transition"
                                    :title="copied === 'ip' ? 'Скопировано' : 'Скопировать'">
                                    <i class="fas" :class="copied === 'ip' ? 'fa-check text-green-500' : 'fa-copy'"></i>
                                </button>
                            </dd>
                        </div>
                    </div>

                    <!-- Device / Browser -->
                    <div v-if="ua" class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-500 flex-shrink-0">
                            <i class="fas text-xs" :class="ua.isMobile ? 'fa-mobile-screen' : 'fa-desktop'"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <dt class="text-xs uppercase tracking-wider text-gray-400 mb-0.5">Устройство</dt>
                            <dd class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-medium">
                                    <i class="fab" :class="ua.osIcon"></i>{{ ua.os }}
                                </span>
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-medium">
                                    <i class="fab" :class="ua.browserIcon"></i>{{ ua.browser }}
                                </span>
                            </dd>
                            <details class="mt-1.5">
                                <summary class="text-xs text-gray-400 hover:text-gray-600 cursor-pointer select-none">Показать User-Agent</summary>
                                <div class="mt-1.5 flex items-start gap-1.5">
                                    <code class="flex-1 text-[11px] leading-relaxed text-gray-500 bg-gray-50 dark:bg-gray-900/40 rounded px-2 py-1.5 break-all">{{ ua.raw }}</code>
                                    <button type="button" @click="copy(ua.raw, 'ua')"
                                        class="text-gray-400 hover:text-gray-600 text-xs mt-1"
                                        :title="copied === 'ua' ? 'Скопировано' : 'Скопировать'">
                                        <i class="fas" :class="copied === 'ua' ? 'fa-check text-green-500' : 'fa-copy'"></i>
                                    </button>
                                </div>
                            </details>
                        </div>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</template>
