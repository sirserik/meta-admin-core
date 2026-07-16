<script setup>
import { ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@admin-core/components/PageHeader.vue';
import Pagination from '@admin-core/components/Pagination.vue';

const props = defineProps({
    title: String,
    items: Object,
    folders: Array,
    stats: Object,
    filters: Object,
});

const search = ref(props.filters.search ?? '');
const folder = ref(props.filters.folder ?? '');
const type = ref(props.filters.type ?? '');
let tm = null;
watch(search, (v) => { clearTimeout(tm); tm = setTimeout(() => apply({ search: v, folder: folder.value, type: type.value }), 300); });
watch(folder, (v) => apply({ search: search.value, folder: v, type: type.value }));
watch(type, (v) => apply({ search: search.value, folder: folder.value, type: v }));

function apply(params) {
    router.get('/admin/media',
        Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v != null)),
        { preserveState: true, preserveScroll: true, replace: true });
}

const uploadForm = useForm({ files: [], folder: 'uploads' });
function upload(e) {
    uploadForm.files = Array.from(e.target.files);
    if (!uploadForm.files.length) return;
    uploadForm.post('/admin/media', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { uploadForm.reset('files'); e.target.value = ''; },
    });
}

function destroy(m) {
    if (!confirm(`Удалить «${m.filename}»?`)) return;
    router.delete(`/admin/media/${m.id}`, { preserveScroll: true });
}

// Поворот на месте (путь не меняется) → превью обновляем cache-buster'ом
const rotatedAt = ref({});
const canRotate = (m) => m.is_image && m.mime_type !== 'image/svg+xml';
function rotate(m) {
    router.post(`/admin/media/${m.id}/rotate`, { degrees: 90 }, {
        preserveScroll: true,
        onSuccess: () => { rotatedAt.value = { ...rotatedAt.value, [m.id]: Date.now() }; },
    });
}
const bustedUrl = (m) => m.url + (rotatedAt.value[m.id] ? `?v=${rotatedAt.value[m.id]}` : '');

function humanSize(b) {
    if (!b) return '0 Б';
    const u = ['Б', 'КБ', 'МБ', 'ГБ']; let i = 0;
    while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; }
    return b.toFixed(1) + ' ' + u[i];
}
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title"
        :subtitle="`Всего: ${stats.total} · Картинок: ${stats.images} · Объём: ${humanSize(stats.size)}`">
        <template #actions>
            <label class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium cursor-pointer">
                <i class="fas fa-upload"></i><span>Загрузить</span>
                <input type="file" multiple class="hidden" @change="upload">
            </label>
        </template>
    </PageHeader>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-4 flex flex-col md:flex-row gap-3">
        <div class="relative flex-1">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input v-model="search" type="search" placeholder="Поиск по имени файла / alt…" class="w-full pl-9 pr-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-red-500">
        </div>
        <select v-model="folder" class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm px-3 py-2">
            <option value="">Все папки</option>
            <option v-for="f in folders" :key="f" :value="f">{{ f }}</option>
        </select>
        <select v-model="type" class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm px-3 py-2">
            <option value="">Все файлы</option>
            <option value="images">Только картинки</option>
        </select>
    </div>

    <p v-if="uploadForm.processing" class="text-sm text-gray-500 mb-3"><i class="fas fa-spinner fa-spin mr-1"></i> Загрузка: {{ uploadForm.progress?.percentage ?? 0 }}%</p>

    <div v-if="items.data.length" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-3">
        <div v-for="m in items.data" :key="m.id"
            class="group relative bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-md transition">
            <div class="aspect-square flex items-center justify-center bg-gray-100 dark:bg-gray-900">
                <img v-if="m.is_image" :src="bustedUrl(m)" :alt="m.alt || m.filename" class="w-full h-full object-cover">
                <i v-else class="fas fa-file text-3xl text-gray-400"></i>
            </div>
            <div class="p-2">
                <div class="text-xs font-medium text-gray-900 dark:text-white truncate" :title="m.filename">{{ m.filename }}</div>
                <div class="text-[11px] text-gray-500 flex justify-between">
                    <span>{{ humanSize(m.size) }}</span>
                    <span v-if="m.width">{{ m.width }}×{{ m.height }}</span>
                </div>
            </div>
            <div class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition flex gap-1">
                <button v-if="canRotate(m)" @click="rotate(m)"
                    class="w-7 h-7 rounded-full bg-white/90 dark:bg-gray-900/90 text-gray-700 flex items-center justify-center text-xs" title="Повернуть на 90° по часовой">
                    <i class="fas fa-rotate-right"></i>
                </button>
                <button @click="navigator.clipboard.writeText(m.url)"
                    class="w-7 h-7 rounded-full bg-white/90 dark:bg-gray-900/90 text-gray-700 flex items-center justify-center text-xs" title="Скопировать URL">
                    <i class="fas fa-copy"></i>
                </button>
                <button @click="destroy(m)" class="w-7 h-7 rounded-full bg-white/90 dark:bg-gray-900/90 text-red-600 flex items-center justify-center text-xs">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    </div>
    <div v-else class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-12 text-center text-gray-500">
        <i class="fas fa-photo-film text-4xl mb-2 opacity-30"></i>
        <p>Медиа-файлов не найдено</p>
    </div>

    <Pagination :meta="items" />
</template>
