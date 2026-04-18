<script setup>
/**
 * Inline viewer for uploaded documents / images.
 *
 *  - PDF    → iframe with the PDF embedded (browser's native PDF viewer)
 *  - image  → <img>
 *  - other  → "Открыть в новой вкладке" / "Скачать" buttons
 *
 * Opened via `open(url, name)`. Close on ESC or backdrop click.
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';

const visible = ref(false);
const url = ref('');
const name = ref('');

function open(u, n = '') {
    url.value = u;
    name.value = n;
    visible.value = true;
}
function close() {
    visible.value = false;
    url.value = '';
    name.value = '';
}
defineExpose({ open, close });

function onEsc(e) { if (e.key === 'Escape') close(); }
onMounted(() => window.addEventListener('keydown', onEsc));
onUnmounted(() => window.removeEventListener('keydown', onEsc));

const ext = computed(() => {
    const n = (url.value || '').split('?')[0].split('#')[0];
    const dot = n.lastIndexOf('.');
    return dot >= 0 ? n.slice(dot + 1).toLowerCase() : '';
});
const kind = computed(() => {
    if (ext.value === 'pdf') return 'pdf';
    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'].includes(ext.value)) return 'image';
    if (['mp4', 'webm', 'ogg', 'mov'].includes(ext.value)) return 'video';
    if (['mp3', 'wav', 'ogg', 'm4a'].includes(ext.value)) return 'audio';
    return 'other';
});
const filename = computed(() => {
    if (name.value) return name.value;
    const n = (url.value || '').split('?')[0].split('#')[0];
    return decodeURIComponent(n.split('/').filter(Boolean).pop() || url.value);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-150 ease-out"
            leave-active-class="transition-opacity duration-100 ease-in"
            enter-from-class="opacity-0" leave-to-class="opacity-0">
            <div v-if="visible" class="fixed inset-0 z-[110] flex items-center justify-center p-3 sm:p-6">
                <div class="absolute inset-0 bg-gray-900/70 backdrop-blur-sm" @click="close"></div>
                <div class="relative w-full max-w-5xl h-[85vh] bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col">
                    <!-- Header -->
                    <header class="h-14 px-4 flex items-center gap-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 flex-shrink-0">
                        <i class="fas fa-file text-gray-400"></i>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ filename }}</div>
                            <div class="text-xs text-gray-500 uppercase">{{ ext || 'file' }}</div>
                        </div>
                        <a :href="url" target="_blank" rel="noopener"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 rounded">
                            <i class="fas fa-external-link-alt"></i> Новая вкладка
                        </a>
                        <a :href="url" :download="filename"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-red-600 hover:bg-red-700 text-white rounded">
                            <i class="fas fa-download"></i> Скачать
                        </a>
                        <button @click="close" class="p-2 text-gray-500 hover:text-gray-800 dark:hover:text-white rounded">
                            <i class="fas fa-xmark text-lg"></i>
                        </button>
                    </header>

                    <!-- Body -->
                    <div class="flex-1 min-h-0 bg-gray-100 dark:bg-gray-900">
                        <iframe v-if="kind === 'pdf'" :src="url" class="w-full h-full bg-white"></iframe>

                        <div v-else-if="kind === 'image'" class="w-full h-full flex items-center justify-center p-6 overflow-auto">
                            <img :src="url" :alt="filename" class="max-w-full max-h-full object-contain rounded shadow">
                        </div>

                        <div v-else-if="kind === 'video'" class="w-full h-full flex items-center justify-center p-6 bg-black">
                            <video :src="url" controls class="max-w-full max-h-full"></video>
                        </div>

                        <div v-else-if="kind === 'audio'" class="w-full h-full flex items-center justify-center p-6">
                            <audio :src="url" controls class="w-full max-w-lg"></audio>
                        </div>

                        <div v-else class="w-full h-full flex flex-col items-center justify-center p-8 text-center">
                            <i class="fas fa-file text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                            <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-2">Предпросмотр недоступен</h3>
                            <p class="text-sm text-gray-500 mb-5 max-w-md">
                                Файлы <strong class="uppercase">{{ ext || '?' }}</strong> браузер не умеет показывать встроенно — но его можно открыть или скачать.
                            </p>
                            <div class="flex items-center gap-2">
                                <a :href="url" target="_blank" rel="noopener"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg text-sm hover:bg-gray-200">
                                    <i class="fas fa-external-link-alt"></i> Открыть
                                </a>
                                <a :href="url" :download="filename"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm">
                                    <i class="fas fa-download"></i> Скачать
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
